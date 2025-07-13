<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Transaction;

echo "🔍 COMPARING DASHBOARD VS REPORTS DATA\n";
echo "======================================\n\n";

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

// Get direct database data first
echo "📊 DIRECT DATABASE QUERY\n";
echo "========================\n";

$todayTransactions = Transaction::whereDate('transaction_date', $today)->get();
$dbRevenue = $todayTransactions->sum('total_amount');
$dbCount = $todayTransactions->count();
$dbAvg = $dbCount > 0 ? $dbRevenue / $dbCount : 0;

echo "Database Results:\n";
echo "- Transactions: $dbCount\n";
echo "- Revenue: Rp " . number_format($dbRevenue) . "\n";
echo "- Average: Rp " . number_format($dbAvg) . "\n\n";

// Test Dashboard API
echo "🏠 DASHBOARD API\n";
echo "================\n";

try {
    $dashboardRequest = new Request();
    $dashboardController = new DashboardController();
    $dashboardResponse = $dashboardController->index($dashboardRequest);
    $dashboardData = json_decode($dashboardResponse->getContent(), true);

    echo "Dashboard API Response:\n";
    echo "Status: " . $dashboardResponse->getStatusCode() . "\n";

    if (isset($dashboardData['data'])) {
        $data = $dashboardData['data'];
        $transactionStats = $data['transaction_stats'] ?? [];
        $stats = $data['stats'] ?? [];

        echo "- Today Revenue: " . (isset($transactionStats['revenue_today']) ? 'Rp ' . number_format($transactionStats['revenue_today']) : 'N/A') . "\n";
        echo "- Today Transactions: " . ($transactionStats['transactions_today'] ?? 'N/A') . "\n";
        echo "- This Month Revenue: " . (isset($transactionStats['revenue_this_month']) ? 'Rp ' . number_format($transactionStats['revenue_this_month']) : 'N/A') . "\n";
        echo "- This Month Transactions: " . ($transactionStats['transactions_this_month'] ?? 'N/A') . "\n";
        echo "- Total Products: " . ($stats['total_products'] ?? 'N/A') . "\n";
        echo "- Total Customers: " . ($stats['total_customers'] ?? 'N/A') . "\n";
    } else {
        echo "❌ No data in dashboard response\n";
    }

    echo "\nFull Dashboard Response:\n";
    echo json_encode($dashboardData, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "❌ Dashboard API Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// Test Reports API
echo "📈 REPORTS API (Sales)\n";
echo "======================\n";

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
    echo "Parameters: " . json_encode($request->all()) . "\n";

    if (isset($reportData['data'])) {
        $data = $reportData['data'];

        // Summary stats
        if (isset($data['summary'])) {
            $summary = $data['summary'];
            echo "\nSummary Stats:\n";
            echo "- Total Revenue: " . (isset($summary['total_revenue']) ? 'Rp ' . number_format($summary['total_revenue']) : 'N/A') . "\n";
            echo "- Total Transactions: " . ($summary['total_transactions'] ?? 'N/A') . "\n";
            echo "- Average Transaction: " . (isset($summary['avg_transaction_value']) ? 'Rp ' . number_format($summary['avg_transaction_value']) : 'N/A') . "\n";
        }

        // Grouped data
        if (isset($data['grouped_data']) && count($data['grouped_data']) > 0) {
            echo "\nGrouped Data (Today):\n";
            $todayData = $data['grouped_data'][0];
            echo "- Period: " . ($todayData['period'] ?? 'N/A') . "\n";
            echo "- Transactions: " . ($todayData['transactions_count'] ?? 'N/A') . "\n";
            echo "- Revenue: " . (isset($todayData['total_revenue']) ? 'Rp ' . number_format($todayData['total_revenue']) : 'N/A') . "\n";
            echo "- Average: " . (isset($todayData['avg_transaction_value']) ? 'Rp ' . number_format($todayData['avg_transaction_value']) : 'N/A') . "\n";
        }

        // Top products
        if (isset($data['top_products']) && count($data['top_products']) > 0) {
            echo "\nTop Products: " . count($data['top_products']) . " items\n";
        }

    } else {
        echo "❌ No data in reports response\n";
        echo "Full response: " . json_encode($reportData, JSON_PRETTY_PRINT) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Reports API Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n\n";

// Comparison Analysis
echo "🔍 COMPARISON ANALYSIS\n";
echo "======================\n";

// Extract values for comparison
$dashboardTodayRevenue = isset($dashboardData['data']['transaction_stats']['revenue_today']) ? $dashboardData['data']['transaction_stats']['revenue_today'] : 0;
$dashboardTodayTransactions = isset($dashboardData['data']['transaction_stats']['transactions_today']) ? $dashboardData['data']['transaction_stats']['transactions_today'] : 0;

$reportsTodayRevenue = 0;
$reportsTodayTransactions = 0;

if (isset($reportData['data']['grouped_data']) && count($reportData['data']['grouped_data']) > 0) {
    $reportsTodayRevenue = $reportData['data']['grouped_data'][0]['total_revenue'] ?? 0;
    $reportsTodayTransactions = $reportData['data']['grouped_data'][0]['transactions_count'] ?? 0;
}

echo "Today's Revenue Comparison:\n";
echo "- Database: Rp " . number_format($dbRevenue) . "\n";
echo "- Dashboard API: Rp " . number_format($dashboardTodayRevenue) . "\n";
echo "- Reports API: Rp " . number_format($reportsTodayRevenue) . "\n";

$revenueMatch = ($dbRevenue == $dashboardTodayRevenue && $dashboardTodayRevenue == $reportsTodayRevenue);
echo "- Revenue Match: " . ($revenueMatch ? "✅ YES" : "❌ NO") . "\n\n";

echo "Today's Transactions Comparison:\n";
echo "- Database: $dbCount\n";
echo "- Dashboard API: $dashboardTodayTransactions\n";
echo "- Reports API: $reportsTodayTransactions\n";

$transactionsMatch = ($dbCount == $dashboardTodayTransactions && $dashboardTodayTransactions == $reportsTodayTransactions);
echo "- Transactions Match: " . ($transactionsMatch ? "✅ YES" : "❌ NO") . "\n\n";

// Overall assessment
if ($revenueMatch && $transactionsMatch) {
    echo "🎉 RESULT: All data sources match perfectly!\n";
    echo "✅ Dashboard and Reports show consistent data.\n";
} else {
    echo "⚠️ RESULT: Data inconsistency detected!\n";
    echo "❌ Dashboard and Reports show different data.\n";
    echo "\n🔧 POSSIBLE CAUSES:\n";
    echo "- Different date filtering logic\n";
    echo "- Different query conditions\n";
    echo "- Different data sources\n";
    echo "- Caching issues\n";
    echo "- API endpoint differences\n";
}

echo "\n📝 TESTING SUMMARY\n";
echo "==================\n";
echo "Date: $today\n";
echo "Database Transactions: $dbCount\n";
echo "Database Revenue: Rp " . number_format($dbRevenue) . "\n";
echo "Data Consistency: " . ($revenueMatch && $transactionsMatch ? "CONSISTENT" : "INCONSISTENT") . "\n";

echo "\n🎯 NEXT STEPS:\n";
echo "1. Check frontend displays in browser\n";
echo "2. Compare numbers visually\n";
echo "3. Test different date ranges\n";
echo "4. Check API endpoints in browser dev tools\n";
