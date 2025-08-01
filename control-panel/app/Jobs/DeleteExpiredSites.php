<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\WordPressProvisioningService;
use App\Services\StorageService;
use App\Services\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class DeleteExpiredSites implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    private $storageService;
    private $provisioningService;
    private $billingService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->storageService = app(StorageService::class);
        $this->provisioningService = app(WordPressProvisioningService::class);
        $this->billingService = app(BillingService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting expired sites cleanup job');

        try {
            // Get expired sites with grace period
            $gracePeriodHours = config('app.site_cleanup_grace_period', 24);
            $cutoffTime = now()->subHours($gracePeriodHours);

            $expiredSites = Site::where('expires_at', '<', $cutoffTime)
                ->whereIn('status', ['active', 'suspended'])
                ->with(['user', 'plan'])
                ->get();

            if ($expiredSites->isEmpty()) {
                Log::info('No expired sites found for cleanup');
                return;
            }

            Log::info("Found {$expiredSites->count()} expired sites for cleanup");

            $successCount = 0;
            $errorCount = 0;

            foreach ($expiredSites as $site) {
                try {
                    $this->deleteSite($site);
                    $successCount++;
                } catch (Exception $e) {
                    $errorCount++;
                    Log::error("Failed to delete expired site {$site->subdomain}: " . $e->getMessage());
                }
            }

            Log::info("Expired sites cleanup completed", [
                'total_processed' => $expiredSites->count(),
                'successful_deletions' => $successCount,
                'errors' => $errorCount
            ]);

            // Clean up orphaned files
            $this->cleanupOrphanedFiles();

        } catch (Exception $e) {
            Log::error('Expired sites cleanup job failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a single site
     */
    private function deleteSite(Site $site): void
    {
        Log::info("Deleting expired site: {$site->subdomain}", [
            'site_id' => $site->id,
            'user_id' => $site->user_id,
            'expired_at' => $site->expires_at,
            'plan' => $site->plan?->name
        ]);

        // 1. Cancel any active subscriptions
        if ($site->stripe_subscription_id && $site->subscription_status !== 'canceled') {
            try {
                $this->billingService->cancelSubscription($site, true);
                Log::info("Canceled subscription for expired site: {$site->subdomain}");
            } catch (Exception $e) {
                Log::warning("Failed to cancel subscription for expired site {$site->subdomain}: " . $e->getMessage());
                // Continue with deletion even if subscription cancellation fails
            }
        }

        // 2. Delete WordPress installation and database
        try {
            $deleted = $this->provisioningService->deleteSite($site);
            if (!$deleted) {
                Log::warning("WordPress deletion may have failed for site: {$site->subdomain}");
            }
        } catch (Exception $e) {
            Log::warning("Error deleting WordPress installation for {$site->subdomain}: " . $e->getMessage());
            // Continue with cleanup
        }

        // 3. Delete storage files
        try {
            $this->storageService->deleteSiteStorage($site);
            Log::info("Deleted storage for site: {$site->subdomain}");
        } catch (Exception $e) {
            Log::warning("Error deleting storage for {$site->subdomain}: " . $e->getMessage());
            // Continue with cleanup
        }

        // 4. Send notification to user (if registered user)
        if ($site->user_id && $site->user) {
            try {
                $this->sendDeletionNotification($site);
            } catch (Exception $e) {
                Log::warning("Failed to send deletion notification for {$site->subdomain}: " . $e->getMessage());
            }
        }

        // 5. Update site status before deletion (for audit trail)
        $site->update([
            'status' => 'deleted',
            'deleted_at' => now()
        ]);

        // 6. Delete site record from database
        $site->delete();

        Log::info("Successfully deleted expired site: {$site->subdomain}");
    }

    /**
     * Clean up orphaned files that may remain
     */
    private function cleanupOrphanedFiles(): void
    {
        try {
            Log::info('Starting orphaned files cleanup');

            $sitesPath = base_path('../sites');
            $storagePath = storage_path('sites');

            // Get all site directories
            $siteDirs = [];
            
            if (is_dir($sitesPath)) {
                $siteDirs = array_merge($siteDirs, glob($sitesPath . '/site_*', GLOB_ONLYDIR));
            }
            
            if (is_dir($storagePath)) {
                $siteDirs = array_merge($siteDirs, glob($storagePath . '/site_*', GLOB_ONLYDIR));
            }

            $orphanedCount = 0;

            foreach ($siteDirs as $dir) {
                // Extract site ID from directory name
                if (preg_match('/site_(\d+)$/', $dir, $matches)) {
                    $siteId = $matches[1];
                    
                    // Check if site exists in database
                    $siteExists = Site::where('id', $siteId)->exists();
                    
                    if (!$siteExists) {
                        // This is an orphaned directory
                        try {
                            $this->deleteDirectory($dir);
                            $orphanedCount++;
                            Log::info("Deleted orphaned directory: {$dir}");
                        } catch (Exception $e) {
                            Log::warning("Failed to delete orphaned directory {$dir}: " . $e->getMessage());
                        }
                    }
                }
            }

            if ($orphanedCount > 0) {
                Log::info("Cleaned up {$orphanedCount} orphaned directories");
            } else {
                Log::info("No orphaned directories found");
            }

        } catch (Exception $e) {
            Log::error('Orphaned files cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Send deletion notification to user
     */
    private function sendDeletionNotification(Site $site): void
    {
        if (!$site->user || !$site->user->email) {
            return;
        }

        // TODO: Implement email notification
        // For now, just log the notification
        Log::info("Should send deletion notification", [
            'user_email' => $site->user->email,
            'site_subdomain' => $site->subdomain,
            'site_title' => $site->title
        ]);

        /*
        // Example implementation:
        Mail::to($site->user->email)->send(new SiteDeletedMail($site));
        */
    }

    /**
     * Recursively delete a directory
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('DeleteExpiredSites job failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // TODO: Send alert to administrators
        // TODO: Add to failed jobs monitoring
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addMinutes(30);
    }
}