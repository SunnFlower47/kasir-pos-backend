<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

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

    public function activateTrial(Request $request)
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant->subscriptions()->latest()->first();

        if (!$subscription) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'status' => 'inactive',
                'plan_name' => 'none',
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

    public function plans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get()->map(function ($plan) {
            $rawFeatures = $plan->features;
            $displayFeatures = ['web', 'mobile', 'desktop'];
            $platforms = ['web', 'mobile', 'desktop'];
            $popular = false;
            $cta = 'Pilih ' . $plan->name;

            if (is_string($rawFeatures)) {
                $decoded = json_decode($rawFeatures, true);
                if (is_array($decoded)) {
                    $rawFeatures = $decoded;
                }
            }

            if (is_array($rawFeatures)) {
                if (isset($rawFeatures['display_features'])) {
                    $displayFeatures = $rawFeatures['display_features'];
                    $platforms = $rawFeatures['platforms'] ?? ['web', 'mobile', 'desktop'];
                    $popular = $rawFeatures['is_popular'] ?? false;
                    $cta = $rawFeatures['cta_text'] ?? ('Pilih ' . $plan->name);
                } elseif (isset($rawFeatures['max_users']) || isset($rawFeatures['limits'])) {
                    $displayFeatures = [];
                    if (isset($rawFeatures['max_users']) && $rawFeatures['max_users'] > 0) $displayFeatures[] = "Max {$rawFeatures['max_users']} Users";
                    if (isset($rawFeatures['max_outlets']) && $rawFeatures['max_outlets'] > 0) $displayFeatures[] = "Max {$rawFeatures['max_outlets']} Outlets";
                } else {
                    $displayFeatures = $rawFeatures;
                }
            }

            return [
                'id' => $plan->slug,
                'name' => $plan->name,
                'price' => (float)$plan->price,
                'period' => $plan->slug,
                'features' => $displayFeatures,
                'platforms' => $platforms,
                'popular' => $popular,
                'cta' => $cta,
                'description' => $plan->description
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function createPayment(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,slug',
        ]);

        $tenant = $request->user()->tenant;
        $user = $request->user();

        $plan = SubscriptionPlan::where('slug', $request->plan_id)->firstOrFail();
        $price = (float)$plan->price;

        DB::beginTransaction();
        try {
            $orderId = sprintf('SUB-%d-%s', $tenant->id, Str::upper(Str::random(12)));
            $subscription = $tenant->subscriptions()->latest()->first();

            if (!$subscription) {
                $subscription = Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_name' => $request->plan_id,
                    'status' => 'pending',
                    'price' => $price,
                    'period' => $request->plan_id,
                    'start_date' => Carbon::now(),
                    'end_date' => Carbon::now(),
                ]);
            }

            $paymentMeta = [
                'plan_slug' => $plan->slug,
                'plan_name' => $plan->name,
                'duration_in_days' => (int)($plan->duration_in_days ?: 30),
            ];

            SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'order_id' => $orderId,
                'amount' => $price,
                'status' => 'pending',
                'notes' => json_encode($paymentMeta),
            ]);

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (int)round($price),
                ],
                'customer_details' => [
                    'first_name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'item_details' => [[
                    'id' => $plan->slug,
                    'price' => (int)round($price),
                    'quantity' => 1,
                    'name' => $plan->name,
                ]]
            ];

            $snapToken = Snap::getSnapToken($params);
            DB::commit();

            $base = config('midtrans.is_production')
                ? 'https://app.midtrans.com/snap/v2/vtweb/'
                : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/';

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $snapToken,
                    'redirect_url' => $base . $snapToken,
                ]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Create payment failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create payment'], 500);
        }
    }

    public function callback(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'status_code' => 'required',
            'gross_amount' => 'required',
            'signature_key' => 'required|string',
            'transaction_status' => 'required|string',
        ]);

        $serverKey = config('midtrans.server_key');
        $hashed = hash('sha512', $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if (!hash_equals($hashed, (string)$request->signature_key)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        try {
            return DB::transaction(function () use ($request) {
                $payment = SubscriptionPayment::where('order_id', $request->order_id)
                    ->lockForUpdate()
                    ->first();

                if (!$payment) {
                    return response()->json(['message' => 'Order not found'], 404);
                }

                // idempotent: stop if already processed
                if ($payment->status === 'paid') {
                    return response()->json(['message' => 'Already processed'], 200);
                }

                if ((float)$payment->amount !== (float)$request->gross_amount) {
                    Log::warning('Midtrans gross_amount mismatch', [
                        'order_id' => $request->order_id,
                        'expected' => $payment->amount,
                        'received' => $request->gross_amount,
                    ]);
                    return response()->json(['message' => 'Amount mismatch'], 400);
                }

                $status = $request->transaction_status;
                $fraudStatus = $request->fraud_status;

                if ($status === 'capture' && $fraudStatus === 'challenge') {
                    $payment->update([
                        'status' => 'pending',
                        'midtrans_response' => $request->all(),
                    ]);

                    return response()->json(['message' => 'Payment challenged'], 200);
                }

                if ($status === 'capture' || $status === 'settlement') {
                    $payment->update([
                        'status' => 'paid',
                        'payment_method' => $request->payment_type,
                        'transaction_reference' => $request->transaction_id,
                        'midtrans_response' => $request->all(),
                        'payment_date' => Carbon::now(),
                    ]);

                    $this->applySubscriptionFromPayment($payment);
                    return response()->json(['message' => 'OK'], 200);
                }

                if (in_array($status, ['deny', 'expire', 'cancel'], true)) {
                    $payment->update([
                        'status' => 'failed',
                        'payment_method' => $request->payment_type,
                        'transaction_reference' => $request->transaction_id,
                        'midtrans_response' => $request->all(),
                    ]);

                    return response()->json(['message' => 'Updated'], 200);
                }

                return response()->json(['message' => 'Ignored'], 200);
            });
        } catch (\Throwable $e) {
            Log::error('Midtrans callback failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Internal server error'], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);

        $tenant = $request->user()->tenant;
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $orderId = $request->order_id;
        $payment = SubscriptionPayment::where('order_id', $orderId)
            ->whereHas('subscription', function ($q) use ($tenant) {
                $q->where('tenant_id', $tenant->id);
            })
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        try {
            $status = Transaction::status($orderId);
            $transactionStatus = $status->transaction_status ?? null;

            if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
                DB::transaction(function () use ($payment, $status) {
                    $lockedPayment = SubscriptionPayment::where('id', $payment->id)->lockForUpdate()->first();

                    if ($lockedPayment->status === 'paid') {
                        return;
                    }

                    $lockedPayment->update([
                        'status' => 'paid',
                        'payment_method' => $status->payment_type ?? null,
                        'transaction_reference' => $status->transaction_id ?? null,
                        'midtrans_response' => (array)$status,
                        'payment_date' => Carbon::now(),
                    ]);

                    $this->applySubscriptionFromPayment($lockedPayment);
                });

                return response()->json(['success' => true, 'status' => 'paid', 'message' => 'Payment successful']);
            }

            if ($transactionStatus === 'pending') {
                return response()->json(['success' => true, 'status' => 'pending', 'message' => 'Waiting for payment']);
            }

            if (in_array($transactionStatus, ['deny', 'expire', 'cancel'], true)) {
                if ($payment->status !== 'paid') {
                    $payment->update([
                        'status' => 'failed',
                        'midtrans_response' => (array)$status,
                    ]);
                }

                return response()->json(['success' => false, 'status' => 'failed', 'message' => 'Payment failed']);
            }

            return response()->json(['success' => true, 'status' => $payment->status]);
        } catch (\Throwable $e) {
            Log::error('Check payment status failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to check payment status'], 500);
        }
    }

    public function history(Request $request)
    {
        $tenant = $request->user()->tenant;

        $payments = SubscriptionPayment::whereHas('subscription', function ($q) use ($tenant) {
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

    private function applySubscriptionFromPayment(SubscriptionPayment $payment): void
    {
        $subscription = $payment->subscription;
        if (!$subscription) {
            return;
        }

        $planMeta = $this->extractPlanMeta($payment);
        $daysToAdd = max(1, (int)($planMeta['duration_in_days'] ?? 30));
        $planName = $planMeta['plan_slug'] ?? ($planMeta['plan_name'] ?? $subscription->plan_name ?? 'monthly');

        $startDate = Carbon::now();
        if ($subscription->end_date && Carbon::parse($subscription->end_date)->isFuture()) {
            $startDate = Carbon::parse($subscription->end_date);
        }

        $subscription->update([
            'plan_name' => $planName,
            'status' => 'active',
            'price' => $payment->amount,
            'period' => $daysToAdd >= 365 ? 'yearly' : 'monthly',
            'start_date' => $subscription->start_date ?: Carbon::now(),
            'end_date' => $startDate->copy()->addDays($daysToAdd),
            'next_billing_date' => $startDate->copy()->addDays($daysToAdd),
        ]);
    }

    private function extractPlanMeta(SubscriptionPayment $payment): array
    {
        $notes = $payment->notes;
        if (is_string($notes)) {
            $decoded = json_decode($notes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [
            'duration_in_days' => ((float)$payment->amount >= 1000000) ? 365 : 30,
            'plan_slug' => ((float)$payment->amount >= 1000000) ? 'yearly' : 'monthly',
            'plan_name' => ((float)$payment->amount >= 1000000) ? 'Yearly' : 'Monthly',
        ];
    }
}
