<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        // Set Midtrans Config
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    /**
     * Get current subscription status
     */
    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        
        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }

        $subscription = $tenant->activeSubscription;

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant->name,
                'status' => $subscription ? $subscription->status : 'inactive',
                'subscription' => $subscription,
                'days_remaining' => $subscription && $subscription->end_date ? Carbon::now()->diffInDays($subscription->end_date, false) : 0
            ]
        ]);
    }

    /**
     * Activate Free Trial (14 Days)
     */
    public function activateTrial(Request $request)
    {
        $tenant = $request->user()->tenant;
        
        // Check if ever had trial? (Optional logic: if exists and is not 'inactive' maybe block?)
        // For now simple logic: Update current subscription to trial active.
        
        $subscription = $tenant->subscriptions()->latest()->first();
        
        if (!$subscription) {
             $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'status' => 'inactive',
                'plan_name' => 'none', // Placeholder to satisfy strict SQL if migration wasn't fresh
                'price' => 0,
                'period' => 'monthly',
                'start_date' => Carbon::now(),
            ]);
        }

        if ($subscription->status === 'active' || ($subscription->plan_name === 'trial' && $subscription->end_date)) {
             return response()->json([
                'success' => false,
                'message' => 'Trial or Subscription is already active/used.'
            ], 400);
        }

        $subscription->update([
            'plan_name' => 'trial',
            'status' => 'active',
            'start_date' => Carbon::now(),
            'end_date' => Carbon::now()->addDays(14),
            'features' => ['web', 'mobile', 'desktop'],
            'max_outlets' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trial activated successfully (14 Days)',
            'data' => $subscription
        ]);
    }

    /**
     * Get available plans (Mock data for now)
     */
    /**
     * Get available plans (Dynamic from DB)
     */
    public function plans()
    {
        $plans = \App\Models\SubscriptionPlan::where('is_active', true)->get()->map(function($plan) {
            return [
                'id' => $plan->slug,
                'name' => $plan->name,
                'price' => (float)$plan->price,
                'period' => $plan->slug, // Using slug as period identifier for now
                'features' => $plan->features ?? ['web', 'mobile', 'desktop'],
                'description' => $plan->description
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Create Payment Token (Snap)
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,slug',
        ]);

        $tenant = $request->user()->tenant;
        $user = $request->user();

        // Pricing Logic (Dynamic)
        $plan = \App\Models\SubscriptionPlan::where('slug', $request->plan_id)->firstOrFail();
        $price = (float) $plan->price;
        $planName = $plan->name;

        DB::beginTransaction();
        try {
            // Generate Order ID
            $orderId = 'SUB-' . $tenant->id . '-' . time();

            // Create Payment Record (Pending)
            // Note: We don't create the *Subscription* record yet, or we update the existing one?
            // Strategy: Create a Payment record first. Upon success, extend/create Subscription.
            // But we need to link it to *a* subscription. 
            // Let's grab the current subscription (even if expired) or create a placeholder.
            // Actually, SubscriptionPayment belongs to Subscription.
            // So we should find the current subscription or create a new 'pending' one?
            // Better approach: Just track the Payment. If successful, THEN update the Subscription.
            // BUT, the schema says payment belongs to subscription.
            // So we take the current subscription (active or expired) or create a new one if none exists.
            
            $subscription = $tenant->subscriptions()->latest()->first();

            if (!$subscription) {
                // Should have been created at register, but just in case
                 $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_name' => $request->plan_id,
                    'status' => 'pending',
                    'price' => $price,
                    'period' => $request->plan_id,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now(), // Expired/Pending
                ]);
            }
            
            $payment = SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'order_id' => $orderId,
                'amount' => $price,
                'status' => 'pending',
                'notes' => 'Renewal for ' . $planName
            ]);

            // Midtrans Params
            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $price,
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'item_details' => [
                    [
                        'id' => $request->plan_id,
                        'price' => $price,
                        'quantity' => 1,
                        'name' => $planName,
                    ]
                ]
            ];

            $snapToken = Snap::getSnapToken($params);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $snapToken,
                    'redirect_url' => "https://app.midtrans.com/snap/v2/vtweb/" . $snapToken
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Midtrans Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Midtrans Webhook
     * NOTE: This should be excluded from CSRF and Auth middleware
     */
    public function callback(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);
        
        // Simple signature verification (Midtrans usually sends 'signature_key')
        if ($request->signature_key !== $hashed) {
            return response()->json(['message' => 'Invalid Signature'], 400);
        }

        $transactionStatus = $request->transaction_status;
        $orderId = $request->order_id;
        $payment = SubscriptionPayment::where('order_id', $orderId)->first();

        if (!$payment) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            // Payment Success
            DB::transaction(function () use ($payment, $request) {
                $payment->update([
                    'status' => 'paid',
                    'midtrans_response' => $request->all(),
                    'payment_date' => Carbon::now()
                ]);

                // Update Subscription
                $subscription = $payment->subscription;
                
                // Determine new dates
                // If currently active and future, add to end_date. Otherwise start from now.
                $startDate = Carbon::now();
                if ($subscription->end_date && $subscription->end_date->isFuture()) {
                    $startDate = $subscription->end_date;
                }

                // Add month/year
                $period = $payment->notes; // Or infer from amount/plan
                // For simplicity, let's assume monthly (30 days) if amount ~ 100k
                $daysToAdd = ($payment->amount >= 1000000) ? 365 : 30;

                $subscription->update([
                    'plan_name' => $payment->amount >= 1000000 ? 'yearly' : 'monthly',
                    'status' => 'active',
                    'end_date' => $startDate->copy()->addDays($daysToAdd),
                    'next_billing_date' => $startDate->copy()->addDays($daysToAdd)
                ]);
            });

        } else if ($transactionStatus == 'deny' || $transactionStatus == 'expire' || $transactionStatus == 'cancel') {
            $payment->update([
                'status' => 'failed',
                'midtrans_response' => $request->all()
            ]);
        }

    }

    /**
     * Check Payment Status Manually (For Frontend polling or Localhost)
     */
    public function checkStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);

        $orderId = $request->order_id;
        $payment = SubscriptionPayment::where('order_id', $orderId)->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        try {
            // Get Status from Midtrans
            $status = \Midtrans\Transaction::status($orderId);
            $transactionStatus = $status->transaction_status;
            // $fraudStatus = $status->fraud_status;

            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                // Success - Update logic (Same as callback)
                if ($payment->status !== 'paid') {
                    DB::transaction(function () use ($payment, $status) {
                        $payment->update([
                            'status' => 'paid',
                            'midtrans_response' => (array)$status,
                            'payment_date' => Carbon::now()
                        ]);

                        // Update Subscription
                        $subscription = $payment->subscription;
                        
                        // Determine new dates
                        $startDate = Carbon::now();
                        if ($subscription->end_date && $subscription->end_date->isFuture()) {
                            $startDate = $subscription->end_date;
                        }

                        // Add month/year
                        $period = $payment->notes; 
                        // Logic: if payment >= 1jt then 1 year, else 1 month
                        $amount = (int)$payment->amount;
                        $daysToAdd = ($amount >= 1000000) ? 365 : 30;

                        $subscription->update([
                            'plan_name' => $amount >= 1000000 ? 'yearly' : 'monthly',
                            'status' => 'active',
                            'end_date' => $startDate->copy()->addDays($daysToAdd),
                            'next_billing_date' => $startDate->copy()->addDays($daysToAdd)
                        ]);
                    });
                }
                return response()->json(['success' => true, 'status' => 'paid', 'message' => 'Payment successful']);

            } else if ($transactionStatus == 'pending') {
                return response()->json(['success' => true, 'status' => 'pending', 'message' => 'Waiting for payment']);
            } else if ($transactionStatus == 'deny' || $transactionStatus == 'expire' || $transactionStatus == 'cancel') {
                $payment->update(['status' => 'failed']);
                return response()->json(['success' => false, 'status' => 'failed', 'message' => 'Payment failed']);
            }

            return response()->json(['success' => true, 'status' => $payment->status]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

    }

    /**
     * Get Payment History
     */
    public function history(Request $request)
    {
        $tenant = $request->user()->tenant;
        
        $payments = SubscriptionPayment::whereHas('subscription', function($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

}

