<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductStock;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Added for logging
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'purchaseItems.product', 'outlet']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('purchase_date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $purchases = $query->latest()->paginate($request->per_page ?? 10);
        
        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
    
        try {
            $request->validate([
                'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
                'outlet_id' => ['required', Rule::exists('outlets', 'id')->where('tenant_id', $tenantId)],
                'purchase_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.purchase_price' => 'required|numeric|min:0',
                'items.*.unit_id' => 'nullable|exists:units,id',
                'items.*.conversion_factor' => 'nullable|numeric|min:0.001',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Purchase validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['purchase_price'];
            }

            // Create Purchase
            // Create Purchase
            $purchase = Purchase::create([
                'invoice_number' => 'PO-' . time(),
                'supplier_id' => $request->supplier_id,
                'outlet_id' => $request->outlet_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'status' => 'pending', // Default status pending
                'notes' => $request->notes,
                'user_id' => $request->user()->id,
            ]);

            // Create Items
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['purchase_price'],
                    'total_price' => $item['quantity'] * $item['purchase_price'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                ]);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase created successfully',
                'data' => $purchase->load(['purchaseItems.product', 'purchaseItems.unit', 'outlet'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error creating purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Purchase $purchase)
    {
        return response()->json([
            'success' => true,
            'data' => $purchase->load(['supplier', 'purchaseItems.product', 'purchaseItems.unit', 'user', 'outlet'])
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Purchase $purchase)
    {
        // Only allow update if status is pending
        if ($purchase->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update purchase that is already processed (received/cancelled)'
            ], 400);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'outlet_id' => 'required|exists:outlets,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.001',
        ]);

        try {
            DB::beginTransaction();

            // Calculate new total
            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += $item['quantity'] * $item['purchase_price'];
            }

            // Update Purchase Header
            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'outlet_id' => $request->outlet_id,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            // Delete old items
            $purchase->purchaseItems()->delete();

            // Create new items
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['purchase_price'],
                    'total_price' => $item['quantity'] * $item['purchase_price'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'conversion_factor' => $item['conversion_factor'] ?? 1,
                ]);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase updated successfully',
                'data' => $purchase->load(['purchaseItems.product', 'purchaseItems.unit', 'outlet'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error updating purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        if ($purchase->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete purchase that is already processed'
            ], 400);
        }

        try {
            $purchase->purchaseItems()->delete();
            $purchase->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase deleted successfully'
            ]);
        } catch (\Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Error deleting purchase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status (e.g. Receive items)
     */
    public function updateStatus(Request $request, Purchase $purchase)
    {
        $request->validate([
            'status' => 'required|in:pending,paid,cancelled',
        ]);

        if ($purchase->status === $request->status) {
             return response()->json([
                'success' => true,
                'message' => 'Status is already ' . $request->status,
                'data' => $purchase
            ]);
        }

        // Logic for transitioning TO 'paid' (Received + Paid)
        if ($request->status === 'paid' && $purchase->status === 'pending') {
            try {
                DB::beginTransaction();

                // Update stock using StockController logic (or direct model manipulation)
                // We'll update product stocks here directly for simplicity, but ideally use a Service
                
                $items = $purchase->purchaseItems;
                foreach ($items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        // Update stock logic would go here if we tracked stock
                        // For now we just create a stock movement record
                        
                        // Find or create product stock record for this outlet
                        $productStock = ProductStock::firstOrCreate(
                            [
                                'product_id' => $item->product_id,
                                'outlet_id' => $purchase->outlet_id
                            ],
                            ['quantity' => 0]
                        );

                        // Calculate total quantity to add based on conversion factor
                        $qtyToAdd = $item->quantity * ($item->conversion_factor > 0 ? $item->conversion_factor : 1);

                        // Use the model's method to add stock and create movement record
                        $productStock->addStock(
                            quantity: $qtyToAdd,
                            type: 'in',
                            referenceType: 'purchase',
                            referenceId: $purchase->id,
                            notes: 'Received from PO ' . $purchase->invoice_number
                        );
                        
                        // Update product basic stock or price if needed
                         // $product->purchase_price = $item->purchase_price;
                         // $product->save();
                    }
                }

                $purchase->update(['status' => 'paid']);
                
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Purchase received and stock updated',
                    'data' => $purchase
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing purchase: ' . $e->getMessage()
                ], 500);
            }
        }
        
        // Generic status update
        $purchase->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $purchase
        ]);
    }

    /**
     * Print Purchase Invoice (Public)
     * 
     * @param Purchase $purchase
     * @return \Illuminate\Http\Response
     */
    public function print(Purchase $purchase)
    {
        // IMPORTANT: In production, verify user can access this purchase via tenant_id or other means
        // Since this is a public route (often opened in new tab), we rely on UUID or obscure ID if possible.
        // For now, standard ID binding.
        
        // Eager load relationships
        // Eager load relationships
        $purchase->load(['supplier', 'purchaseItems.product', 'purchaseItems.unit', 'user', 'outlet']);

        // Fetch company details
        $company = [
            'name' => \App\Models\Setting::get('company_name', 'Kasir App'),
            'address' => \App\Models\Setting::get('company_address', '-'),
            'phone' => \App\Models\Setting::get('company_phone', '-'),
            'email' => \App\Models\Setting::get('company_email', '-'),
            'website' => \App\Models\Setting::get('company_website', null),
            'logo' => \App\Models\Setting::get('company_logo', null),
        ];

        // Fetch currency settings
        $currency_symbol = \App\Models\Setting::get('currency_symbol', 'Rp');
        $currency_position = \App\Models\Setting::get('currency_position', 'before');
        $decimal_places = \App\Models\Setting::get('decimal_places', 0);

        // Return a simple HTML view for printing
        return view('purchases.print', compact('purchase', 'company', 'currency_symbol', 'currency_position', 'decimal_places'));
    }
}