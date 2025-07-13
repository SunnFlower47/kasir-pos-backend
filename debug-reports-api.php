<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "🔍 DEBUG REPORTS API DETAILED\n";
echo "==============================\n\n";

// Login as admin user
$adminUser = User::first();
if ($adminUser) {
    Auth::login($adminUser);
    echo "✅ Logged in as: " . $adminUser->name . "\n\n";
} else {
    echo "❌ No admin user found!\n";
    exit;
}

$today = date('Y-m-d');
echo "📅 Testing date: $today\n\n";

// Test Reports API with detailed output
echo "📈 REPORTS API (Sales) - DETAILED\n";
echo "==================================\n";

try {
    $request = new Request([
        'date_from' => $today,
        'date_to' => $today,
        'group_by' => 'day'
    ]);
    
    $reportController = new ReportController();
    $reportResponse = $reportController->sales($request);
    $reportData = json_decode($reportResponse->getContent(), true);
    
    echo "Reports API Response:\n";
    echo "Status: " . $reportResponse->getStatusCode() . "\n";
    echo "Parameters: " . json_encode($request->all()) . "\n\n";
    
    if (isset($reportData['data'])) {
        $data = $reportData['data'];
        
        // Summary stats
        if (isset($data['summary'])) {
            $summary = $data['summary'];
            echo "📊 SUMMARY STATS:\n";
            echo "- Total Revenue: " . (isset($summary['total_revenue']) ? 'Rp ' . number_format($summary['total_revenue']) : 'N/A') . "\n";
            echo "- Total Transactions: " . ($summary['total_transactions'] ?? 'N/A') . "\n";
            echo "- Total Customers: " . ($summary['total_customers'] ?? 'N/A') . "\n";
            echo "- Total Products: " . ($summary['total_products'] ?? 'N/A') . "\n";
            echo "- Average Transaction: " . (isset($summary['avg_transaction_value']) ? 'Rp ' . number_format($summary['avg_transaction_value']) : 'N/A') . "\n";
            echo "- Growth: " . ($summary['growth'] ?? 'N/A') . "%\n\n";
        } else {
            echo "❌ No summary data found\n\n";
        }
        
        // Grouped data
        if (isset($data['grouped_data']) && count($data['grouped_data']) > 0) {
            echo "📅 GROUPED DATA:\n";
            foreach ($data['grouped_data'] as $group) {
                echo "- Period: " . ($group['period'] ?? 'N/A') . "\n";
                echo "- Transactions: " . ($group['transactions_count'] ?? 'N/A') . "\n";
                echo "- Revenue: " . (isset($group['total_revenue']) ? 'Rp ' . number_format($group['total_revenue']) : 'N/A') . "\n";
                echo "- Discount: " . (isset($group['total_discount']) ? 'Rp ' . number_format($group['total_discount']) : 'N/A') . "\n";
                echo "- Tax: " . (isset($group['total_tax']) ? 'Rp ' . number_format($group['total_tax']) : 'N/A') . "\n";
                echo "- Average: " . (isset($group['avg_transaction_value']) ? 'Rp ' . number_format($group['avg_transaction_value']) : 'N/A') . "\n\n";
            }
        } else {
            echo "❌ No grouped data found\n\n";
        }
        
        // Top products
        if (isset($data['top_products']) && count($data['top_products']) > 0) {
            echo "🏆 TOP PRODUCTS (" . count($data['top_products']) . " items):\n";
            foreach ($data['top_products'] as $index => $product) {
                echo ($index + 1) . ". " . ($product['name'] ?? 'N/A') . "\n";
                echo "   - Category: " . ($product['category_name'] ?? 'N/A') . "\n";
                echo "   - Sold: " . ($product['total_sold'] ?? 'N/A') . " units\n";
                echo "   - Revenue: " . (isset($product['total_revenue']) ? 'Rp ' . number_format($product['total_revenue']) : 'N/A') . "\n";
                echo "   - SKU: " . ($product['sku'] ?? 'N/A') . "\n\n";
            }
        } else {
            echo "❌ No top products found\n\n";
        }
        
        // Chart data
        if (isset($data['chart_data']) && count($data['chart_data']) > 0) {
            echo "📈 CHART DATA (" . count($data['chart_data']) . " points):\n";
            foreach ($data['chart_data'] as $chart) {
                echo "- Date: " . ($chart['date'] ?? 'N/A') . "\n";
                echo "- Revenue: " . (isset($chart['revenue']) ? 'Rp ' . number_format($chart['revenue']) : 'N/A') . "\n";
                echo "- Transactions: " . ($chart['transactions'] ?? 'N/A') . "\n\n";
            }
        } else {
            echo "❌ No chart data found\n\n";
        }
        
    } else {
        echo "❌ No data in reports response\n";
    }
    
    echo "\n🔍 FULL RESPONSE STRUCTURE:\n";
    echo "============================\n";
    echo json_encode($reportData, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "❌ Reports API Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Test direct database query for top products
echo "🔍 DIRECT DATABASE QUERY - TOP PRODUCTS\n";
echo "========================================\n";

try {
    $topProductsQuery = \App\Models\TransactionItem::select(
        'products.id',
        'products.name',
        'products.sku',
        'categories.name as category_name',
        \DB::raw('SUM(transaction_items.quantity) as total_sold'),
        \DB::raw('SUM(transaction_items.total_price) as total_revenue')
    )
    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
    ->join('products', 'transaction_items.product_id', '=', 'products.id')
    ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
    ->where('transactions.status', 'completed')
    ->whereBetween('transactions.transaction_date', [
        $today . ' 00:00:00', 
        $today . ' 23:59:59'
    ])
    ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
    ->orderBy('total_sold', 'desc')
    ->limit(10)
    ->get();
    
    echo "Direct Query Results:\n";
    echo "Count: " . $topProductsQuery->count() . "\n\n";
    
    if ($topProductsQuery->count() > 0) {
        foreach ($topProductsQuery as $index => $product) {
            echo ($index + 1) . ". " . $product->name . "\n";
            echo "   - Category: " . ($product->category_name ?? 'N/A') . "\n";
            echo "   - Sold: " . $product->total_sold . " units\n";
            echo "   - Revenue: Rp " . number_format($product->total_revenue) . "\n";
            echo "   - SKU: " . ($product->sku ?? 'N/A') . "\n\n";
        }
    } else {
        echo "❌ No products found in direct query\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n\n";

// Test customer count
echo "👥 CUSTOMER COUNT TEST\n";
echo "======================\n";

try {
    $totalCustomers = \App\Models\Customer::count();
    $customersWithTransactions = \App\Models\Transaction::whereDate('transaction_date', $today)
        ->whereNotNull('customer_id')
        ->distinct('customer_id')
        ->count();
    
    echo "Total Customers in DB: $totalCustomers\n";
    echo "Customers with transactions today: $customersWithTransactions\n";
    
} catch (Exception $e) {
    echo "❌ Customer Error: " . $e->getMessage() . "\n";
}
