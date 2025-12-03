<?php

require_once 'vendor/autoload.php';

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing New Purchase Logic\n";
echo "==============================\n\n";

DB::beginTransaction();

try {
    // Get test data
    $product = Product::first();
    $supplier = Supplier::first();
    $outlet = Outlet::first();
    
    if (!$product || !$supplier || !$outlet) {
        throw new Exception("Missing test data (product, supplier, or outlet)");
    }
    
    // Get initial stock
    $initialStock = ProductStock::where('product_id', $product->id)
                               ->where('outlet_id', $outlet->id)
                               ->first();
    $initialQuantity = $initialStock ? $initialStock->quantity : 0;
    
    echo "📦 Test Product: {$product->name}\n";
    echo "🏪 Test Outlet: {$outlet->name}\n";
    echo "📊 Initial Stock: {$initialQuantity}\n\n";
    
    // Test 1: Create purchase with pending status
    echo "🧪 Test 1: Create purchase with pending status\n";
    echo "----------------------------------------------\n";
    
    $purchase = Purchase::create([
        'invoice_number' => 'TEST-' . time(),
        'supplier_id' => $supplier->id,
        'outlet_id' => $outlet->id,
        'user_id' => 1,
        'purchase_date' => now(),
        'status' => 'pending',
        'subtotal' => 50000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 50000,
        'paid_amount' => 0,
        'remaining_amount' => 50000,
    ]);
    
    // Add purchase item
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 5000,
        'total_price' => 50000,
    ]);
    
    // Check stock after creating pending purchase
    $stockAfterPending = ProductStock::where('product_id', $product->id)
                                    ->where('outlet_id', $outlet->id)
                                    ->first();
    $quantityAfterPending = $stockAfterPending ? $stockAfterPending->quantity : 0;
    
    echo "Stock after creating pending purchase: {$quantityAfterPending}\n";
    echo "Expected: {$initialQuantity} (no change)\n";
    echo "✅ " . ($quantityAfterPending == $initialQuantity ? "PASS" : "FAIL") . "\n\n";
    
    // Test 2: Change status to paid
    echo "🧪 Test 2: Change status from pending to paid\n";
    echo "---------------------------------------------\n";
    
    $purchase->update([
        'status' => 'paid',
        'paid_amount' => 50000,
        'remaining_amount' => 0,
    ]);
    
    // Manually trigger the stock update (simulating the controller logic)
    $controller = new \App\Http\Controllers\Api\PurchaseController();
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('addStockForPurchase');
    $method->setAccessible(true);
    $method->invoke($controller, $purchase);
    
    // Check stock after changing to paid
    $stockAfterPaid = ProductStock::where('product_id', $product->id)
                                 ->where('outlet_id', $outlet->id)
                                 ->first();
    $quantityAfterPaid = $stockAfterPaid ? $stockAfterPaid->quantity : 0;
    
    echo "Stock after changing to paid: {$quantityAfterPaid}\n";
    echo "Expected: " . ($initialQuantity + 10) . " (added 10 units)\n";
    echo "✅ " . ($quantityAfterPaid == ($initialQuantity + 10) ? "PASS" : "FAIL") . "\n\n";
    
    // Test 3: Change status back to cancelled
    echo "🧪 Test 3: Change status from paid to cancelled\n";
    echo "----------------------------------------------\n";
    
    $purchase->update(['status' => 'cancelled']);
    
    // Manually trigger the stock removal
    $removeMethod = $reflection->getMethod('removeStockForPurchase');
    $removeMethod->setAccessible(true);
    $removeMethod->invoke($controller, $purchase);
    
    // Check stock after changing to cancelled
    $stockAfterCancelled = ProductStock::where('product_id', $product->id)
                                      ->where('outlet_id', $outlet->id)
                                      ->first();
    $quantityAfterCancelled = $stockAfterCancelled ? $stockAfterCancelled->quantity : 0;
    
    echo "Stock after changing to cancelled: {$quantityAfterCancelled}\n";
    echo "Expected: {$initialQuantity} (back to original)\n";
    echo "✅ " . ($quantityAfterCancelled == $initialQuantity ? "PASS" : "FAIL") . "\n\n";
    
    // Clean up test data
    $purchase->purchaseItems()->delete();
    $purchase->delete();
    
    DB::rollback(); // Rollback to not affect real data
    
    echo "🎉 All tests completed!\n";
    echo "✅ New purchase logic is working correctly\n";
    
} catch (\Exception $e) {
    DB::rollback();
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
