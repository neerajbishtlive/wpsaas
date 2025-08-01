<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Plan;
use App\Services\WordPressProvisioningService;
use App\Services\StorageService;
use App\Services\ResourceMonitorService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SiteController extends Controller
{
    private $provisioningService;
    private $storageService;
    private $resourceMonitorService;
    private $billingService;

    public function __construct(
        WordPressProvisioningService $provisioningService,
        StorageService $storageService,
        ResourceMonitorService $resourceMonitorService,
        BillingService $billingService
    ) {
        $this->middleware('auth')->except(['store']); // Allow guest site creation
        $this->provisioningService = $provisioningService;
        $this->storageService = $storageService;
        $this->resourceMonitorService = $resourceMonitorService;
        $this->billingService = $billingService;
    }

    /**
     * Display user's sites
     */
    public function index()
    {
        $user = Auth::user();
        
        $sites = Site::where('user_id', $user->id)
            ->with(['plan'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add real-time usage data
        $sites->each(function ($site) {
            $site->usage = $this->resourceMonitorService->checkResourceLimits($site);
            $site->storage = $this->storageService->checkStorageLimit($site);
        });

        return view('sites.index', compact('sites'));
    }

    /**
     * Show site details
     */
    public function show(Site $site)
    {
        $this->authorize('view', $site);

        // Get detailed usage statistics
        $usage24h = $this->resourceMonitorService->getUsageStatistics($site, '24h');
        $usage7d = $this->resourceMonitorService->getUsageStatistics($site, '7d');
        $storageDetails = $this->storageService->calculateSiteStorageUsage($site);
        $resourceLimits = $this->resourceMonitorService->checkResourceLimits($site);

        // Get billing information if site has subscription
        $billingHistory = [];
        if ($site->user && $site->stripe_subscription_id) {
            $billingHistory = $this->billingService->getBillingHistory($site->user, 5);
        }

        return view('sites.show', compact(
            'site', 
            'usage24h', 
            'usage7d', 
            'storageDetails', 
            'resourceLimits',
            'billingHistory'
        ));
    }

    /**
     * Show create site form
     */
    public function create()
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        return view('sites.create', compact('plans'));
    }

    /**
     * Create new site (supports both guest and authenticated users)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9-]+$/',
                'unique:sites,subdomain'
            ],
            'title' => 'required|string|max:255',
            'admin_username' => 'required|string|min:4|max:20|alpha_dash',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
            'plan_id' => 'nullable|exists:plans,id',
            'payment_method_id' => 'nullable|string' // For paid plans
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $planId = $request->plan_id;
            
            // Check if user can create more sites
            if ($user) {
                $existingSitesCount = Site::where('user_id', $user->id)->count();
                $maxSites = $user->isPaid() ? 10 : 3; // Example limits
                
                if ($existingSitesCount >= $maxSites) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have reached the maximum number of sites for your plan.'
                    ], 403);
                }
            }

            // Set expiration based on user type
            $expiresAt = $user ? 
                now()->addDays(7) : // Registered user: 7 days
                now()->addHours(24); // Guest: 24 hours

            // Create site record
            $site = Site::create([
                'user_id' => $user?->id,
                'subdomain' => $request->subdomain,
                'title' => $request->title,
                'status' => 'provisioning',
                'plan_id' => $planId,
                'expires_at' => $expiresAt,
                'admin_username' => $request->admin_username,
                'admin_email' => $request->admin_email
            ]);

            // Create storage directories
            $this->storageService->createSiteStorage($site);

            // Provision WordPress
            $provisionResult = $this->provisioningService->provisionSite($site, [
                'admin_username' => $request->admin_username,
                'admin_password' => $request->admin_password,
                'admin_email' => $request->admin_email,
                'site_title' => $request->title
            ]);

            if (!$provisionResult['success']) {
                // Cleanup on failure
                $site->delete();
                $this->storageService->deleteSiteStorage($site);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to provision WordPress site: ' . $provisionResult['message']
                ], 500);
            }

            // Handle paid plan subscription
            if ($planId && $request->payment_method_id) {
                $plan = Plan::find($planId);
                $subscriptionResult = $this->billingService->createSubscription(
                    $site, 
                    $plan, 
                    $request->payment_method_id
                );

                if (!$subscriptionResult['success']) {
                    Log::warning("Subscription creation failed for site {$site->id}: " . $subscriptionResult['error']);
                    // Don't fail the site creation, but mark as unpaid
                    $site->update(['subscription_status' => 'payment_failed']);
                }
            }

            $site->update(['status' => 'active']);

            Log::info("Site created successfully", [
                'subdomain' => $site->subdomain,
                'user_id' => $user?->id,
                'plan_id' => $planId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Site created successfully!',
                'site' => [
                    'id' => $site->id,
                    'subdomain' => $site->subdomain,
                    'url' => "https://{$site->subdomain}." . config('app.domain'),
                    'admin_url' => "https://{$site->subdomain}." . config('app.domain') . "/wp-admin",
                    'expires_at' => $site->expires_at->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Site creation failed: " . $e->getMessage(), [
                'subdomain' => $request->subdomain,
                'user_id' => $user?->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Site creation failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Upgrade site plan
     */
    public function upgrade(Request $request, Site $site)
    {
        $this->authorize('update', $site);

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $newPlan = Plan::find($request->plan_id);
            
            // If site has no subscription, create one
            if (!$site->stripe_subscription_id) {
                $result = $this->billingService->createSubscription(
                    $site, 
                    $newPlan, 
                    $request->payment_method_id
                );
            } else {
                // Change existing subscription
                $result = $this->billingService->changePlan($site, $newPlan);
            }

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Plan upgraded successfully!'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Upgrade failed'
                ], 400);
            }

        } catch (Exception $e) {
            Log::error("Plan upgrade failed for site {$site->id}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Upgrade failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Suspend site
     */
    public function suspend(Site $site)
    {
        $this->authorize('update', $site);

        try {
            $site->update(['status' => 'suspended']);
            
            Log::info("Site suspended: {$site->subdomain}");
            
            return response()->json([
                'success' => true,
                'message' => 'Site suspended successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend site.'
            ], 500);
        }
    }

    /**
     * Restore suspended site
     */
    public function restore(Site $site)
    {
        $this->authorize('update', $site);

        try {
            // Check if site subscription is still valid
            $canRestore = true;
            
            if ($site->stripe_subscription_id) {
                // Check subscription status
                $limits = $this->resourceMonitorService->checkResourceLimits($site);
                $canRestore = $limits['within_limits'];
            }

            if (!$canRestore) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot restore site due to resource violations or billing issues.'
                ], 400);
            }

            $site->update(['status' => 'active']);
            
            Log::info("Site restored: {$site->subdomain}");
            
            return response()->json([
                'success' => true,
                'message' => 'Site restored successfully.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore site.'
            ], 500);
        }
    }

    /**
     * Delete site
     */
    public function destroy(Site $site)
    {
        $this->authorize('delete', $site);

        try {
            // Cancel subscription if exists
            if ($site->stripe_subscription_id) {
                $this->billingService->cancelSubscription($site, true);
            }

            // Delete storage
            $this->storageService->deleteSiteStorage($site);

            // Delete WordPress installation
            $this->provisioningService->deleteSite($site);

            // Delete site record
            $site->delete();

            Log::info("Site deleted: {$site->subdomain}");

            return response()->json([
                'success' => true,
                'message' => 'Site deleted successfully.'
            ]);

        } catch (Exception $e) {
            Log::error("Site deletion failed for {$site->subdomain}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete site.'
            ], 500);
        }
    }

    /**
     * Extend site expiration (for registered users)
     */
    public function extend(Site $site)
    {
        $this->authorize('update', $site);

        if (!$site->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only registered users can extend site expiration.'
            ], 403);
        }

        try {
            // Extend by 7 days for registered users
            $site->update([
                'expires_at' => $site->expires_at->addDays(7)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Site expiration extended by 7 days.',
                'new_expiration' => $site->expires_at->toISOString()
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to extend site expiration.'
            ], 500);
        }
    }

    /**
     * Get site usage API
     */
    public function usage(Site $site, Request $request)
    {
        $this->authorize('view', $site);

        $period = $request->get('period', '24h');
        $usage = $this->resourceMonitorService->getUsageStatistics($site, $period);
        $storage = $this->storageService->calculateSiteStorageUsage($site);
        $limits = $this->resourceMonitorService->checkResourceLimits($site);

        return response()->json([
            'usage' => $usage,
            'storage' => $storage,
            'limits' => $limits
        ]);
    }
}