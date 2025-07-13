<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        // Filter by gender
        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('customers.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'level' => 'nullable|in:silver,gold,platinum',
        ]);

        $customer = Customer::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc')->take(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('customers.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'level' => 'nullable|in:silver,gold,platinum',
        ]);

        $customer->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('customers.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Check if customer has transactions
        if ($customer->transactions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer that has transaction history'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }

    /**
     * Add loyalty points to customer
     */
    public function addLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('customers.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'points' => 'required|integer|min:1',
            'reason' => 'nullable|string',
        ]);

        $customer->addLoyaltyPoints($request->points);

        return response()->json([
            'success' => true,
            'message' => 'Loyalty points added successfully',
            'data' => [
                'customer' => $customer->fresh(),
                'points_added' => $request->points,
            ]
        ]);
    }

    /**
     * Redeem loyalty points
     */
    public function redeemLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('customers.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        if ($customer->deductLoyaltyPoints($request->points)) {
            $redeemRate = \App\Models\Setting::get('loyalty_redeem_rate', 1000);
            $cashValue = $request->points / $redeemRate * 1000; // Convert to rupiah

            return response()->json([
                'success' => true,
                'message' => 'Loyalty points redeemed successfully',
                'data' => [
                    'customer' => $customer->fresh(),
                    'points_redeemed' => $request->points,
                    'cash_value' => $cashValue,
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient loyalty points'
            ], 422);
        }
    }
}
