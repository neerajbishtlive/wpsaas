<?php

namespace App\Jobs;

use App\Models\Site;
use App\Models\User;
use App\Services\NginxConfigService;
use App\Services\WordPressProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SuspendUnpaidSites implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(
        NginxConfigService $nginxService,
        WordPressProvisioningService $wpService
    ): void {
        Log::info('Starting unpaid sites suspension check');

        // Get all active sites that need suspension
        $sitesToSuspend = Site::where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where(function ($q) {
                    // Users with expired subscriptions
                    $q->whereNotNull('subscription_ends_at')
                        ->where('subscription_ends_at', '<', now())
                        ->where('plan_id', '!=', 1); // Not free plan
                })
                ->orWhere(function ($q) {
                    // Users with cancelled subscriptions past grace period
                    $q->whereNotNull('subscription_cancelled_at')
                        ->where('subscription_cancelled_at', '<', now()->subDays(3));
                })
                ->orWhere(function ($q) {
                    // Users with failed payments (after retry period)
                    $q->where('payment_status', 'failed')
                        ->where('payment_failed_at', '<', now()->subDays(7));
                });
            })
            ->get();

        foreach ($sitesToSuspend as $site) {
            try {
                $this->suspendSite($site, $nginxService, $wpService);
            } catch (\Exception $e) {
                Log::error("Failed to suspend site {$site->id}: " . $e->getMessage());
                continue;
            }
        }

        // Check for sites to auto-delete after suspension period
        $this->handleAutoDeleteSites();

        // Send warning emails for sites approaching suspension
        $this->sendSuspensionWarnings();

        Log::info("Completed unpaid sites check. Suspended: {$sitesToSuspend->count()} sites");
    }

    /**
     * Suspend a single site
     */
    protected function suspendSite(
        Site $site, 
        NginxConfigService $nginxService,
        WordPressProvisioningService $wpService
    ): void {
        Log::info("Suspending site: {$site->subdomain}");

        // Update site status
        $site->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $this->getSuspensionReason($site->user),
        ]);

        // Create suspension notice file
        $suspensionHtml = $this->createSuspensionNotice($site);
        $sitePath = config('app.sites_path') . '/' . $site->directory;
        
        // Backup index.php if exists
        if (file_exists("$sitePath/index.php")) {
            rename("$sitePath/index.php", "$sitePath/index.php.suspended");
        }
        
        // Write suspension notice
        file_put_contents("$sitePath/index.php", $suspensionHtml);

        // Update nginx to serve static page
        $nginxConfig = $nginxService->generateSuspendedConfig($site);
        file_put_contents(
            "/etc/nginx/sites-available/{$site->subdomain}.conf",
            $nginxConfig
        );

        // Reload nginx
        exec('sudo nginx -s reload');

        // Stop PHP-FPM processes for this site if using dedicated pool
        if ($site->user->plan_id >= 3) { // Premium plans with dedicated pools
            exec("sudo systemctl stop php-fpm-{$site->id}");
        }

        // Send suspension email
        $this->sendSuspensionEmail($site);

        // Log suspension
        activity()
            ->performedOn($site)
            ->causedBy($site->user)
            ->withProperties([
                'reason' => $site->suspension_reason,
                'previous_status' => 'active'
            ])
            ->log('Site suspended for non-payment');
    }

    /**
     * Get suspension reason based on user status
     */
    protected function getSuspensionReason(User $user): string
    {
        if ($user->payment_status === 'failed') {
            return 'Payment failed';
        }
        
        if ($user->subscription_cancelled_at && $user->subscription_cancelled_at < now()->subDays(3)) {
            return 'Subscription cancelled';
        }
        
        if ($user->subscription_ends_at && $user->subscription_ends_at < now()) {
            return 'Subscription expired';
        }
        
        return 'Account suspended';
    }

    /**
     * Create suspension notice HTML
     */
    protected function createSuspensionNotice(Site $site): string
    {
        $contactEmail = config('app.support_email', 'support@example.com');
        $reason = $site->suspension_reason;
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Suspended</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #e53e3e;
            margin-bottom: 20px;
        }
        .reason {
            background-color: #fed7d7;
            color: #742a2a;
            padding: 10px 20px;
            border-radius: 4px;
            display: inline-block;
            margin: 20px 0;
        }
        .contact {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
        }
        a {
            color: #3182ce;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Site Suspended</h1>
        <p>This WordPress site has been temporarily suspended.</p>
        <div class="reason">{$reason}</div>
        <p>To reactivate your site, please log in to your account and update your billing information.</p>
        <div class="contact">
            <p>Need help? Contact us at:</p>
            <p><a href="mailto:{$contactEmail}">{$contactEmail}</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Handle auto-deletion of long-suspended sites
     */
    protected function handleAutoDeleteSites(): void
    {
        // Delete sites suspended for over 30 days
        $sitesToDelete = Site::where('status', 'suspended')
            ->where('suspended_at', '<', now()->subDays(30))
            ->whereHas('user', function ($query) {
                $query->where('plan_id', '!=', 1); // Don't auto-delete free tier
            })
            ->get();

        foreach ($sitesToDelete as $site) {
            // Dispatch deletion job
            DeleteExpiredSites::dispatch([$site->id])
                ->delay(now()->addMinutes(5));
            
            Log::info("Scheduled deletion for long-suspended site: {$site->subdomain}");
        }
    }

    /**
     * Send warning emails for upcoming suspensions
     */
    protected function sendSuspensionWarnings(): void
    {
        // Users with subscriptions ending in 3 days
        $usersToWarn = User::where('subscription_ends_at', '>', now())
            ->where('subscription_ends_at', '<=', now()->addDays(3))
            ->where('last_suspension_warning_at', '<', now()->subDay())
            ->orWhereNull('last_suspension_warning_at')
            ->get();

        foreach ($usersToWarn as $user) {
            $this->sendWarningEmail($user);
            $user->update(['last_suspension_warning_at' => now()]);
        }

        // Users with recent payment failures
        $failedPaymentUsers = User::where('payment_status', 'failed')
            ->where('payment_failed_at', '>', now()->subDays(6))
            ->where('last_payment_warning_at', '<', now()->subDay())
            ->orWhereNull('last_payment_warning_at')
            ->get();

        foreach ($failedPaymentUsers as $user) {
            $this->sendPaymentFailureWarning($user);
            $user->update(['last_payment_warning_at' => now()]);
        }
    }

    /**
     * Send suspension notification email
     */
    protected function sendSuspensionEmail(Site $site): void
    {
        $user = $site->user;
        
        Mail::to($user->email)->send(new \App\Mail\SiteSuspended($site));
        
        // Also send in-app notification if implemented
        $user->notify(new \App\Notifications\SiteSuspendedNotification($site));
    }

    /**
     * Send warning email for upcoming suspension
     */
    protected function sendWarningEmail(User $user): void
    {
        $daysRemaining = now()->diffInDays($user->subscription_ends_at);
        
        Mail::to($user->email)->send(new \App\Mail\SubscriptionExpiringSoon($user, $daysRemaining));
    }

    /**
     * Send payment failure warning
     */
    protected function sendPaymentFailureWarning(User $user): void
    {
        $daysSinceFailure = now()->diffInDays($user->payment_failed_at);
        $daysUntilSuspension = 7 - $daysSinceFailure;
        
        Mail::to($user->email)->send(new \App\Mail\PaymentFailedWarning($user, $daysUntilSuspension));
    }
}