<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "🧪 TESTING REPORT API\n";
echo "====================\n\n";

// Login as admin user
$adminUser = User::first();
if ($adminUser) {
    Auth::login($adminUser);
    echo "✅ Logged in as: " . $adminUser->name . "\n\n";
} else {
    echo "❌ No admin user found!\n";
    exit;
}

// Create request
$request = new Request([
    'date_from' => '2025-07-12',
    'date_to' => '2025-07-12',
    'group_by' => 'day'
]);

// Test sales report
echo "📊 Testing Sales Report API\n";
echo "Parameters: " . json_encode($request->all()) . "\n\n";

try {
    $controller = new ReportController();
    $response = $controller->sales($request);

    $data = json_decode($response->getContent(), true);

    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Data:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

    if (isset($data['data']['grouped_data'])) {
        echo "Grouped Data Count: " . count($data['data']['grouped_data']) . "\n";
        if (count($data['data']['grouped_data']) > 0) {
            echo "First Group: " . json_encode($data['data']['grouped_data'][0]) . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// Test without group_by
echo "📊 Testing Sales Report API (without grouping)\n";

$request2 = new Request([
    'date_from' => '2025-07-12',
    'date_to' => '2025-07-12'
]);

echo "Parameters: " . json_encode($request2->all()) . "\n\n";

try {
    $response2 = $controller->sales($request2);
    $data2 = json_decode($response2->getContent(), true);

    echo "Response Status: " . $response2->getStatusCode() . "\n";
    echo "Response Data:\n";
    echo json_encode($data2, JSON_PRETTY_PRINT) . "\n\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// Test direct database query
echo "🔍 Testing Direct Database Query\n";

try {
    // First, check all transactions
    $allTransactions = \App\Models\Transaction::all();
    echo "All Transactions Count: " . $allTransactions->count() . "\n";

    if ($allTransactions->count() > 0) {
        $first = $allTransactions->first();
        echo "First Transaction:\n";
        echo "- Date: " . $first->transaction_date . "\n";
        echo "- Status: " . $first->status . "\n";
        echo "- Amount: " . $first->total_amount . "\n";
        echo "- Raw Date: " . $first->getRawOriginal('transaction_date') . "\n\n";
    }

    // Test different date formats
    echo "Testing different date queries:\n";

    // Query 1: Exact date
    $q1 = \App\Models\Transaction::where('status', 'completed')
        ->whereDate('transaction_date', '2025-07-12')
        ->get();
    echo "- whereDate('2025-07-12'): " . $q1->count() . "\n";

    // Query 2: Between dates
    $q2 = \App\Models\Transaction::where('status', 'completed')
        ->whereBetween('transaction_date', ['2025-07-12', '2025-07-12'])
        ->get();
    echo "- whereBetween(['2025-07-12', '2025-07-12']): " . $q2->count() . "\n";

    // Query 3: Between with time
    $q3 = \App\Models\Transaction::where('status', 'completed')
        ->whereBetween('transaction_date', ['2025-07-12 00:00:00', '2025-07-12 23:59:59'])
        ->get();
    echo "- whereBetween with time: " . $q3->count() . "\n";

    // Query 4: Just status
    $q4 = \App\Models\Transaction::where('status', 'completed')->get();
    echo "- status = completed: " . $q4->count() . "\n";

    // Query 5: All transactions today
    $q5 = \App\Models\Transaction::whereDate('transaction_date', today())->get();
    echo "- today's transactions: " . $q5->count() . "\n";

} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
}
