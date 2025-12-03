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
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Build query with eager loading
            $query = Transaction::with([
                'customer:id,name,email,phone',
                'outlet:id,name',
                'user:id,name,email',
                'transactionItems:id,transaction_id,product_id,quantity,unit_price,total_price',
                'transactionItems.product:id,name,sku'
            ]);

            // Filter by outlet
            if ($request->has('outlet_id') && $request->outlet_id) {
                $query->where('outlet_id', $request->outlet_id);
            }

            // Filter by user (cashier)
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Filter by customer
            if ($request->has('customer_id') && $request->customer_id) {
                $query->where('customer_id', $request->customer_id);
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by payment method
            if ($request->has('payment_method') && $request->payment_method) {
                $query->where('payment_method', $request->payment_method);
            }

            // Filter by date range (database agnostic)
            if ($request->has('date_from') && $request->date_from) {
                $query->where('transaction_date', '>=', $request->date_from . ' 00:00:00');
            }

            if ($request->has('date_to') && $request->date_to) {
                $query->where('transaction_date', '<=', $request->date_to . ' 23:59:59');
            }

            // Search by transaction number
            if ($request->has('search') && $request->search) {
                $query->where('transaction_number', 'like', '%' . $request->search . '%');
            }

            $perPage = $request->get('per_page', 15);
            $transactions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching transactions', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();

        // Auto-set outlet if not provided
        $outletId = $request->outlet_id ?? $user->outlet_id ?? 1; // Default to outlet 1 if no outlet assigned

        // Log transaction request details
        Log::info('Creating new transaction:', [
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
            // Parse transaction_date if provided, or use current datetime
            // Frontend sends local time in format YYYY-MM-DDTHH:mm:ss (no timezone)
            // Parse it as local time and store as-is
            if ($request->transaction_date) {
                // Parse the date string (format: YYYY-MM-DDTHH:mm:ss or YYYY-MM-DD HH:mm:ss)
                $dateString = str_replace('T', ' ', $request->transaction_date);
                // Ensure it has seconds if not present
                if (strlen($dateString) === 16) { // YYYY-MM-DD HH:mm
                    $dateString .= ':00';
                }
                $transactionDate = \Carbon\Carbon::parse($dateString);
            } else {
                $transactionDate = now();
            }

            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateTransactionNumber(),
                'outlet_id' => $outletId,
                'customer_id' => $request->customer_id,
                'user_id' => $user->id,
                'transaction_date' => $transactionDate,
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
                Log::info('Processing transaction item:', [
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
                    Log::info('Stock found:', [
                        'product_id' => $product->id,
                        'outlet_id' => $outletId,
                        'available_stock' => $productStock->quantity,
                        'requested_quantity' => $item['quantity']
                    ]);
                } else {
                    Log::warning('No stock record found:', [
                        'product_id' => $product->id,
                        'outlet_id' => $outletId
                    ]);
                }

                // Enhanced stock validation
                if (!$productStock) {
                    throw new \Exception("No stock record found for product: {$product->name} at outlet ID: {$outletId}");
                }

                if ($productStock->quantity < $item['quantity']) {
                    Log::error('Insufficient stock:', [
                        'product_name' => $product->name,
                        'available_stock' => $productStock->quantity,
                        'requested_quantity' => $item['quantity'],
                        'shortage' => $item['quantity'] - $productStock->quantity
                    ]);

                    throw new \Exception("Insufficient stock for product: {$product->name}. Available: {$productStock->quantity}, Requested: {$item['quantity']}");
                }

                // Calculate item total
                // Use the unit_price sent from frontend (respects wholesale price selection)
                // Only fallback to product selling_price if unit_price is not provided
                $unitPrice = isset($item['unit_price']) && $item['unit_price'] > 0
                    ? (float) $item['unit_price']
                    : $product->selling_price;
                $itemDiscount = $item['discount_amount'] ?? 0;
                $totalPrice = ($unitPrice * $item['quantity']) - $itemDiscount;

                // Log for debugging wholesale price usage
                if (isset($item['unit_price']) && abs($item['unit_price'] - $product->selling_price) > 0.01) {
                    Log::info('Using custom unit price (wholesale):', [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'selling_price' => $product->selling_price,
                        'wholesale_price' => $product->wholesale_price,
                        'unit_price_sent' => $item['unit_price'] ?? null,
                        'unit_price_used' => $unitPrice,
                    ]);
                }

                // Create transaction item with snapshot of purchase price
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'purchase_price' => $product->purchase_price, // Store snapshot of purchase price at transaction time
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
                // Use new loyalty_points_per_rupiah (backward compatible with loyalty_points_rate)
                $pointsPerRupiah = \App\Models\Setting::get('loyalty_points_per_rupiah', null);
                if ($pointsPerRupiah === null) {
                    // Fallback to old loyalty_points_rate for backward compatibility
                    $pointsPerRupiah = \App\Models\Setting::get('loyalty_points_rate', 200);
                }
                $points = floor($transaction->total_amount / $pointsPerRupiah);
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
            Log::error('Failed to create transaction', [
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
        try {
            $transaction->load([
                'customer:id,name,email,phone',
                'outlet:id,name',
                'user:id,name,email',
                'transactionItems:id,transaction_id,product_id,quantity,unit_price,total_price',
                'transactionItems.product:id,name,sku,selling_price,purchase_price',
                'transactionItems.product.category:id,name',
                'transactionItems.product.unit:id,name'
            ]);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching transaction detail', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund transaction
     */
    public function refund(Request $request, Transaction $transaction): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        if (!$user->can('transactions.refund')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing transactions.refund permission'
            ], 403);
        }

        if ($transaction->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed transactions can be refunded'
            ], 422);
        }

        // Check if refund is enabled
        $refundEnabled = \App\Models\Setting::get('refund_enabled', true);
        if (!$refundEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'Refund feature is currently disabled'
            ], 422);
        }

        // Role-based refund time limit
        $isAdmin = $user->hasRole(['Super Admin', 'Admin', 'Manager']);
        $isCashier = $user->hasRole('Cashier');

        $transactionDate = \Carbon\Carbon::parse($transaction->transaction_date);

        // Kasir hanya bisa refund transaksi hari ini
        if ($isCashier) {
            $sameDayOnly = \App\Models\Setting::get('refund_allow_same_day_only_for_cashier', true);
            if ($sameDayOnly) {
                if (!$transactionDate->isToday()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda hanya bisa melakukan refund untuk transaksi hari ini'
                    ], 422);
                }
            }
        }
        // Admin/Manager bisa refund dengan batasan waktu (jika di-set)
        else {
            $refundDaysLimit = \App\Models\Setting::get('refund_days_limit', 7);

            // Jika limit = 0, berarti tidak ada batasan (admin bisa refund kapan saja)
            if ($refundDaysLimit > 0) {
                $daysSinceTransaction = now()->diffInDays($transactionDate);

                if ($daysSinceTransaction > $refundDaysLimit) {
                    return response()->json([
                        'success' => false,
                        'message' => "Transaksi hanya bisa di-refund dalam {$refundDaysLimit} hari sejak transaksi dibuat. Transaksi ini sudah {$daysSinceTransaction} hari."
                    ], 422);
                }
            }
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
                // Use new loyalty_points_per_rupiah (backward compatible with loyalty_points_rate)
                $pointsPerRupiah = \App\Models\Setting::get('loyalty_points_per_rupiah', null);
                if ($pointsPerRupiah === null) {
                    // Fallback to old loyalty_points_rate for backward compatibility
                    $pointsPerRupiah = \App\Models\Setting::get('loyalty_points_rate', 200);
                }
                $points = floor($transaction->total_amount / $pointsPerRupiah);
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
