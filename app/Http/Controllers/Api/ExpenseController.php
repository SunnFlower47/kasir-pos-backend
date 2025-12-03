<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.view permission'
            ], 403);
        }

        $query = Expense::with(['outlet', 'user']);

        // Filter by outlet
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('expense_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('expense_date', '<=', $request->date_to);
        }

        // Search by expense number or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $expenses = $query->orderBy('expense_date', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $expenses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.create permission'
            ], 403);
        }

        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'expense_date' => 'required|date',
            'category' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris,ewallet',
            'notes' => 'nullable|string',
        ]);

        try {
            $expense = Expense::create([
                'expense_number' => Expense::generateExpenseNumber(),
                'outlet_id' => $request->outlet_id,
                'expense_date' => $request->expense_date,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'user_id' => $user->id,
            ]);

            $expense->load(['outlet', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'data' => $expense
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.view permission'
            ], 403);
        }

        $expense->load(['outlet', 'user']);

        return response()->json([
            'success' => true,
            'data' => $expense
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.edit permission'
            ], 403);
        }

        $request->validate([
            'outlet_id' => 'sometimes|required|exists:outlets,id',
            'expense_date' => 'sometimes|required|date',
            'category' => 'nullable|string|max:255',
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'payment_method' => 'sometimes|required|in:cash,transfer,qris,ewallet',
            'notes' => 'nullable|string',
        ]);

        try {
            $expense->update($request->only([
                'outlet_id',
                'expense_date',
                'category',
                'description',
                'amount',
                'payment_method',
                'notes',
            ]));

            $expense->load(['outlet', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'data' => $expense
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.delete permission'
            ], 403);
        }

        try {
            $expense->delete();

            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete expense: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expense categories (for dropdown/statistics)
     */
    public function categories(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (!$user || !$user->can('expenses.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing expenses.view permission'
            ], 403);
        }

        $categories = Expense::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
