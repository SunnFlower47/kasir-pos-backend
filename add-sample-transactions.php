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

echo "🚀 ADDING SAMPLE TRANSACTIONS\n";
echo "=============================\n\n";

// Create some sample products first
$products = [];

// Check if we have products
$existingProducts = Product::all();
if ($existingProducts->count() == 0) {
    echo "📦 Creating sample products...\n";

    $categories = \App\Models\Category::all();
    $units = \App\Models\Unit::all();
    $outlets = Outlet::all();

    $productData = [
        ['name' => 'Nasi Gudeg', 'price' => 15000, 'cost' => 10000],
        ['name' => 'Ayam Bakar', 'price' => 25000, 'cost' => 18000],
        ['name' => 'Es Teh Manis', 'price' => 5000, 'cost' => 2000],
        ['name' => 'Kopi Hitam', 'price' => 8000, 'cost' => 3000],
        ['name' => 'Sate Ayam', 'price' => 20000, 'cost' => 15000],
        ['name' => 'Gado-gado', 'price' => 12000, 'cost' => 8000],
        ['name' => 'Bakso', 'price' => 18000, 'cost' => 12000],
        ['name' => 'Mie Ayam', 'price' => 16000, 'cost' => 11000],
    ];

    foreach ($productData as $data) {
        $product = Product::create([
            'name' => $data['name'],
            'sku' => 'SKU' . rand(1000, 9999),
            'category_id' => $categories->random()->id,
            'unit_id' => $units->random()->id,
            'purchase_price' => $data['cost'],
            'selling_price' => $data['price'],
            'stock_quantity' => rand(50, 200),
            'min_stock' => 10,
            'outlet_id' => $outlets->random()->id,
            'is_active' => true,
        ]);
        $products[] = $product;
    }

    echo "✅ Created " . count($products) . " products\n\n";
} else {
    $products = $existingProducts->toArray();
    echo "✅ Using existing " . count($products) . " products\n\n";
}

// Create some sample customers
$customers = [];
$existingCustomers = Customer::all();
if ($existingCustomers->count() == 0) {
    echo "👥 Creating sample customers...\n";

    $customerData = [
        ['name' => 'Budi Santoso', 'phone' => '081234567890'],
        ['name' => 'Siti Nurhaliza', 'phone' => '081234567891'],
        ['name' => 'Ahmad Wijaya', 'phone' => '081234567892'],
        ['name' => 'Dewi Sartika', 'phone' => '081234567893'],
        ['name' => 'Rudi Hermawan', 'phone' => '081234567894'],
    ];

    foreach ($customerData as $data) {
        $customer = Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => strtolower(str_replace(' ', '.', $data['name'])) . '@example.com',
            'address' => 'Jl. Testing No. ' . rand(1, 100),
            'is_active' => true,
        ]);
        $customers[] = $customer;
    }

    echo "✅ Created " . count($customers) . " customers\n\n";
} else {
    $customers = $existingCustomers->toArray();
    echo "✅ Using existing " . count($customers) . " customers\n\n";
}

// Create transactions for the last 30 days
echo "💰 Creating sample transactions...\n";

$outlets = Outlet::all();
$paymentMethods = ['cash', 'transfer', 'qris', 'e_wallet'];

$totalTransactions = 0;
$totalRevenue = 0;

for ($i = 0; $i < 30; $i++) {
    $date = now()->subDays($i);
    $transactionsPerDay = rand(2, 8); // 2-8 transactions per day

    for ($j = 0; $j < $transactionsPerDay; $j++) {
        // Random time during business hours (8 AM - 9 PM)
        $hour = rand(8, 21);
        $minute = rand(0, 59);
        $transactionDateTime = $date->copy()->setTime($hour, $minute);

        // Generate unique transaction number
        $transactionNumber = Transaction::generateTransactionNumber();

        $transaction = Transaction::create([
            'transaction_number' => $transactionNumber,
            'customer_id' => count($customers) > 0 && rand(0, 1) ? $customers[array_rand($customers)]['id'] : null,
            'outlet_id' => $outlets->random()->id,
            'user_id' => rand(1, 5), // Random user
            'transaction_date' => $transactionDateTime,
            'subtotal' => 0,
            'tax_amount' => 0,
            'discount_amount' => rand(0, 1) ? rand(1000, 5000) : 0, // Random discount
            'total_amount' => 0,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => $paymentMethods[array_rand($paymentMethods)],
            'status' => 'completed',
            'notes' => rand(0, 1) ? 'Sample transaction' : null,
        ]);

        // Add transaction items (1-4 items per transaction)
        $itemCount = rand(1, 4);
        $subtotal = 0;

        $usedProducts = [];
        for ($k = 0; $k < $itemCount; $k++) {
            // Avoid duplicate products in same transaction
            do {
                $product = $products[array_rand($products)];
            } while (in_array($product['id'], $usedProducts));

            $usedProducts[] = $product['id'];

            $quantity = rand(1, 3);
            $price = $product['selling_price'];
            $total = $quantity * $price;

            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id' => $product['id'],
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
        $paid = $total + rand(0, 10000); // Sometimes overpay
        $change = $paid - $total;

        $transaction->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'change_amount' => $change,
        ]);

        $totalTransactions++;
        $totalRevenue += $total;
    }
}

echo "✅ Created $totalTransactions transactions\n";
echo "💰 Total Revenue: Rp " . number_format($totalRevenue, 0, ',', '.') . "\n";
echo "📅 Date Range: " . now()->subDays(29)->format('Y-m-d') . " to " . now()->format('Y-m-d') . "\n\n";

// Show summary
echo "📊 SUMMARY\n";
echo "==========\n";
echo "Products: " . Product::count() . "\n";
echo "Customers: " . Customer::count() . "\n";
echo "Transactions: " . Transaction::count() . "\n";
echo "Transaction Items: " . TransactionItem::count() . "\n";
echo "Total Revenue: Rp " . number_format(Transaction::sum('total_amount'), 0, ',', '.') . "\n\n";

echo "🎉 Sample data created successfully!\n";
echo "Now you can test the reports with realistic data.\n\n";

echo "🔗 Test URLs:\n";
echo "- Reports: http://localhost:3000/reports\n";
echo "- Dashboard: http://localhost:3000/dashboard\n";
echo "- POS: http://localhost:3000/pos\n";
