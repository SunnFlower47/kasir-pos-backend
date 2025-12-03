<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Transaction;
use App\Models\TransactionItem;

echo "🔍 DEBUG TRANSACTION DATA\n";
echo "========================\n\n";

// Check transactions
$transactions = Transaction::with('transactionItems')->get();
echo "Total Transactions: " . $transactions->count() . "\n\n";

if ($transactions->count() > 0) {
    foreach ($transactions as $tx) {
        echo "Transaction Details:\n";
        echo "- Number: " . ($tx->transaction_number ?? 'N/A') . "\n";
        echo "- Date: " . $tx->transaction_date . "\n";
        echo "- Total: " . number_format((float) $tx->total_amount) . "\n";
        echo "- Status: " . $tx->status . "\n";
        echo "- Items: " . $tx->transactionItems->count() . "\n";
        echo "- Created: " . $tx->created_at . "\n";
        echo "- Updated: " . $tx->updated_at . "\n\n";

        if ($tx->transactionItems->count() > 0) {
            echo "Transaction Items:\n";
            foreach ($tx->transactionItems as $item) {
                echo "  - Product ID: " . $item->product_id . "\n";
                echo "  - Quantity: " . $item->quantity . "\n";
                echo "  - Unit Price: " . number_format((float) ($item->unit_price ?? 0)) . "\n";
                echo "  - Total: " . number_format((float) ($item->total_price ?? 0)) . "\n";
                if ($item->product) {
                    echo "  - Product Name: " . $item->product->name . "\n";
                }
                echo "\n";
            }
        }
    }
} else {
    echo "❌ No transactions found!\n";
}

// Test API endpoint
echo "🌐 TESTING API ENDPOINTS\n";
echo "========================\n\n";

// Test sales report API
try {
    $today = date('Y-m-d');
    echo "Testing Sales Report API with date: $today\n";

    // Simulate API call parameters
    $params = [
        'date_from' => $today,
        'date_to' => $today,
        'group_by' => 'day'
    ];

    echo "Parameters: " . json_encode($params) . "\n\n";

    // Check if we can access the controller
    $controller = new App\Http\Controllers\Api\ReportController();
    echo "✅ ReportController accessible\n";

} catch (Exception $e) {
    echo "❌ Error testing API: " . $e->getMessage() . "\n";
}
