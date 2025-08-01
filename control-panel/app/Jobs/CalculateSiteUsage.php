<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\ResourceMonitorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class CalculateSiteUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    private $resourceMonitorService;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->resourceMonitorService = app(ResourceMonitorService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting site usage calculation job');

        try {
            // Get all active sites
            $sites = Site::whereIn('status', ['active', 'suspended'])
                ->with(['plan', 'user'])
                ->get();

            if ($sites->isEmpty()) {
                Log::info('No active sites found for usage calculation');
                return;
            }

            Log::info("Calculating usage for {$sites->count()} sites");

            $successCount = 0;
            $errorCount = 0;
            $violationCount = 0;

            foreach ($sites as $site) {
                try {
                    // Record current usage
                    $usage = $this->resourceMonitorService->recordSiteUsage($site);
                    
                    if (!empty($usage)) {
                        $successCount++;
                        
                        // Check for resource limit violations
                        $limitCheck = $this->resourceMonitorService->checkResourceLimits($site);
                        
                        if (!$limitCheck['within_limits']) {
                            $violationCount++;
                            $this->handleResourceViolation($site, $limitCheck);
                        }
                        
                        // Log high usage warnings
                        if ($limitCheck['has_warnings']) {
                            $this->logUsageWarnings($site, $limitCheck['warnings']);
                        }
                    }

                } catch (Exception $e) {
                    $errorCount++;
                    Log::error("Failed to calculate usage for site {$site->subdomain}: " . $e->getMessage());
                }

                // Small delay to prevent overwhelming the system
                usleep(100000); // 0.1 seconds
            }

            Log::info("Site usage calculation completed", [
                'total_sites' => $sites->count(),
                'successful_calculations' => $successCount,
                'errors' => $errorCount,
                'violations_found' => $violationCount
            ]);

            // Clean up old statistics
            $this->cleanupOldStatistics();

        } catch (Exception $e) {
            Log::error('Site usage calculation job failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle resource limit violations
     */
    private function handleResourceViolation(Site $site, array $limitCheck): void
    {
        Log::warning("Resource violations detected for site: {$site->subdomain}", [
            'site_id' => $site->id,
            'violations' => $limitCheck['violations']
        ]);

        // Check if site should be suspended
        $criticalViolations = collect($limitCheck['violations'])->filter(function ($violation) {
            // Define which violations are critical enough for suspension
            return in_array($violation['resource'], ['cpu_percent', 'memory_mb']) 
                && $violation['percentage'] > 150; // 50% over limit
        });

        if ($criticalViolations->isNotEmpty()) {
            try {
                // Enforce limits (may suspend site)
                $this->resourceMonitorService->enforceLimits($site);
                
                Log::warning("Site {$site->subdomain} may have been suspended due to critical violations", [
                    'critical_violations' => $criticalViolations->toArray()
                ]);

                // TODO: Send notification to user
                $this->notifyUserOfViolation($site, $criticalViolations->toArray());

            } catch (Exception $e) {
                Log::error("Failed to enforce limits for site {$site->subdomain}: " . $e->getMessage());
            }
        }
    }

    /**
     * Log usage warnings
     */
    private function logUsageWarnings(Site $site, array $warnings): void
    {
        Log::info("Usage warnings for site: {$site->subdomain}", [
            'site_id' => $site->id,
            'warnings' => $warnings
        ]);

        // Check if we should notify user about approaching limits
        $highWarnings = collect($warnings)->filter(function ($warning) {
            return $warning['percentage'] > 90; // Over 90% of limit
        });

        if ($highWarnings->isNotEmpty()) {
            // TODO: Send warning notification to user
            $this->notifyUserOfWarning($site, $highWarnings->toArray());
        }
    }

    /**
     * Clean up old statistics (keep last 30 days)
     */
    private function cleanupOldStatistics(): void
    {
        try {
            $cutoffDate = now()->subDays(30);
            
            $deletedCount = \App\Models\SiteStatistic::where('recorded_at', '<', $cutoffDate)
                ->delete();

            if ($deletedCount > 0) {
                Log::info("Cleaned up {$deletedCount} old usage statistics");
            }

        } catch (Exception $e) {
            Log::error('Failed to cleanup old statistics: ' . $e->getMessage());
        }
    }

    /**
     * Notify user of resource violation
     */
    private function notifyUserOfViolation(Site $site, array $violations): void
    {
        if (!$site->user || !$site->user->email) {
            return;
        }

        // TODO: Implement email notification
        Log::info("Should send violation notification", [
            'user_email' => $site->user->email,
            'site_subdomain' => $site->subdomain,
            'violations' => $violations
        ]);

        /*
        // Example implementation:
        Mail::to($site->user->email)->send(new ResourceViolationMail($site, $violations));
        */
    }

    /**
     * Notify user of approaching resource limits
     */
    private function notifyUserOfWarning(Site $site, array $warnings): void
    {
        if (!$site->user || !$site->user->email) {
            return;
        }

        // Check if we already sent a warning recently (avoid spam)
        $lastWarningKey = "usage_warning_sent_{$site->id}";
        if (cache()->has($lastWarningKey)) {
            return;
        }

        // Set cache to prevent sending another warning for 24 hours
        cache()->put($lastWarningKey, true, now()->addHours(24));

        // TODO: Implement email notification
        Log::info("Should send usage warning notification", [
            'user_email' => $site->user->email,
            'site_subdomain' => $site->subdomain,
            'warnings' => $warnings
        ]);

        /*
        // Example implementation:
        Mail::to($site->user->email)->send(new UsageWarningMail($site, $warnings));
        */
    }

    /**
     * Generate usage summary for admin dashboard
     */
    private function generateUsageSummary(array $allUsageData): void
    {
        try {
            $summary = [
                'timestamp' => now(),
                'total_sites' => count($allUsageData),
                'avg_cpu_usage' => collect($allUsageData)->avg('cpu_percent'),
                'avg_memory_usage' => collect($allUsageData)->avg('memory_mb'),
                'total_storage_usage' => collect($allUsageData)->sum('storage_mb'),
                'total_bandwidth_usage' => collect($allUsageData)->sum('bandwidth_mb'),
                'sites_over_80_percent' => collect($allUsageData)->filter(function ($usage) {
                    return $usage['cpu_percent'] > 80 || $usage['memory_mb'] > 80;
                })->count()
            ];

            // Store summary in cache for admin dashboard
            cache()->put('system_usage_summary', $summary, now()->addMinutes(15));

            Log::info('Generated system usage summary', $summary);

        } catch (Exception $e) {
            Log::error('Failed to generate usage summary: ' . $e->getMessage());
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error('CalculateSiteUsage job failed completely', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // TODO: Send alert to administrators
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHour();
    }
}