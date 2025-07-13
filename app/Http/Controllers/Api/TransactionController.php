<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with(['customer', 'outlet', 'user', 'transactionItems.product']);

        // Filter by outlet
        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Filter by user (cashier)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Search by transaction number
        if ($request->has('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%');
        }

        $perPage = $request->get('per_page', 15);
        $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Auto-set outlet if not provided
        $outletId = $request->outlet_id ?? $user->outlet_id ?? 1; // Default to outlet 1 if no outlet assigned

        // Log transaction request details
        \Log::info('Creating new transaction:', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'outlet_id' => $outletId,
            'customer_id' => $request->customer_id,
            'total_amount' => $request->total_amount,
            'items_count' => count($request->items),
            'items' => $request->items
        ]);

        DB::beginTransaction();
        try {
            // Create transaction
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateTransactionNumber(),
                'outlet_id' => $outletId,
                'customer_id' => $request->customer_id,
                'user_id' => $user->id,
                'transaction_date' => $request->transaction_date ?? now()->toDateString(),
                'subtotal' => 0,
                'discount_amount' => $request->discount_amount ?? 0,
                'tax_amount' => $request->tax_amount ?? 0,
                'total_amount' => 0,
                'paid_amount' => $request->paid_amount,
                'change_amount' => 0,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'notes' => $request->notes,
            ]);

            $subtotal = 0;

            // Create transaction items and reduce stock
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Log transaction item details
                \Log::info('Processing transaction item:', [
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'requested_quantity' => $item['quantity'],
                    'outlet_id' => $outletId
                ]);

                // Check stock availability - use the determined outlet ID
                $productStock = ProductStock::where('product_id', $product->id)
                                          ->where('outlet_id', $outletId)
                                          ->first();

                // Log stock information
                if ($productStock) {
                    \Log::info('Stock found:', [
                        'product_id' => $product->id,
                        'outlet_id' => $outletId,
                        'available_stock' => $productStock->quantity,
                        'requested_quantity' => $item['quantity']
                    ]);
                } else {
                    \Log::warning('No stock record found:', [
                        'product_id' => $product->id,
                        'outlet_id' => $outletId
                    ]);
                }

                // Enhanced stock validation
                if (!$productStock) {
                    throw new \Exception("No stock record found for product: {$product->name} at outlet ID: {$outletId}");
                }

                if ($productStock->quantity < $item['quantity']) {
                    \Log::error('Insufficient stock:', [
                        'product_name' => $product->name,
                        'available_stock' => $productStock->quantity,
                        'requested_quantity' => $item['quantity'],
                        'shortage' => $item['quantity'] - $productStock->quantity
                    ]);

                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$productStock->quantity}, Requested: {$item['quantity']}");
                }

                // Calculate item total
                $unitPrice = $item['unit_price'] ?? $product->selling_price;
                $itemDiscount = $item['discount_amount'] ?? 0;
                $totalPrice = ($unitPrice * $item['quantity']) - $itemDiscount;

                // Create transaction item
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'discount_amount' => $itemDiscount,
                    'total_price' => $totalPrice,
                ]);

                // Reduce stock
                $productStock->reduceStock(
                    $item['quantity'],
                    'out',
                    Transaction::class,
                    $transaction->id,
                    "Sale transaction {$transaction->transaction_number}"
                );

                $subtotal += $totalPrice;
            }

            // Update transaction totals
            $transaction->subtotal = $subtotal;
            $transaction->total_amount = $subtotal + $transaction->tax_amount - $transaction->discount_amount;
            $transaction->change_amount = $transaction->paid_amount - $transaction->total_amount;
            $transaction->save();

            // Add loyalty points if customer exists
            if ($transaction->customer_id) {
                $customer = Customer::find($transaction->customer_id);
                $loyaltyRate = \App\Models\Setting::get('loyalty_points_rate', 100);
                $points = floor($transaction->total_amount / $loyaltyRate);
                if ($points > 0) {
                    $customer->addLoyaltyPoints($points);
                }
            }

            DB::commit();

            $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            // Log transaction error
            \Log::error('Failed to create transaction', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['customer', 'outlet', 'user', 'transactionItems.product.category', 'transactionItems.product.unit']);

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Refund transaction
     */
    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('transactions.refund')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($transaction->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed transactions can be refunded'
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Return stock for each item
            foreach ($transaction->transactionItems as $item) {
                $productStock = ProductStock::where('product_id', $item->product_id)
                                          ->where('outlet_id', $transaction->outlet_id)
                                          ->first();

                if ($productStock) {
                    $productStock->addStock(
                        $item->quantity,
                        'in',
                        Transaction::class,
                        $transaction->id,
                        "Refund transaction {$transaction->transaction_number}"
                    );
                }
            }

            // Deduct loyalty points if customer exists
            if ($transaction->customer_id) {
                $customer = Customer::find($transaction->customer_id);
                $loyaltyRate = \App\Models\Setting::get('loyalty_points_rate', 100);
                $points = floor($transaction->total_amount / $loyaltyRate);
                if ($points > 0) {
                    $customer->deductLoyaltyPoints($points);
                }
            }

            // Update transaction status
            $transaction->update([
                'status' => 'refunded',
                'notes' => ($transaction->notes ?? '') . "\nRefund reason: " . $request->reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction refunded successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to refund transaction: ' . $e->getMessage()
            ], 500);
        }
    }
}
