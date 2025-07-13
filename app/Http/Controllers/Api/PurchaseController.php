<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Purchase::with(['supplier', 'outlet', 'user', 'purchaseItems.product']);

        // Filter by outlet
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Filter by supplier
        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('purchase_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('purchase_date', '<=', $request->date_to);
        }

        // Search by invoice number
        if ($request->has('search')) {
            $query->where('invoice_number', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->get('per_page', 15);
        $purchases = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'outlet_id' => 'required|exists:outlets,id',
            'purchase_date' => 'required|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',

            // Purchase items
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        \Log::info('Creating purchase with data:', $request->all());

        DB::beginTransaction();
        try {
            // Create purchase
            $purchase = Purchase::create([
                'invoice_number' => Purchase::generateInvoiceNumber(),
                'supplier_id' => $request->supplier_id,
                'outlet_id' => $request->outlet_id,
                'purchase_date' => $request->purchase_date,
                'subtotal' => 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'total_amount' => 0,
                'paid_amount' => $request->paid_amount ?? 0,
                'remaining_amount' => 0,
                'status' => 'pending',
                'notes' => $request->notes,
                'user_id' => $user->id,
            ]);

            $subtotal = 0;

            // Create purchase items and add stock
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $totalPrice = $item['unit_price'] * $item['quantity'];

                // Create purchase item
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $totalPrice,
                ]);

                // Add stock
                $productStock = ProductStock::where('product_id', $product->id)
                                          ->where('outlet_id', $request->outlet_id)
                                          ->first();

                if (!$productStock) {
                    $productStock = ProductStock::create([
                        'product_id' => $product->id,
                        'outlet_id' => $request->outlet_id,
                        'quantity' => 0,
                    ]);
                }

                $productStock->addStock(
                    $item['quantity'],
                    'in',
                    Purchase::class,
                    $purchase->id,
                    "Purchase from supplier ID: {$purchase->supplier_id}"
                );

                $subtotal += $totalPrice;
            }

            // Update purchase totals
            $purchase->subtotal = $subtotal;
            $purchase->total_amount = $subtotal + $purchase->tax_amount - $purchase->discount_amount;
            $purchase->remaining_amount = $purchase->total_amount - $purchase->paid_amount;

            // Update status based on payment
            if ($purchase->paid_amount >= $purchase->total_amount) {
                $purchase->status = 'paid';
            } elseif ($purchase->paid_amount > 0) {
                $purchase->status = 'partial';
            }

            $purchase->save();

            DB::commit();

            $purchase->load(['supplier', 'outlet', 'user', 'purchaseItems.product']);

            return response()->json([
                'success' => true,
                'message' => 'Purchase created successfully',
                'data' => $purchase
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchase $purchase): JsonResponse
    {
        $purchase->load(['supplier', 'outlet', 'user', 'purchaseItems.product.category', 'purchaseItems.product.unit']);

        return response()->json([
            'success' => true,
            'data' => $purchase
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($purchase->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit paid purchase'
            ], 422);
        }

        $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:pending,partial,paid,cancelled',
        ]);

        $oldPaidAmount = $purchase->paid_amount;
        $newPaidAmount = $request->paid_amount ?? $oldPaidAmount;

        $purchase->update([
            'paid_amount' => $newPaidAmount,
            'remaining_amount' => $purchase->total_amount - $newPaidAmount,
            'notes' => $request->notes ?? $purchase->notes,
        ]);

        // Update status - allow manual status override or auto-calculate
        if ($request->has('status')) {
            $purchase->status = $request->status;
        } else {
            // Auto-calculate status based on payment
            if ($purchase->paid_amount >= $purchase->total_amount) {
                $purchase->status = 'paid';
            } elseif ($purchase->paid_amount > 0) {
                $purchase->status = 'partial';
            } else {
                $purchase->status = 'pending';
            }
        }

        $purchase->save();

        return response()->json([
            'success' => true,
            'message' => 'Purchase updated successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Update purchase status
     */
    public function updateStatus(Request $request, Purchase $purchase): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,partial,paid,cancelled',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $purchase->status;
        $newStatus = $request->status;

        // Prevent certain status changes
        if ($oldStatus === 'paid' && $newStatus !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status of paid purchase'
            ], 422);
        }

        $purchase->update([
            'status' => $newStatus,
            'notes' => $request->notes ?? $purchase->notes,
        ]);

        // Auto-adjust paid amount for certain status changes
        if ($newStatus === 'paid' && $purchase->paid_amount < $purchase->total_amount) {
            $purchase->update([
                'paid_amount' => $purchase->total_amount,
                'remaining_amount' => 0,
            ]);
        } elseif ($newStatus === 'cancelled') {
            // Don't change payment amounts for cancelled purchases
        }

        $purchase->load(['supplier', 'outlet', 'user', 'purchaseItems.product']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase status updated successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($purchase->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending purchases can be deleted'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Reduce stock for each item
            foreach ($purchase->purchaseItems as $item) {
                $productStock = ProductStock::where('product_id', $item->product_id)
                                          ->where('outlet_id', $purchase->outlet_id)
                                          ->first();

                if ($productStock) {
                    $productStock->reduceStock(
                        $item->quantity,
                        'out',
                        Purchase::class,
                        $purchase->id,
                        "Purchase deletion: {$purchase->invoice_number}"
                    );
                }
            }

            $purchase->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete purchase: ' . $e->getMessage()
            ], 500);
        }
    }
}
