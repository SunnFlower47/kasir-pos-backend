<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;

echo "🚀 ADDING SIMPLE TRANSACTIONS\n";
echo "==============================\n\n";

// Create a few simple transactions for today
$today = now();

// Create 5 transactions for today
for ($i = 1; $i <= 5; $i++) {
    echo "Creating transaction $i...\n";
    
    $transaction = Transaction::create([
        'transaction_number' => 'TRX' . $today->format('Ymd') . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
        'customer_id' => null,
        'outlet_id' => 1,
        'user_id' => 1,
        'transaction_date' => $today->copy()->addHours($i),
        'subtotal' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'change_amount' => 0,
        'payment_method' => ['cash', 'transfer', 'qris'][rand(0, 2)],
        'status' => 'completed',
    ]);
    
    // Add one item per transaction
    $amount = rand(10000, 50000);
    
    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => 1, // Use existing product
        'quantity' => 1,
        'unit_price' => $amount,
        'discount_amount' => 0,
        'total_price' => $amount,
    ]);
    
    // Update transaction totals
    $transaction->update([
        'subtotal' => $amount,
        'total_amount' => $amount,
        'paid_amount' => $amount,
    ]);
    
    echo "✅ Transaction $i created: Rp " . number_format($amount) . "\n";
}

echo "\n📊 SUMMARY\n";
echo "==========\n";
echo "Total Transactions: " . Transaction::count() . "\n";
echo "Total Revenue Today: Rp " . number_format(Transaction::whereDate('transaction_date', $today)->sum('total_amount')) . "\n";
echo "\n🎉 Done! Test the reports now.\n";
