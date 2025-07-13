<?php

/**
 * Testing Helper Script
 *
 * Script untuk membantu testing dengan data sample
 * Jalankan: php testing-helper.php [command]
 */

require_once 'vendor/autoload.php';

use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Outlet;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function showHelp() {
    echo "🧪 Testing Helper Commands:\n\n";
    echo "php testing-helper.php status          - Show database status\n";
    echo "php testing-helper.php clean           - Clean all data (migrate:fresh)\n";
    echo "php testing-helper.php sample-products - Create sample products\n";
    echo "php testing-helper.php sample-customers - Create sample customers\n";
    echo "php testing-helper.php sample-suppliers - Create sample suppliers\n";
    echo "php testing-helper.php sample-transactions - Create sample transactions\n";
    echo "php testing-helper.php sample-all      - Create all sample data\n";
    echo "php testing-helper.php help            - Show this help\n\n";
}

function showStatus() {
    echo "📊 Database Status:\n";
    echo "==================\n";
    echo "Products: " . Product::count() . "\n";
    echo "Customers: " . Customer::count() . "\n";
    echo "Suppliers: " . Supplier::count() . "\n";
    echo "Transactions: " . Transaction::count() . "\n";
    echo "Users: " . App\Models\User::count() . "\n";
    echo "Outlets: " . Outlet::count() . "\n";
    echo "Categories: " . Category::count() . "\n";
    echo "Units: " . Unit::count() . "\n\n";
}

function cleanDatabase() {
    echo "🧹 Cleaning database...\n";
    Artisan::call('migrate:fresh', ['--seed' => true]);
    echo "✅ Database cleaned and seeded!\n\n";
    showStatus();
}

function createSampleProducts() {
    echo "📦 Creating sample products...\n";

    $categories = Category::all();
    $units = Unit::all();
    $outlets = Outlet::all();

    if ($categories->isEmpty() || $units->isEmpty() || $outlets->isEmpty()) {
        echo "❌ Error: Categories, Units, or Outlets not found. Run 'clean' first.\n";
        return;
    }

    $products = [
        ['name' => 'Nasi Gudeg', 'price' => 15000, 'stock' => 50],
        ['name' => 'Ayam Bakar', 'price' => 25000, 'stock' => 30],
        ['name' => 'Es Teh Manis', 'price' => 5000, 'stock' => 100],
        ['name' => 'Kopi Hitam', 'price' => 8000, 'stock' => 80],
        ['name' => 'Sate Ayam', 'price' => 20000, 'stock' => 40],
    ];

    foreach ($products as $productData) {
        Product::create([
            'name' => $productData['name'],
            'sku' => 'SKU' . rand(1000, 9999),
            'category_id' => $categories->random()->id,
            'unit_id' => $units->random()->id,
            'purchase_price' => $productData['price'] * 0.7,
            'selling_price' => $productData['price'],
            'stock_quantity' => $productData['stock'],
            'min_stock' => 10,
            'outlet_id' => $outlets->random()->id,
            'is_active' => true,
        ]);
    }

    echo "✅ Created " . count($products) . " sample products!\n\n";
}

function createSampleCustomers() {
    echo "👥 Creating sample customers...\n";

    $customers = [
        ['name' => 'Budi Santoso', 'phone' => '081234567890'],
        ['name' => 'Siti Nurhaliza', 'phone' => '081234567891'],
        ['name' => 'Ahmad Wijaya', 'phone' => '081234567892'],
        ['name' => 'Dewi Sartika', 'phone' => '081234567893'],
        ['name' => 'Rudi Hermawan', 'phone' => '081234567894'],
    ];

    foreach ($customers as $customerData) {
        Customer::create([
            'name' => $customerData['name'],
            'phone' => $customerData['phone'],
            'email' => strtolower(str_replace(' ', '.', $customerData['name'])) . '@example.com',
            'address' => 'Jl. Testing No. ' . rand(1, 100),
            'is_active' => true,
        ]);
    }

    echo "✅ Created " . count($customers) . " sample customers!\n\n";
}

function createSampleSuppliers() {
    echo "🏪 Creating sample suppliers...\n";

    $suppliers = [
        ['name' => 'CV Sumber Rejeki', 'phone' => '021-12345678'],
        ['name' => 'PT Maju Bersama', 'phone' => '021-12345679'],
        ['name' => 'UD Berkah Jaya', 'phone' => '021-12345680'],
    ];

    foreach ($suppliers as $supplierData) {
        Supplier::create([
            'name' => $supplierData['name'],
            'phone' => $supplierData['phone'],
            'email' => strtolower(str_replace([' ', '.'], ['', ''], $supplierData['name'])) . '@supplier.com',
            'address' => 'Jl. Supplier No. ' . rand(1, 50),
            'is_active' => true,
        ]);
    }

    echo "✅ Created " . count($suppliers) . " sample suppliers!\n\n";
}

function createSampleTransactions() {
    echo "💰 Creating sample transactions...\n";

    $products = Product::all();
    $customers = Customer::all();
    $outlets = Outlet::all();

    if ($products->isEmpty()) {
        echo "❌ Error: No products found. Create products first.\n";
        return;
    }

    // Create transactions for the last 30 days
    for ($i = 0; $i < 30; $i++) {
        $date = now()->subDays($i);
        $transactionsPerDay = rand(1, 5);

        for ($j = 0; $j < $transactionsPerDay; $j++) {
            $transaction = Transaction::create([
                'transaction_number' => Transaction::generateTransactionNumber(),
                'customer_id' => $customers->isNotEmpty() ? $customers->random()->id : null,
                'outlet_id' => $outlets->random()->id,
                'user_id' => 1, // Admin user
                'transaction_date' => $date,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'payment_method' => ['cash', 'transfer', 'qris'][rand(0, 2)],
                'paid_amount' => 0,
                'change_amount' => 0,
                'status' => 'completed',
            ]);

            // Add transaction items
            $itemCount = rand(1, 3);
            $subtotal = 0;

            for ($k = 0; $k < $itemCount; $k++) {
                $product = $products->random();
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

            // Update transaction totals
            $tax = $subtotal * 0.1; // 10% tax
            $total = $subtotal + $tax;

            $transaction->update([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'paid_amount' => $total,
            ]);
        }
    }

    echo "✅ Created sample transactions for the last 30 days!\n\n";
}

function createAllSampleData() {
    echo "🚀 Creating all sample data...\n\n";
    createSampleProducts();
    createSampleCustomers();
    createSampleSuppliers();
    createSampleTransactions();
    echo "🎉 All sample data created successfully!\n\n";
    showStatus();
}

// Main execution
$command = $argv[1] ?? 'help';

switch ($command) {
    case 'status':
        showStatus();
        break;
    case 'clean':
        cleanDatabase();
        break;
    case 'sample-products':
        createSampleProducts();
        break;
    case 'sample-customers':
        createSampleCustomers();
        break;
    case 'sample-suppliers':
        createSampleSuppliers();
        break;
    case 'sample-transactions':
        createSampleTransactions();
        break;
    case 'sample-all':
        createAllSampleData();
        break;
    case 'help':
    default:
        showHelp();
        break;
}
