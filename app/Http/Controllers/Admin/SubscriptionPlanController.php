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
            // Display & Marketing
            'display_features' => 'nullable|array',
            'platforms' => 'nullable|array',
            'main_cta' => 'nullable|string',
        ]);

        // Pack features into structured JSON
        $features = [
            'limits' => [
                'max_users' => $request->max_users ?? 0,
                'max_outlets' => $request->max_outlets ?? 1,
                'max_products' => $request->max_products ?? 1000,
            ],
            'display_features' => array_values(array_filter($request->display_features ?? [], function($value) { return !is_null($value) && $value !== ''; })),
            'platforms' => $request->platforms ?? [],
            'is_popular' => $request->has('is_popular'),
            'cta_text' => $request->cta_text ?? 'Choose Plan',
        ];
        
        $data = $request->only(['name', 'slug', 'price', 'duration_in_days', 'description']);
        $data['features'] = $features; // Eloquent casts this to JSON automatically

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
             // Display & Marketing
            'display_features' => 'nullable|array',
            'platforms' => 'nullable|array',
            'main_cta' => 'nullable|string',
        ]);

        // Pack features
        // Merge with existing if needed, but here we rebuild the structure
        $features = [
            'limits' => [
                'max_users' => $request->max_users ?? ($plan->features['limits']['max_users'] ?? 0),
                'max_outlets' => $request->max_outlets ?? ($plan->features['limits']['max_outlets'] ?? 1),
                'max_products' => $request->max_products ?? ($plan->features['limits']['max_products'] ?? 1000),
            ],
            'display_features' => array_values(array_filter($request->display_features ?? [], function($value) { return !is_null($value) && $value !== ''; })),
            'platforms' => $request->platforms ?? [],
            'is_popular' => $request->has('is_popular'),
            'cta_text' => $request->cta_text ?? 'Choose Plan',
        ];

        $data = $request->only(['name', 'slug', 'price', 'duration_in_days', 'description', 'is_active']);
        $data['features'] = $features;

        $plan->update($data);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully');
    }
}
