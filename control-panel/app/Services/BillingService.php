<?php

namespace App\Services;

use App\Models\User;
use App\Models\Site;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\Price;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Exception;

class BillingService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create or retrieve Stripe customer
     */
    public function getOrCreateCustomer(User $user): ?string
    {
        try {
            // Return existing customer ID if available
            if ($user->stripe_customer_id) {
                return $user->stripe_customer_id;
            }

            // Create new Stripe customer
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                    'platform' => 'wp-saas'
                ]
            ]);

            // Save customer ID to user
            $user->update(['stripe_customer_id' => $customer->id]);

            Log::info("Created Stripe customer for user: {$user->email}");
            return $customer->id;

        } catch (Exception $e) {
            Log::error("Failed to create Stripe customer for user {$user->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create subscription for a site
     */
    public function createSubscription(Site $site, Plan $plan, ?string $paymentMethodId = null): array
    {
        try {
            $user = $site->user;
            $customerId = $this->getOrCreateCustomer($user);

            if (!$customerId) {
                throw new Exception('Could not create or retrieve Stripe customer');
            }

            // Attach payment method if provided
            if ($paymentMethodId) {
                $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
                $paymentMethod->attach(['customer' => $customerId]);
            }

            // Create subscription
            $subscriptionData = [
                'customer' => $customerId,
                'items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1
                ]],
                'metadata' => [
                    'site_id' => $site->id,
                    'plan_id' => $plan->id,
                    'subdomain' => $site->subdomain
                ],
                'expand' => ['latest_invoice.payment_intent']
            ];

            // Set default payment method if provided
            if ($paymentMethodId) {
                $subscriptionData['default_payment_method'] = $paymentMethodId;
            }

            $subscription = Subscription::create($subscriptionData);

            // Update site with subscription details
            $site->update([
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $subscription->id,
                'subscription_status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_end ? 
                    \Carbon\Carbon::createFromTimestamp($subscription->trial_end) : null,
                'expires_at' => \Carbon\Carbon::createFromTimestamp($subscription->current_period_end)
            ]);

            Log::info("Created subscription for site: {$site->subdomain}");

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null
            ];

        } catch (Exception $e) {
            Log::error("Failed to create subscription for site {$site->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Site $site, bool $immediately = false): bool
    {
        try {
            if (!$site->stripe_subscription_id) {
                Log::warning("No subscription ID found for site: {$site->subdomain}");
                return false;
            }

            $subscription = Subscription::retrieve($site->stripe_subscription_id);

            if ($immediately) {
                // Cancel immediately
                $subscription->cancel();
                $site->update([
                    'subscription_status' => 'canceled',
                    'expires_at' => now()
                ]);
            } else {
                // Cancel at period end
                $subscription->cancel_at_period_end = true;
                $subscription->save();
                $site->update(['subscription_status' => 'cancel_at_period_end']);
            }

            Log::info("Canceled subscription for site: {$site->subdomain}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to cancel subscription for site {$site->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Resume canceled subscription
     */
    public function resumeSubscription(Site $site): bool
    {
        try {
            if (!$site->stripe_subscription_id) {
                return false;
            }

            $subscription = Subscription::retrieve($site->stripe_subscription_id);
            $subscription->cancel_at_period_end = false;
            $subscription->save();

            $site->update(['subscription_status' => $subscription->status]);

            Log::info("Resumed subscription for site: {$site->subdomain}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to resume subscription for site {$site->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Site $site, Plan $newPlan): array
    {
        try {
            if (!$site->stripe_subscription_id) {
                throw new Exception('No active subscription found');
            }

            $subscription = Subscription::retrieve($site->stripe_subscription_id);
            
            // Update subscription item
            Subscription::update($subscription->id, [
                'items' => [[
                    'id' => $subscription->items->data[0]->id,
                    'price' => $newPlan->stripe_price_id,
                ]],
                'proration_behavior' => 'create_prorations'
            ]);

            // Update site plan
            $site->update(['plan_id' => $newPlan->id]);

            Log::info("Changed plan for site {$site->subdomain} to {$newPlan->name}");

            return [
                'success' => true,
                'message' => 'Plan changed successfully'
            ];

        } catch (Exception $e) {
            Log::error("Failed to change plan for site {$site->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create setup intent for saving payment method
     */
    public function createSetupIntent(User $user): array
    {
        try {
            $customerId = $this->getOrCreateCustomer($user);
            
            if (!$customerId) {
                throw new Exception('Could not create or retrieve customer');
            }

            $setupIntent = SetupIntent::create([
                'customer' => $customerId,
                'usage' => 'off_session',
                'metadata' => [
                    'user_id' => $user->id
                ]
            ]);

            return [
                'success' => true,
                'client_secret' => $setupIntent->client_secret
            ];

        } catch (Exception $e) {
            Log::error("Failed to create setup intent for user {$user->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get customer's payment methods
     */
    public function getPaymentMethods(User $user): array
    {
        try {
            if (!$user->stripe_customer_id) {
                return [];
            }

            $paymentMethods = PaymentMethod::all([
                'customer' => $user->stripe_customer_id,
                'type' => 'card'
            ]);

            return array_map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'brand' => $pm->card->brand,
                    'last4' => $pm->card->last4,
                    'exp_month' => $pm->card->exp_month,
                    'exp_year' => $pm->card->exp_year
                ];
            }, $paymentMethods->data);

        } catch (Exception $e) {
            Log::error("Failed to get payment methods for user {$user->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Handle webhook events
     */
    public function handleWebhook(array $event): bool
    {
        try {
            $type = $event['type'];
            $data = $event['data']['object'];

            switch ($type) {
                case 'invoice.payment_succeeded':
                    return $this->handlePaymentSucceeded($data);
                
                case 'invoice.payment_failed':
                    return $this->handlePaymentFailed($data);
                
                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($data);
                
                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($data);
                
                default:
                    Log::info("Unhandled webhook event: {$type}");
                    return true;
            }

        } catch (Exception $e) {
            Log::error("Failed to handle webhook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get billing history for user
     */
    public function getBillingHistory(User $user, int $limit = 10): array
    {
        try {
            if (!$user->stripe_customer_id) {
                return [];
            }

            $invoices = Invoice::all([
                'customer' => $user->stripe_customer_id,
                'limit' => $limit
            ]);

            return array_map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'amount' => $invoice->amount_paid / 100, // Convert from cents
                    'currency' => strtoupper($invoice->currency),
                    'status' => $invoice->status,
                    'date' => \Carbon\Carbon::createFromTimestamp($invoice->created)->toDateString(),
                    'invoice_url' => $invoice->hosted_invoice_url,
                    'pdf_url' => $invoice->invoice_pdf
                ];
            }, $invoices->data);

        } catch (Exception $e) {
            Log::error("Failed to get billing history for user {$user->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate usage-based charges (for future implementation)
     */
    public function calculateUsageCharges(Site $site, array $usage): float
    {
        $plan = $site->plan;
        $charges = 0;

        if (!$plan || !$plan->usage_billing_enabled) {
            return 0;
        }

        // Example: Charge for bandwidth overage
        if (isset($usage['bandwidth_mb']) && $usage['bandwidth_mb'] > $plan->bandwidth_limit_mb) {
            $overage = $usage['bandwidth_mb'] - $plan->bandwidth_limit_mb;
            $charges += $overage * ($plan->bandwidth_overage_rate ?? 0.01); // $0.01 per MB
        }

        // Example: Charge for storage overage
        if (isset($usage['storage_mb']) && $usage['storage_mb'] > $plan->storage_limit_mb) {
            $overage = $usage['storage_mb'] - $plan->storage_limit_mb;
            $charges += $overage * ($plan->storage_overage_rate ?? 0.05); // $0.05 per MB
        }

        return round($charges, 2);
    }

    /**
     * Private webhook handlers
     */
    private function handlePaymentSucceeded(array $invoice): bool
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        
        if (!$subscriptionId) {
            return true;
        }

        $site = Site::where('stripe_subscription_id', $subscriptionId)->first();
        
        if ($site) {
            $site->update([
                'subscription_status' => 'active',
                'expires_at' => \Carbon\Carbon::createFromTimestamp($invoice['period_end'])
            ]);
            
            Log::info("Payment succeeded for site: {$site->subdomain}");
        }

        return true;
    }

    private function handlePaymentFailed(array $invoice): bool
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        
        if (!$subscriptionId) {
            return true;
        }

        $site = Site::where('stripe_subscription_id', $subscriptionId)->first();
        
        if ($site) {
            $site->update(['subscription_status' => 'past_due']);
            
            // TODO: Send payment failed notification
            Log::warning("Payment failed for site: {$site->subdomain}");
        }

        return true;
    }

    private function handleSubscriptionUpdated(array $subscription): bool
    {
        $site = Site::where('stripe_subscription_id', $subscription['id'])->first();
        
        if ($site) {
            $site->update([
                'subscription_status' => $subscription['status'],
                'expires_at' => \Carbon\Carbon::createFromTimestamp($subscription['current_period_end'])
            ]);
            
            Log::info("Subscription updated for site: {$site->subdomain}");
        }

        return true;
    }

    private function handleSubscriptionDeleted(array $subscription): bool
    {
        $site = Site::where('stripe_subscription_id', $subscription['id'])->first();
        
        if ($site) {
            $site->update([
                'subscription_status' => 'canceled',
                'expires_at' => now()
            ]);
            
            Log::info("Subscription deleted for site: {$site->subdomain}");
        }

        return true;
    }
}