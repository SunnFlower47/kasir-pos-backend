<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with(['fromOutlet', 'toOutlet', 'user', 'stockTransferItems.product', 'items.product']);

        // Filter by outlet
        if ($request->has('outlet_id')) {
            $outletId = $request->outlet_id;
            $query->where(function ($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)
                  ->orWhere('to_outlet_id', $outletId);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('transfer_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('transfer_date', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $transfers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.transfer')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'from_outlet_id' => 'required|exists:outlets,id',
            'to_outlet_id' => 'required|exists:outlets,id|different:from_outlet_id',
            'transfer_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Create stock transfer
            $transfer = StockTransfer::create([
                'transfer_number' => StockTransfer::generateTransferNumber(),
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'transfer_date' => $request->transfer_date,
                'status' => 'pending',
                'notes' => $request->notes,
                'user_id' => $user->id,
            ]);

            // Create transfer items and check stock availability
            foreach ($request->items as $item) {
                $fromStock = ProductStock::where('product_id', $item['product_id'])
                                        ->where('outlet_id', $request->from_outlet_id)
                                        ->first();

                if (!$fromStock || $fromStock->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product ID {$item['product_id']}");
                }

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            $transfer->load(['fromOutlet', 'toOutlet', 'stockTransferItems.product', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer created successfully',
                'data' => $transfer
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load(['fromOutlet', 'toOutlet', 'user', 'stockTransferItems.product', 'items.product']);

        return response()->json([
            'success' => true,
            'data' => $stockTransfer
        ]);
    }

    /**
     * Approve and execute stock transfer
     */
    public function approve(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.transfer')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($stockTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Transfer can only be approved when status is pending'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Process each transfer item
            foreach ($stockTransfer->stockTransferItems as $item) {
                // Reduce stock from source outlet
                $fromStock = ProductStock::where('product_id', $item->product_id)
                                        ->where('outlet_id', $stockTransfer->from_outlet_id)
                                        ->first();

                if (!$fromStock || $fromStock->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for product ID {$item->product_id}");
                }

                $fromStock->reduceStock(
                    $item->quantity,
                    'transfer',
                    StockTransfer::class,
                    $stockTransfer->id,
                    "Transfer to outlet {$stockTransfer->toOutlet->name}"
                );

                // Add stock to destination outlet
                $toStock = ProductStock::where('product_id', $item->product_id)
                                      ->where('outlet_id', $stockTransfer->to_outlet_id)
                                      ->first();

                if (!$toStock) {
                    $toStock = ProductStock::create([
                        'product_id' => $item->product_id,
                        'outlet_id' => $stockTransfer->to_outlet_id,
                        'quantity' => 0,
                    ]);
                }

                $toStock->addStock(
                    $item->quantity,
                    'transfer',
                    StockTransfer::class,
                    $stockTransfer->id,
                    "Transfer from outlet {$stockTransfer->fromOutlet->name}"
                );
            }

            // Update transfer status
            $stockTransfer->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer approved and completed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve stock transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel stock transfer
     */
    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.transfer')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($stockTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending transfers can be cancelled'
            ], 422);
        }

        $stockTransfer->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Stock transfer cancelled successfully'
        ]);
    }
}
