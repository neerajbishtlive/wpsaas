<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use App\Models\Plan;
use App\Models\SiteStatistic;
use App\Services\StorageService;
use App\Services\ResourceMonitorService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class AdminController extends Controller
{
    private $storageService;
    private $resourceMonitorService;
    private $billingService;

    public function __construct(
        StorageService $storageService,
        ResourceMonitorService $resourceMonitorService,
        BillingService $billingService
    ) {
        $this->middleware('auth');
        $this->middleware('admin'); // Custom middleware to check admin role
        $this->storageService = $storageService;
        $this->resourceMonitorService = $resourceMonitorService;
        $this->billingService = $billingService;
    }

    /**
     * Admin dashboard overview
     */
    public function dashboard()
    {
        // System statistics
        $stats = [
            'total_sites' => Site::count(),
            'active_sites' => Site::where('status', 'active')->count(),
            'suspended_sites' => Site::where('status', 'suspended')->count(),
            'expired_sites' => Site::where('expires_at', '<', now())->count(),
            'total_users' => User::count(),
            'paid_users' => User::whereHas('sites', function($q) {
                $q->whereNotNull('stripe_subscription_id');
            })->count(),
            'guest_sites' => Site::whereNull('user_id')->count(),
            'total_revenue' => $this->calculateTotalRevenue(),
            'monthly_revenue' => $this->calculateMonthlyRevenue()
        ];

        // Recent activity
        $recentSites = Site::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentUsers = User::orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // System health
        $systemHealth = $this->getSystemHealth();

        // Resource usage trends
        $usageTrends = $this->getResourceUsageTrends();

        return view('admin.dashboard', compact(
            'stats', 
            'recentSites', 
            'recentUsers', 
            'systemHealth',
            'usageTrends'
        ));
    }

    /**
     * Manage all sites
     */
    public function sites(Request $request)
    {
        $query = Site::with(['user', 'plan']);

        // Filtering
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan')) {
            $query->where('plan_id', $request->plan);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subdomain', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQ) use ($search) {
                      $userQ->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $sites = $query->paginate(20);
        $plans = Plan::all();

        return view('admin.sites.index', compact('sites', 'plans'));
    }

    /**
     * View site details
     */
    public function siteDetails(Site $site)
    {
        $site->load(['user', 'plan', 'statistics']);

        // Get detailed usage
        $usage = $this->resourceMonitorService->getUsageStatistics($site, '7d');
        $storage = $this->storageService->calculateSiteStorageUsage($site);
        $limits = $this->resourceMonitorService->checkResourceLimits($site);

        // Get billing info
        $billingInfo = null;
        if ($site->stripe_subscription_id) {
            $billingInfo = [
                'subscription_id' => $site->stripe_subscription_id,
                'status' => $site->subscription_status,
                'next_billing' => $site->expires_at
            ];
        }

        return view('admin.sites.show', compact(
            'site', 
            'usage', 
            'storage', 
            'limits', 
            'billingInfo'
        ));
    }

    /**
     * Suspend/unsuspend site
     */
    public function toggleSiteStatus(Site $site)
    {
        try {
            $newStatus = $site->status === 'suspended' ? 'active' : 'suspended';
            $site->update(['status' => $newStatus]);

            Log::info("Admin changed site status", [
                'site_id' => $site->id,
                'subdomain' => $site->subdomain,
                'old_status' => $site->status,
                'new_status' => $newStatus,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Site {$newStatus} successfully.",
                'new_status' => $newStatus
            ]);

        } catch (Exception $e) {
            Log::error("Failed to change site status: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change site status.'
            ], 500);
        }
    }

    /**
     * Delete site (admin override)
     */
    public function deleteSite(Site $site)
    {
        try {
            // Cancel subscription if exists
            if ($site->stripe_subscription_id) {
                $this->billingService->cancelSubscription($site, true);
            }

            // Delete storage
            $this->storageService->deleteSiteStorage($site);

            // Log before deletion
            Log::warning("Admin deleted site", [
                'site_id' => $site->id,
                'subdomain' => $site->subdomain,
                'user_id' => $site->user_id,
                'admin_id' => auth()->id()
            ]);

            $site->delete();

            return response()->json([
                'success' => true,
                'message' => 'Site deleted successfully.'
            ]);

        } catch (Exception $e) {
            Log::error("Admin site deletion failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete site.'
            ], 500);
        }
    }

    /**
     * Manage users
     */
    public function users(Request $request)
    {
        $query = User::withCount(['sites']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * View user details
     */
    public function userDetails(User $user)
    {
        $user->load(['sites.plan']);
        
        $stats = [
            'total_sites' => $user->sites->count(),
            'active_sites' => $user->sites->where('status', 'active')->count(),
            'total_spent' => $this->calculateUserRevenue($user)
        ];

        return view('admin.users.show', compact('user', 'stats'));
    }

    /**
     * Manage plans
     */
    public function plans()
    {
        $plans = Plan::orderBy('price')->get();
        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Create new plan
     */
    public function createPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly',
            'storage_limit_mb' => 'required|integer|min:100',
            'bandwidth_limit_mb' => 'required|integer|min:1000',
            'memory_limit_mb' => 'required|integer|min:64',
            'cpu_limit_percent' => 'required|integer|min:5|max:100',
            'max_sites' => 'required|integer|min:1',
            'is_active' => 'boolean',
            'is_public' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan = Plan::create($request->all());

            Log::info("Admin created new plan", [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully.',
                'plan' => $plan
            ]);

        } catch (Exception $e) {
            Log::error("Failed to create plan: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create plan.'
            ], 500);
        }
    }

    /**
     * Update plan
     */
    public function updatePlan(Request $request, Plan $plan)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'storage_limit_mb' => 'required|integer|min:100',
            'bandwidth_limit_mb' => 'required|integer|min:1000',
            'is_active' => 'boolean',
            'is_public' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $plan->update($request->all());

            Log::info("Admin updated plan", [
                'plan_id' => $plan->id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plan.'
            ], 500);
        }
    }

    /**
     * System settings
     */
    public function settings()
    {
        $settings = [
            'guest_site_duration' => config('app.guest_site_duration', 24),
            'registered_site_duration' => config('app.registered_site_duration', 7),
            'max_sites_per_user' => config('app.max_sites_per_user', 10),
            'default_storage_limit' => config('app.default_storage_limit', 100),
            'maintenance_mode' => config('app.maintenance_mode', false)
        ];

        return view('admin.settings', compact('settings'));
    }

    /**
     * System health check
     */
    public function systemHealth()
    {
        $health = $this->getSystemHealth();
        return response()->json($health);
    }

    /**
     * Get system analytics
     */
    public function analytics(Request $request)
    {
        $period = $request->get('period', '7d');
        
        $analytics = [
            'sites_created' => $this->getSitesCreatedTrend($period),
            'revenue_trend' => $this->getRevenueTrend($period),
            'resource_usage' => $this->getSystemResourceUsage($period),
            'popular_plans' => $this->getPopularPlans(),
            'user_activity' => $this->getUserActivityTrend($period)
        ];

        return response()->json($analytics);
    }

    /**
     * Export data
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'sites');
        
        try {
            switch ($type) {
                case 'sites':
                    return $this->exportSites();
                case 'users':
                    return $this->exportUsers();
                case 'revenue':
                    return $this->exportRevenue();
                default:
                    throw new Exception('Invalid export type');
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Private helper methods
     */
    private function calculateTotalRevenue(): float
    {
        // This would integrate with your billing system
        // For now, estimate based on active paid sites
        $paidSites = Site::whereNotNull('stripe_subscription_id')
            ->where('subscription_status', 'active')
            ->with('plan')
            ->get();

        return $paidSites->sum(function($site) {
            return $site->plan ? $site->plan->price : 0;
        });
    }

    private function calculateMonthlyRevenue(): float
    {
        // Calculate current month revenue
        return $this->calculateTotalRevenue(); // Simplified for now
    }

    private function calculateUserRevenue(User $user): float
    {
        // Calculate total spent by user
        return $user->sites()
            ->whereNotNull('stripe_subscription_id')
            ->with('plan')
            ->get()
            ->sum(function($site) {
                return $site->plan ? $site->plan->price : 0;
            });
    }

    private function getSystemHealth(): array
    {
        return [
            'disk_usage' => $this->getDiskUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'database_status' => $this->getDatabaseStatus(),
            'queue_status' => $this->getQueueStatus(),
            'cache_status' => $this->getCacheStatus()
        ];
    }

    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'percentage' => round(($used / $total) * 100, 2)
        ];
    }

    private function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit')
        ];
    }

    private function getDatabaseStatus(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'connected', 'message' => 'Database connection healthy'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getQueueStatus(): array
    {
        // Check queue jobs
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            return [
                'status' => $failedJobs > 10 ? 'warning' : 'healthy',
                'failed_jobs' => $failedJobs
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getCacheStatus(): array
    {
        try {
            cache()->put('admin_health_check', 'ok', 60);
            $test = cache()->get('admin_health_check');
            
            return [
                'status' => $test === 'ok' ? 'healthy' : 'error',
                'message' => $test === 'ok' ? 'Cache working' : 'Cache test failed'
            ];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function getResourceUsageTrends(): array
    {
        // Get resource usage trends for the last 24 hours
        $stats = SiteStatistic::where('recorded_at', '>=', now()->subDay())
            ->selectRaw('
                DATE_FORMAT(recorded_at, "%H:00") as hour,
                AVG(cpu_usage_percent) as avg_cpu,
                AVG(memory_usage_mb) as avg_memory,
                SUM(bandwidth_usage_mb) as total_bandwidth
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return $stats->toArray();
    }

    private function getSitesCreatedTrend(string $period): array
    {
        $days = $period === '30d' ? 30 : 7;
        
        return Site::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getRevenueTrend(string $period): array
    {
        // Simplified revenue trend
        return [];
    }

    private function getSystemResourceUsage(string $period): array
    {
        // System-wide resource usage
        return [];
    }

    private function getPopularPlans(): array
    {
        return Site::whereNotNull('plan_id')
            ->with('plan')
            ->get()
            ->groupBy('plan.name')
            ->map(function($sites, $planName) {
                return [
                    'plan' => $planName,
                    'count' => $sites->count()
                ];
            })
            ->values()
            ->toArray();
    }

    private function getUserActivityTrend(string $period): array
    {
        $days = $period === '30d' ? 30 : 7;
        
        return User::where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function exportSites()
    {
        // CSV export of sites
        $sites = Site::with(['user', 'plan'])->get();
        // Implementation for CSV export
        return response()->json(['message' => 'Export functionality to be implemented']);
    }

    private function exportUsers()
    {
        // CSV export of users
        return response()->json(['message' => 'Export functionality to be implemented']);
    }

    private function exportRevenue()
    {
        // CSV export of revenue data
        return response()->json(['message' => 'Export functionality to be implemented']);
    }
}