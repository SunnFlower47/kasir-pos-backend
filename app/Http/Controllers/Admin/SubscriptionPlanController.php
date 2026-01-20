<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::all();
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:subscription_plans',
            'price' => 'required|numeric',
            'duration_in_days' => 'required|integer',
            'description' => 'nullable',
            // Feature Limits
            'max_users' => 'nullable|integer',
            'max_outlets' => 'nullable|integer',
            'max_products' => 'nullable|integer',
        ]);

        // Pack features
        $features = [
            'max_users' => $request->max_users ?? 0, // 0 usually means unlimited/or specific logic, let's treat null as unlimited if we want, but user wants limit. Let's say 0 is none? Or use null for unlimited. Let's use user input directly.
            'max_outlets' => $request->max_outlets ?? 1,
            'max_products' => $request->max_products ?? 1000,
        ];
        
        $data = $request->only(['name', 'slug', 'price', 'duration_in_days', 'description']);
        $data['features'] = $features;

        SubscriptionPlan::create($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
         $validated = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:subscription_plans,slug,' . $plan->id,
            'price' => 'required|numeric',
            'duration_in_days' => 'required|integer',
            'description' => 'nullable',
            'is_active' => 'boolean',
            // Feature Limits
            'max_users' => 'nullable|integer',
            'max_outlets' => 'nullable|integer',
            'max_products' => 'nullable|integer',
        ]);

        // Pack features
        $features = [
            'max_users' => $request->max_users ?? $plan->features['max_users'] ?? 0,
            'max_outlets' => $request->max_outlets ?? $plan->features['max_outlets'] ?? 1,
            'max_products' => $request->max_products ?? $plan->features['max_products'] ?? 1000,
        ];

        $data = $request->only(['name', 'slug', 'price', 'duration_in_days', 'description', 'is_active']);
        $data['features'] = $features;

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully');
    }
}
