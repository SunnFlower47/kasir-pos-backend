<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::with('latestSubscription')->latest();

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'suspended') {
                $query->where('is_active', false);
            }
        }

        $tenants = $query->paginate(10);
        return view('admin.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'outlets']);
        return view('admin.tenants.show', compact('tenant'));
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'business_type' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant updated successfully');
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['is_active' => false]);
        \App\Models\AuditLog::createLog('App\Models\Tenant', $tenant->id, 'suspend');
        return back()->with('success', 'Tenant suspended successfully.');
    }

    public function resume(Tenant $tenant)
    {
        $tenant->update(['is_active' => true]);
        \App\Models\AuditLog::createLog('App\Models\Tenant', $tenant->id, 'resume');
        return back()->with('success', 'Tenant resumed successfully.');
    }



    public function extend(Tenant $tenant)
    {
        $plans = \App\Models\SubscriptionPlan::where('is_active', true)->get();
        return view('admin.tenants.extend', compact('tenant', 'plans'));
    }

    public function processExtend(Request $request, Tenant $tenant)
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'duration_days' => 'required|integer|min:1',
            'notes' => 'nullable|string'
        ]);

        $plan = \App\Models\SubscriptionPlan::findOrFail($request->plan_id);
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($tenant, $plan, $request) {
            // Find or Create Subscription
            $subscription = $tenant->latestSubscription;
            
            if (!$subscription) {
                 $subscription = \App\Models\Subscription::create([
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'plan_name' => $plan->name, // store slug or name? Model usually stores slug/name. Controller checked plan_name. Let's use name as it matches 'trial' logic or slug if standard. Let's use name.
                    'price' => $plan->price,
                    'period' => $request->duration_days >= 365 ? 'yearly' : 'monthly',
                    'start_date' => now(),
                    'end_date' => now(), // will update below
                ]);
            }

            // Create Payment Record (Manual)
            \App\Models\SubscriptionPayment::create([
                'subscription_id' => $subscription->id,
                'order_id' => 'MANUAL-' . time(),
                'payment_method' => 'manual',
                'amount' => $plan->price, // Or 0? Let's assume paid full price manually. 
                'status' => 'paid',
                'notes' => 'Manual Extension: ' . $request->notes,
                'payment_date' => now(),
            ]);

            // Update Subscription Dates
            $startDate = now();
            if ($subscription->end_date && $subscription->end_date->isFuture()) {
                $startDate = $subscription->end_date;
            }

            $subscription->update([
                'plan_name' => $plan->name,
                'status' => 'active',
                'end_date' => $startDate->copy()->addDays($request->duration_days),
                'features' => $plan->features
            ]);
            
            \App\Models\AuditLog::createLog('App\Models\Subscription', $subscription->id, 'manual_extend', null, [
                'plan' => $plan->name,
                'duration' => $request->duration_days,
                'notes' => $request->notes
            ]);
        });
        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Subscription extended successfully.');
    }

    public function impersonate(Tenant $tenant)
    {
        // 1. Verify Requester is System Admin
        $admin = auth()->user();
        if (!$admin->hasRole('System Admin')) {
            abort(403, 'Unauthorized. Only System Admins can impersonate.');
        }

        // Find the owner/first user of the tenant
        $user = \App\Models\User::where('tenant_id', $tenant->id)->first();

        if (!$user) {
            return back()->with('error', 'No user found for this tenant.');
        }

        // 2. Verify Target is NOT System Admin (Anti-Escalation)
        if ($user->hasRole('System Admin')) {
            abort(403, 'Security Alert: Cannot impersonate another System Admin.');
        }

        // 3. Create a short-lived token (30 mins)
        // Note: Sanctum personal_access_tokens table has 'expires_at' column if configured.
        // We set explicitly here.
        $token = $user->createToken('impersonate_token', ['*'], now()->addMinutes(30))->plainTextToken;

        // Log the action
        \App\Models\AuditLog::createLog('App\Models\Tenant', $tenant->id, 'impersonate_start', null, ['target_user_id' => $user->id]);

        // Redirect to Frontend with token
        // Assuming Frontend is running on localhost:3000 for development or defined in env
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        return redirect()->away("$frontendUrl/login?impersonate_token=$token");
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return redirect()->route('admin.tenants.index')->with('success', 'Tenant deleted successfully');
    }
}
