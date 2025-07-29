<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteStatistic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class ResourceMonitorService
{
    private $storageService;
    
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Monitor and record site resource usage
     */
    public function recordSiteUsage(Site $site): array
    {
        try {
            // Get current usage data
            $usage = $this->getCurrentUsage($site);
            
            // Record in database
            SiteStatistic::create([
                'site_id' => $site->id,
                'recorded_at' => now(),
                'cpu_usage_percent' => $usage['cpu_percent'],
                'memory_usage_mb' => $usage['memory_mb'],
                'storage_usage_mb' => $usage['storage_mb'],
                'bandwidth_usage_mb' => $usage['bandwidth_mb'],
                'page_views' => $usage['page_views'],
                'unique_visitors' => $usage['unique_visitors']
            ]);

            // Cache the latest usage for quick access
            Cache::put("site_usage_{$site->id}", $usage, now()->addMinutes(5));

            return $usage;

        } catch (Exception $e) {
            Log::error("Failed to record usage for site {$site->subdomain}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if site is within resource limits
     */
    public function checkResourceLimits(Site $site): array
    {
        $plan = $site->plan;
        $usage = $this->getCachedOrCurrentUsage($site);
        
        // Default limits for guest users
        $limits = [
            'cpu_percent' => 10,
            'memory_mb' => 64,
            'storage_mb' => 100,
            'bandwidth_mb' => 1000,
            'page_views' => 1000
        ];

        // Override with plan limits if available
        if ($plan) {
            $limits = [
                'cpu_percent' => $plan->cpu_limit_percent ?? 25,
                'memory_mb' => $plan->memory_limit_mb ?? 256,
                'storage_mb' => $plan->storage_limit_mb ?? 1000,
                'bandwidth_mb' => $plan->bandwidth_limit_mb ?? 10000,
                'page_views' => $plan->page_views_limit ?? 50000
            ];
        }

        // Calculate violations
        $violations = [];
        $warnings = [];

        foreach ($limits as $resource => $limit) {
            $current = $usage[$resource] ?? 0;
            $percentage = $limit > 0 ? ($current / $limit) * 100 : 0;

            if ($percentage > 100) {
                $violations[] = [
                    'resource' => $resource,
                    'current' => $current,
                    'limit' => $limit,
                    'percentage' => round($percentage, 2)
                ];
            } elseif ($percentage > 80) {
                $warnings[] = [
                    'resource' => $resource,
                    'current' => $current,
                    'limit' => $limit,
                    'percentage' => round($percentage, 2)
                ];
            }
        }

        return [
            'within_limits' => empty($violations),
            'has_warnings' => !empty($warnings),
            'violations' => $violations,
            'warnings' => $warnings,
            'usage' => $usage,
            'limits' => $limits
        ];
    }

    /**
     * Get resource usage statistics for a time period
     */
    public function getUsageStatistics(Site $site, string $period = '24h'): array
    {
        try {
            $startDate = $this->getStartDateForPeriod($period);
            
            $stats = SiteStatistic::where('site_id', $site->id)
                ->where('recorded_at', '>=', $startDate)
                ->orderBy('recorded_at')
                ->get();

            if ($stats->isEmpty()) {
                return $this->getEmptyStats();
            }

            return [
                'period' => $period,
                'data_points' => $stats->count(),
                'cpu' => [
                    'avg' => round($stats->avg('cpu_usage_percent'), 2),
                    'max' => $stats->max('cpu_usage_percent'),
                    'min' => $stats->min('cpu_usage_percent')
                ],
                'memory' => [
                    'avg' => round($stats->avg('memory_usage_mb'), 2),
                    'max' => $stats->max('memory_usage_mb'),
                    'min' => $stats->min('memory_usage_mb')
                ],
                'storage' => [
                    'current' => $stats->last()->storage_usage_mb ?? 0,
                    'trend' => $this->calculateTrend($stats, 'storage_usage_mb')
                ],
                'bandwidth' => [
                    'total' => $stats->sum('bandwidth_usage_mb'),
                    'avg_daily' => round($stats->sum('bandwidth_usage_mb') / max(1, $this->getDaysInPeriod($period)), 2)
                ],
                'traffic' => [
                    'total_views' => $stats->sum('page_views'),
                    'unique_visitors' => $stats->sum('unique_visitors'),
                    'avg_daily_views' => round($stats->sum('page_views') / max(1, $this->getDaysInPeriod($period)), 2)
                ],
                'timeline' => $stats->map(function ($stat) {
                    return [
                        'timestamp' => $stat->recorded_at->toISOString(),
                        'cpu' => $stat->cpu_usage_percent,
                        'memory' => $stat->memory_usage_mb,
                        'storage' => $stat->storage_usage_mb,
                        'bandwidth' => $stat->bandwidth_usage_mb,
                        'views' => $stat->page_views
                    ];
                })->values()
            ];

        } catch (Exception $e) {
            Log::error("Failed to get usage statistics for site {$site->subdomain}: " . $e->getMessage());
            return $this->getEmptyStats();
        }
    }

    /**
     * Suspend site if over limits
     */
    public function enforceLimits(Site $site): bool
    {
        $check = $this->checkResourceLimits($site);
        
        if (!$check['within_limits']) {
            // Check if site should be suspended
            $suspendableViolations = collect($check['violations'])->filter(function ($violation) {
                return in_array($violation['resource'], ['cpu_percent', 'memory_mb', 'bandwidth_mb']);
            });

            if ($suspendableViolations->isNotEmpty()) {
                return $this->suspendSite($site, $suspendableViolations->toArray());
            }
        }

        return true;
    }

    /**
     * Get current resource usage
     */
    private function getCurrentUsage(Site $site): array
    {
        // Get storage usage
        $storageUsage = $this->storageService->calculateSiteStorageUsage($site);
        
        // Get system resources (CPU, Memory)
        $systemUsage = $this->getSystemResourceUsage($site);
        
        // Get traffic stats from logs or database
        $trafficStats = $this->getTrafficStats($site);
        
        // Get bandwidth usage
        $bandwidthUsage = $this->getBandwidthUsage($site);

        return [
            'cpu_percent' => $systemUsage['cpu'] ?? 0,
            'memory_mb' => $systemUsage['memory'] ?? 0,
            'storage_mb' => $storageUsage['total_mb'] ?? 0,
            'bandwidth_mb' => $bandwidthUsage,
            'page_views' => $trafficStats['views'] ?? 0,
            'unique_visitors' => $trafficStats['visitors'] ?? 0
        ];
    }

    /**
     * Get system resource usage (CPU, Memory)
     */
    private function getSystemResourceUsage(Site $site): array
    {
        try {
            // For shared hosting, we estimate based on site activity
            // In production, you might integrate with system monitoring tools
            
            $siteDir = base_path('../sites/site_' . $site->id);
            
            // Simple estimation based on recent file activity
            $recentActivity = 0;
            if (file_exists($siteDir)) {
                $files = glob($siteDir . '/wp-content/cache/*');
                $recentFiles = array_filter($files, function($file) {
                    return filemtime($file) > (time() - 300); // Last 5 minutes
                });
                $recentActivity = count($recentFiles);
            }

            // Estimate CPU usage (0-100%)
            $estimatedCpu = min(100, $recentActivity * 2);
            
            // Estimate memory usage (basic calculation)
            $baseMemory = 32; // Base WordPress memory
            $estimatedMemory = $baseMemory + ($recentActivity * 4);

            return [
                'cpu' => round($estimatedCpu, 2),
                'memory' => round($estimatedMemory, 2)
            ];

        } catch (Exception $e) {
            Log::warning("Could not get system usage for site {$site->subdomain}: " . $e->getMessage());
            return ['cpu' => 0, 'memory' => 32];
        }
    }

    /**
     * Get traffic statistics
     */
    private function getTrafficStats(Site $site): array
    {
        try {
            // Check if we have access logs
            $logFile = storage_path("sites/site_{$site->id}/logs/access.log");
            
            if (!file_exists($logFile)) {
                return ['views' => 0, 'visitors' => 0];
            }

            // Parse recent log entries (last hour)
            $cutoff = time() - 3600;
            $views = 0;
            $uniqueIps = [];

            $handle = fopen($logFile, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // Parse nginx log format
                    if (preg_match('/^(\S+) - - \[([^\]]+)\]/', $line, $matches)) {
                        $ip = $matches[1];
                        $timestamp = strtotime(str_replace(':', ' ', substr($matches[2], 0, 11)) . substr($matches[2], 11));
                        
                        if ($timestamp >= $cutoff) {
                            $views++;
                            $uniqueIps[$ip] = true;
                        }
                    }
                }
                fclose($handle);
            }

            return [
                'views' => $views,
                'visitors' => count($uniqueIps)
            ];

        } catch (Exception $e) {
            Log::warning("Could not parse traffic stats for site {$site->subdomain}: " . $e->getMessage());
            return ['views' => 0, 'visitors' => 0];
        }
    }

    /**
     * Get bandwidth usage
     */
    private function getBandwidthUsage(Site $site): float
    {
        try {
            // Calculate from log files or estimate
            $logFile = storage_path("sites/site_{$site->id}/logs/access.log");
            
            if (!file_exists($logFile)) {
                return 0;
            }

            $bandwidth = 0;
            $cutoff = time() - 3600; // Last hour

            $handle = fopen($logFile, 'r');
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    // Parse nginx log to get response size
                    if (preg_match('/^(\S+).*\[([^\]]+)\].*" (\d+) (\d+)/', $line, $matches)) {
                        $timestamp = strtotime(str_replace(':', ' ', substr($matches[2], 0, 11)) . substr($matches[2], 11));
                        $responseSize = intval($matches[4]);
                        
                        if ($timestamp >= $cutoff && $responseSize > 0) {
                            $bandwidth += $responseSize;
                        }
                    }
                }
                fclose($handle);
            }

            return round($bandwidth / 1024 / 1024, 2); // Convert to MB

        } catch (Exception $e) {
            Log::warning("Could not calculate bandwidth for site {$site->subdomain}: " . $e->getMessage());
            return 0;
        }
    }

    private function getCachedOrCurrentUsage(Site $site): array
    {
        return Cache::get("site_usage_{$site->id}", function() use ($site) {
            return $this->getCurrentUsage($site);
        });
    }

    private function suspendSite(Site $site, array $violations): bool
    {
        try {
            $site->update(['status' => 'suspended']);
            
            Log::warning("Site {$site->subdomain} suspended for resource violations", [
                'violations' => $violations
            ]);

            // TODO: Send notification email to user
            
            return true;
        } catch (Exception $e) {
            Log::error("Failed to suspend site {$site->subdomain}: " . $e->getMessage());
            return false;
        }
    }

    private function getStartDateForPeriod(string $period): Carbon
    {
        return match($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay()
        };
    }

    private function getDaysInPeriod(string $period): int
    {
        return match($period) {
            '1h' => 1,
            '24h' => 1,
            '7d' => 7,
            '30d' => 30,
            default => 1
        };
    }

    private function calculateTrend(array $stats, string $field): string
    {
        if (count($stats) < 2) {
            return 'stable';
        }

        $first = $stats[0]->{$field};
        $last = $stats[count($stats) - 1]->{$field};
        
        if ($last > $first * 1.1) return 'increasing';
        if ($last < $first * 0.9) return 'decreasing';
        
        return 'stable';
    }

    private function getEmptyStats(): array
    {
        return [
            'period' => '24h',
            'data_points' => 0,
            'cpu' => ['avg' => 0, 'max' => 0, 'min' => 0],
            'memory' => ['avg' => 0, 'max' => 0, 'min' => 0],
            'storage' => ['current' => 0, 'trend' => 'stable'],
            'bandwidth' => ['total' => 0, 'avg_daily' => 0],
            'traffic' => ['total_views' => 0, 'unique_visitors' => 0, 'avg_daily_views' => 0],
            'timeline' => []
        ];
    }
}