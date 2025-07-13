<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Get stock overview for specific outlet
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'search' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'low_stock_only' => 'nullable|boolean',
        ]);

        $user = Auth::user();
        $query = ProductStock::with(['product.category', 'product.unit', 'outlet']);

        // Filter by outlet if specified
        if ($request->has('outlet_id') && $request->outlet_id) {
            $query->where('outlet_id', $request->outlet_id);
        }
        // Note: Removed automatic filtering by user outlet_id to allow viewing all outlets
        // Admin and super admin should be able to see all outlets when no filter is applied

        // Add consistent ordering to prevent random results
        $query->orderBy('product_id', 'asc')->orderBy('outlet_id', 'asc');

        // Search by product name or SKU
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Filter low stock only
        if ($request->boolean('low_stock_only')) {
            $query->whereRaw('quantity <= (SELECT min_stock FROM products WHERE products.id = product_stocks.product_id)');
        }

        $perPage = $request->get('per_page', 15);
        $stocks = $query->paginate($perPage);

        // Add low stock indicator
        $stocks->getCollection()->transform(function ($stock) {
            $stock->is_low_stock = $stock->quantity <= $stock->product->min_stock;
            return $stock;
        });

        return response()->json([
            'success' => true,
            'data' => $stocks
        ]);
    }

    /**
     * Adjust stock quantity (stock opname)
     */
    public function adjust(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.adjustment')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'outlet_id' => 'required|exists:outlets,id',
            'new_quantity' => 'required|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $productStock = ProductStock::where('product_id', $request->product_id)
                                      ->where('outlet_id', $request->outlet_id)
                                      ->first();

            if (!$productStock) {
                // Create new stock record if doesn't exist
                $productStock = ProductStock::create([
                    'product_id' => $request->product_id,
                    'outlet_id' => $request->outlet_id,
                    'quantity' => 0,
                ]);
            }

            $oldQuantity = $productStock->quantity;
            $newQuantity = $request->new_quantity;
            $difference = $newQuantity - $oldQuantity;

            // Update stock
            $productStock->quantity = $newQuantity;
            $productStock->save();

            // Create stock movement record
            StockMovement::create([
                'product_id' => $request->product_id,
                'outlet_id' => $request->outlet_id,
                'type' => 'adjustment',
                'quantity' => $difference,
                'quantity_before' => $oldQuantity,
                'quantity_after' => $newQuantity,
                'notes' => $request->notes ?? 'Stock adjustment',
                'user_id' => $user->id,
            ]);

            DB::commit();

            $productStock->load(['product', 'outlet']);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjusted successfully',
                'data' => $productStock
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process stock opname (bulk stock adjustment)
     */
    public function opname(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.adjustment')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.system_stock' => 'required|integer|min:0',
            'items.*.physical_stock' => 'required|integer|min:0',
            'items.*.difference' => 'required|integer',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $processedItems = [];

            foreach ($request->items as $item) {
                // Skip items with no difference
                if ($item['difference'] == 0) {
                    continue;
                }

                // Find or create product stock record
                $productStock = ProductStock::firstOrCreate(
                    [
                        'product_id' => $item['product_id'],
                        'outlet_id' => $request->outlet_id
                    ],
                    ['quantity' => 0]
                );

                $oldQuantity = $productStock->quantity;
                $newQuantity = $item['physical_stock'];
                $difference = $newQuantity - $oldQuantity;

                // Update stock quantity
                $productStock->update(['quantity' => $newQuantity]);

                // Create stock movement record
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->outlet_id,
                    'type' => 'adjustment',
                    'quantity' => $difference, // Use actual difference (can be negative)
                    'quantity_before' => $oldQuantity,
                    'quantity_after' => $newQuantity,
                    'reference_type' => 'stock_opname',
                    'reference_id' => null,
                    'notes' => $item['notes'] ?? 'Stock opname adjustment',
                    'user_id' => $user->id,
                ]);

                $processedItems[] = [
                    'product_id' => $item['product_id'],
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'difference' => $difference
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock opname processed successfully',
                'data' => [
                    'outlet_id' => $request->outlet_id,
                    'processed_items' => $processedItems,
                    'total_items' => count($processedItems)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process stock opname: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movements history
     */
    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'product_id' => 'nullable|exists:products,id',
            'type' => 'nullable|in:in,out,adjustment,transfer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = StockMovement::with(['product', 'outlet', 'user']);

        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    /**
     * Process incoming stock from supplier
     */
    public function incoming(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('stocks.adjustment')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'outlet_id' => 'required|exists:outlets,id',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $item) {
                // Get or create product stock
                $productStock = ProductStock::firstOrCreate([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->outlet_id
                ], [
                    'quantity' => 0
                ]);

                $oldQuantity = $productStock->quantity;
                $newQuantity = $oldQuantity + $item['quantity'];

                // Update stock
                $productStock->quantity = $newQuantity;
                $productStock->save();

                // Create stock movement record
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->outlet_id,
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'quantity_before' => $oldQuantity,
                    'quantity_after' => $newQuantity,
                    'reference_type' => 'stock_incoming',
                    'reference_id' => $request->supplier_id,
                    'notes' => $item['notes'] ?? "Stock incoming from supplier - {$request->reference_number}",
                    'user_id' => $user->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock incoming processed successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process stock incoming: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer stock between outlets
     */
    public function transfer(Request $request): JsonResponse
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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();
        try {
            // Create transfer record
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF' . date('YmdHis') . rand(100, 999),
                'from_outlet_id' => $request->from_outlet_id,
                'to_outlet_id' => $request->to_outlet_id,
                'status' => 'completed',
                'notes' => $request->notes,
                'user_id' => $user->id,
                'transfer_date' => now()
            ]);

            foreach ($request->items as $item) {
                // Check source stock
                $sourceStock = ProductStock::where('product_id', $item['product_id'])
                                         ->where('outlet_id', $request->from_outlet_id)
                                         ->first();

                if (!$sourceStock || $sourceStock->quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product ID {$item['product_id']} at source outlet");
                }

                // Get or create destination stock
                $destStock = ProductStock::firstOrCreate([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->to_outlet_id
                ], [
                    'quantity' => 0
                ]);

                // Record transfer item
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity']
                ]);

                // Update stocks
                $sourceStock->quantity -= $item['quantity'];
                $sourceStock->save();

                $destStock->quantity += $item['quantity'];
                $destStock->save();

                // Create movement records
                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->from_outlet_id,
                    'type' => 'out',
                    'quantity' => -$item['quantity'],
                    'quantity_before' => $sourceStock->quantity + $item['quantity'],
                    'quantity_after' => $sourceStock->quantity,
                    'notes' => "Transfer out to outlet {$request->to_outlet_id} - {$transfer->transfer_number}",
                    'user_id' => $user->id,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id
                ]);

                StockMovement::create([
                    'product_id' => $item['product_id'],
                    'outlet_id' => $request->to_outlet_id,
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'quantity_before' => $destStock->quantity - $item['quantity'],
                    'quantity_after' => $destStock->quantity,
                    'notes' => "Transfer in from outlet {$request->from_outlet_id} - {$transfer->transfer_number}",
                    'user_id' => $user->id,
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id
                ]);
            }

            DB::commit();

            $transfer->load(['fromOutlet', 'toOutlet', 'items.product', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully',
                'data' => $transfer
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer stock: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = ProductStock::with(['product', 'outlet'])
                            ->whereRaw('quantity <= (SELECT min_stock FROM products WHERE products.id = product_stocks.product_id)')
                            ->where('quantity', '>', 0);

        // Filter by user's outlet if not admin
        if ($user && $user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        } elseif ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        $lowStocks = $query->orderBy('quantity', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $lowStocks,
            'count' => $lowStocks->count()
        ]);
    }
}
