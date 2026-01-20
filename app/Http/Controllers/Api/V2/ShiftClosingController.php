<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ShiftClosing;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ShiftClosingController extends Controller
{
    /**
     * Get the last closing for a specific outlet and user
     */
    public function getLastClosing(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $outletId = $request->get('outlet_id', $user->outlet_id);
            $userId = $request->get('user_id', $user->id);

            $lastClosing = ShiftClosing::where('outlet_id', $outletId)
                ->where('user_id', $userId)
                ->orderBy('closing_date', 'desc')
                ->orderBy('closing_time', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $lastClosing
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching last closing', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading last closing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new shift closing
     */
    public function store(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'outlet_id' => 'required|exists:outlets,id',
                'cashier_name' => 'required|string|max:255',
                'cashier_email' => 'required|email|max:255',
                'total_transactions' => 'required|integer|min:0',
                'total_revenue' => 'required|numeric|min:0',
                'revenue_by_payment' => 'required|array',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $closingDate = now()->toDateString();
            $closingTime = now()->toTimeString();

            // Get last closing to set last_closing_at
            $lastClosing = ShiftClosing::where('outlet_id', $request->outlet_id)
                ->where('user_id', $user->id)
                ->orderBy('closing_date', 'desc')
                ->orderBy('closing_time', 'desc')
                ->first();

            $shiftClosing = ShiftClosing::create([
                'outlet_id' => $request->outlet_id,
                'user_id' => $user->id,
                'cashier_name' => $request->cashier_name,
                'cashier_email' => $request->cashier_email,
                'closing_date' => $closingDate,
                'closing_time' => $closingTime,
                'total_transactions' => $request->total_transactions,
                'total_revenue' => $request->total_revenue,
                'revenue_by_payment' => $request->revenue_by_payment,
                'last_closing_at' => $lastClosing ? $lastClosing->created_at : null,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shift closing saved successfully',
                'data' => $shiftClosing
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error saving shift closing', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error saving shift closing: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get closing history
     */
    public function index(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = ShiftClosing::with(['outlet:id,name', 'user:id,name,email']);

            // Filter by outlet
            if ($request->has('outlet_id') && $request->outlet_id) {
                $query->where('outlet_id', $request->outlet_id);
            } else {
                // If user has outlet, filter by it
                if ($user->outlet_id) {
                    $query->where('outlet_id', $user->outlet_id);
                }
            }

            // Filter by user
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by date range
            if ($request->has('date_from') && $request->date_from) {
                $query->where('closing_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('closing_date', '<=', $request->date_to);
            }

            $perPage = $request->get('per_page', 15);
            $closings = $query->orderBy('closing_date', 'desc')
                ->orderBy('closing_time', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $closings
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching shift closings', [
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading shift closings: ' . $e->getMessage()
            ], 500);
        }
    }
}
