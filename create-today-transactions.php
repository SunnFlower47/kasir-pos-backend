<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Outlet;

echo "💰 CREATING TODAY'S TRANSACTIONS\n";
echo "=================================\n\n";

$products = Product::all();
$customers = Customer::all();
$outlets = Outlet::all();

if ($products->count() == 0) {
    echo "❌ No products found. Run sample-products first.\n";
    exit;
}

$today = now();
$paymentMethods = ['cash', 'transfer', 'qris', 'e_wallet'];

echo "📅 Creating transactions for: " . $today->format('Y-m-d') . "\n\n";

// Create 10 transactions for today with different times
for ($i = 1; $i <= 10; $i++) {
    echo "Creating transaction $i...\n";
    
    // Random time during business hours (8 AM - 8 PM)
    $hour = rand(8, 20);
    $minute = rand(0, 59);
    $transactionTime = $today->copy()->setTime($hour, $minute);
    
    $transaction = Transaction::create([
        'transaction_number' => Transaction::generateTransactionNumber(),
        'customer_id' => $customers->count() > 0 && rand(0, 1) ? $customers->random()->id : null,
        'outlet_id' => $outlets->random()->id,
        'user_id' => rand(1, 5),
        'transaction_date' => $transactionTime,
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => rand(0, 1) ? rand(1000, 3000) : 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'change_amount' => 0,
        'payment_method' => $paymentMethods[array_rand($paymentMethods)],
        'status' => 'completed',
        'notes' => rand(0, 1) ? 'Sample transaction for testing' : null,
    ]);
    
    // Add 1-3 items per transaction
    $itemCount = rand(1, 3);
    $subtotal = 0;
    
    $usedProducts = [];
    for ($j = 0; $j < $itemCount; $j++) {
        // Avoid duplicate products in same transaction
        do {
            $product = $products->random();
        } while (in_array($product->id, $usedProducts) && count($usedProducts) < $products->count());
        
        $usedProducts[] = $product->id;
        
        $quantity = rand(1, 3);
        $price = $product->selling_price;
        $total = $quantity * $price;
        
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'discount_amount' => 0,
            'total_price' => $total,
        ]);
        
        $subtotal += $total;
    }
    
    // Calculate totals
    $tax = $subtotal * 0.1; // 10% tax
    $total = $subtotal + $tax - $transaction->discount_amount;
    $paid = $total + rand(0, 5000); // Sometimes overpay
    $change = $paid - $total;
    
    $transaction->update([
        'subtotal' => $subtotal,
        'tax_amount' => $tax,
        'total_amount' => $total,
        'paid_amount' => $paid,
        'change_amount' => $change,
    ]);
    
    echo "✅ Transaction $i: " . $transaction->transaction_number . " - Rp " . number_format($total) . " at " . $transactionTime->format('H:i') . "\n";
}

echo "\n📊 TODAY'S SUMMARY\n";
echo "==================\n";

$todayTransactions = Transaction::whereDate('transaction_date', $today)->get();
$todayRevenue = $todayTransactions->sum('total_amount');
$todayCount = $todayTransactions->count();
$avgTransaction = $todayCount > 0 ? $todayRevenue / $todayCount : 0;

echo "Date: " . $today->format('Y-m-d') . "\n";
echo "Transactions: " . $todayCount . "\n";
echo "Total Revenue: Rp " . number_format($todayRevenue) . "\n";
echo "Average Transaction: Rp " . number_format($avgTransaction) . "\n";
echo "Payment Methods: " . $todayTransactions->pluck('payment_method')->unique()->implode(', ') . "\n";
echo "Outlets: " . $todayTransactions->pluck('outlet_id')->unique()->count() . " outlets\n";

echo "\n🎯 TESTING INSTRUCTIONS\n";
echo "=======================\n";
echo "1. Open Dashboard: http://localhost:3000/dashboard\n";
echo "2. Open Reports: http://localhost:3000/reports\n";
echo "3. Compare the data between both pages\n";
echo "4. Check if numbers match or differ\n\n";

echo "🔍 WHAT TO LOOK FOR:\n";
echo "- Total revenue numbers\n";
echo "- Transaction counts\n";
echo "- Date filtering\n";
echo "- Data source differences\n";
echo "- API endpoint differences\n\n";

echo "✅ Ready for comparison testing!\n";
