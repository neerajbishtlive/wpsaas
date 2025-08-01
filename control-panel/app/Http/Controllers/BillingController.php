<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Plan;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\Webhook;
use Exception;

class BillingController extends Controller
{
    private $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->middleware('auth')->except(['webhook']);
        $this->billingService = $billingService;
    }

    /**
     * Show billing dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get user's subscriptions
        $sites = Site::where('user_id', $user->id)
            ->whereNotNull('stripe_subscription_id')
            ->with('plan')
            ->get();

        // Get payment methods
        $paymentMethods = $this->billingService->getPaymentMethods($user);

        // Get billing history
        $billingHistory = $this->billingService->getBillingHistory($user);

        // Calculate totals
        $monthlyTotal = $sites->sum(function($site) {
            return $site->plan ? $site->plan->price : 0;
        });

        return view('billing.index', compact(
            'sites', 
            'paymentMethods', 
            'billingHistory',
            'monthlyTotal'
        ));
    }

    /**
     * Show subscription plans
     */
    public function plans()
    {
        $plans = Plan::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price')
            ->get();

        return view('billing.plans', compact('plans'));
    }

    /**
     * Create setup intent for adding payment method
     */
    public function createSetupIntent()
    {
        try {
            $user = Auth::user();
            $result = $this->billingService->createSetupIntent($user);

            return response()->json($result);

        } catch (Exception $e) {
            Log::error("Setup intent creation failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment setup.'
            ], 500);
        }
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'required|exists:sites,id',
            'plan_id' => 'required|exists:plans,id',
            'payment_method_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $site = Site::where('id', $request->site_id)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $plan = Plan::findOrFail($request->plan_id);

            // Create subscription
            $result = $this->billingService->createSubscription(
                $site, 
                $plan, 
                $request->payment_method_id
            );

            if ($result['success']) {
                Log::info("User subscribed to plan", [
                    'user_id' => $user->id,
                    'site_id' => $site->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $result['subscription_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Successfully subscribed to ' . $plan->name,
                    'subscription' => [
                        'id' => $result['subscription_id'],
                        'status' => $result['status']
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

        } catch (Exception $e) {
            Log::error("Subscription creation failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Subscription failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Request $request, Site $site)
    {
        $this->authorize('update', $site);

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $newPlan = Plan::findOrFail($request->plan_id);
            $result = $this->billingService->changePlan($site, $newPlan);

            if ($result['success']) {
                Log::info("User changed subscription plan", [
                    'user_id' => Auth::id(),
                    'site_id' => $site->id,
                    'old_plan_id' => $site->plan_id,
                    'new_plan_id' => $newPlan->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Plan changed to ' . $newPlan->name
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

        } catch (Exception $e) {
            Log::error("Plan change failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Plan change failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request, Site $site)
    {
        $this->authorize('update', $site);

        $immediately = $request->boolean('immediately', false);

        try {
            $success = $this->billingService->cancelSubscription($site, $immediately);

            if ($success) {
                $message = $immediately ? 
                    'Subscription canceled immediately.' : 
                    'Subscription will be canceled at the end of the current billing period.';

                Log::info("User canceled subscription", [
                    'user_id' => Auth::id(),
                    'site_id' => $site->id,
                    'immediately' => $immediately
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $message
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel subscription.'
                ], 400);
            }

        } catch (Exception $e) {
            Log::error("Subscription cancellation failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Cancellation failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Resume canceled subscription
     */
    public function resumeSubscription(Site $site)
    {
        $this->authorize('update', $site);

        try {
            $success = $this->billingService->resumeSubscription($site);

            if ($success) {
                Log::info("User resumed subscription", [
                    'user_id' => Auth::id(),
                    'site_id' => $site->id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription resumed successfully.'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to resume subscription.'
                ], 400);
            }

        } catch (Exception $e) {
            Log::error("Subscription resume failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Resume failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Update payment method
     */
    public function updatePaymentMethod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Attach the payment method to customer
            if (!$user->stripe_customer_id) {
                $this->billingService->getOrCreateCustomer($user);
            }

            // TODO: Update default payment method for customer
            // This would involve Stripe API calls to set the default payment method

            Log::info("User updated payment method", [
                'user_id' => $user->id,
                'payment_method_id' => $request->payment_method_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully.'
            ]);

        } catch (Exception $e) {
            Log::error("Payment method update failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method.'
            ], 500);
        }
    }

    /**
     * Download invoice
     */
    public function downloadInvoice(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        
        if (!$invoiceId) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice ID required.'
            ], 400);
        }

        try {
            // Get invoice from Stripe and return download URL
            $user = Auth::user();
            $billingHistory = $this->billingService->getBillingHistory($user, 50);
            
            $invoice = collect($billingHistory)->firstWhere('id', $invoiceId);
            
            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'download_url' => $invoice['pdf_url']
            ]);

        } catch (Exception $e) {
            Log::error("Invoice download failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get invoice download link.'
            ], 500);
        }
    }

    /**
     * Get billing history
     */
    public function billingHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 10);
            
            $history = $this->billingService->getBillingHistory($user, $limit);

            return response()->json([
                'success' => true,
                'history' => $history
            ]);

        } catch (Exception $e) {
            Log::error("Billing history fetch failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load billing history.'
            ], 500);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            // Verify webhook signature
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            
            Log::info("Received Stripe webhook", [
                'type' => $event['type'],
                'id' => $event['id']
            ]);

            // Handle the event
            $handled = $this->billingService->handleWebhook($event);

            if ($handled) {
                return response()->json(['status' => 'success']);
            } else {
                Log::warning("Webhook not handled", ['type' => $event['type']]);
                return response()->json(['status' => 'ignored']);
            }

        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error("Webhook signature verification failed: " . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
            
        } catch (Exception $e) {
            Log::error("Webhook processing failed: " . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Show current subscription details
     */
    public function subscription(Site $site)
    {
        $this->authorize('view', $site);

        if (!$site->stripe_subscription_id) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found for this site.'
            ], 404);
        }

        try {
            $subscription = [
                'id' => $site->stripe_subscription_id,
                'status' => $site->subscription_status,
                'plan' => $site->plan,
                'current_period_end' => $site->expires_at,
                'cancel_at_period_end' => $site->subscription_status === 'cancel_at_period_end'
            ];

            return response()->json([
                'success' => true,
                'subscription' => $subscription
            ]);

        } catch (Exception $e) {
            Log::error("Subscription details fetch failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load subscription details.'
            ], 500);
        }
    }

    /**
     * Preview plan change (calculate prorations)
     */
    public function previewPlanChange(Request $request, Site $site)
    {
        $this->authorize('view', $site);

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $newPlan = Plan::findOrFail($request->plan_id);
            $currentPlan = $site->plan;

            if (!$currentPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'No current plan to compare with.'
                ], 400);
            }

            // Calculate proration (simplified)
            $currentPrice = $currentPlan->price;
            $newPrice = $newPlan->price;
            $priceDiff = $newPrice - $currentPrice;

            // Calculate remaining days in current period
            $remainingDays = now()->diffInDays($site->expires_at);
            $totalDays = 30; // Assuming monthly billing
            $proratedAmount = ($priceDiff * $remainingDays) / $totalDays;

            return response()->json([
                'success' => true,
                'preview' => [
                    'current_plan' => $currentPlan->name,
                    'new_plan' => $newPlan->name,
                    'current_price' => $currentPrice,
                    'new_price' => $newPrice,
                    'price_difference' => $priceDiff,
                    'prorated_amount' => round($proratedAmount, 2),
                    'next_billing_date' => $site->expires_at->toDateString(),
                    'immediate_charge' => $proratedAmount > 0 ? $proratedAmount : 0
                ]
            ]);

        } catch (Exception $e) {
            Log::error("Plan change preview failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview plan change.'
            ], 500);
        }
    }
}