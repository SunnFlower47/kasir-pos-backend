<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Purchase;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnhancedReportController extends Controller
{
    /**
     * Get enhanced report data with charts and advanced filters
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        if (!$user->can('reports.sales')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - Missing reports.sales permission'], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'period' => 'nullable|in:daily,monthly,yearly',
            'year' => 'nullable|integer|min:2020|max:2030',
            'month' => 'nullable|integer|min:1|max:12'
        ]);

        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        $outletId = $request->outlet_id;
        $period = $request->period ?? 'daily';
        $year = $request->year ?? now()->year;
        $month = $request->month ?? now()->month;

        try {
            // Get summary data
            $summary = $this->getSummaryData($dateFrom, $dateTo, $outletId);

            // Get chart data based on period
            $chartData = $this->getChartData($dateFrom, $dateTo, $outletId, $period);

            // Get top products
            $topProducts = $this->getTopProducts($dateFrom, $dateTo, $outletId);

            // Get payment methods data
            $paymentMethods = $this->getPaymentMethodsData($dateFrom, $dateTo, $outletId);

            // Get customer segments
            $customerSegments = $this->getCustomerSegments($dateFrom, $dateTo, $outletId);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'daily_revenue' => $chartData['daily'],
                    'monthly_revenue' => $chartData['monthly'],
                    'yearly_revenue' => $chartData['yearly'],
                    'top_products' => $topProducts,
                    'payment_methods' => $paymentMethods,
                    'customer_segments' => $customerSegments,
                    'hourly_analysis' => $this->getHourlyAnalysis($dateFrom, $dateTo, $outletId),
                    'category_performance' => $this->getCategoryPerformance($dateFrom, $dateTo, $outletId)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate enhanced report: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getSummaryData($dateFrom, $dateTo, $outletId)
    {
        $query = Transaction::whereBetween('transaction_date', [
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59'
        ]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        // Only get completed transactions for revenue
        $completedTransactions = (clone $query)->where('status', 'completed')->get();

        // Calculate refunds
        $refundTransactions = (clone $query)->where('status', 'refunded')->get();
        $totalRefunds = $refundTransactions->sum('total_amount');

        // Calculate previous period for growth comparison
        $daysDiff = Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo));
        $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');

        $prevQuery = Transaction::whereBetween('transaction_date', [
            $prevDateFrom . ' 00:00:00',
            $prevDateTo . ' 23:59:59'
        ])->where('status', 'completed');

        if ($outletId) {
            $prevQuery->where('outlet_id', $outletId);
        }

        $prevTransactions = $prevQuery->get();

        $totalRevenue = $completedTransactions->sum('total_amount');
        $netRevenue = $totalRevenue - $totalRefunds;
        $prevTotalRevenue = $prevTransactions->sum('total_amount');
        $revenueGrowth = $prevTotalRevenue > 0 ? (($netRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100 : 0;

        $totalTransactions = $completedTransactions->count();
        $prevTotalTransactions = $prevTransactions->count();
        $transactionGrowth = $prevTotalTransactions > 0 ? (($totalTransactions - $prevTotalTransactions) / $prevTotalTransactions) * 100 : 0;

        // Calculate profit margin and net profit (using net_revenue)
        // According to accounting principles (Accrual Basis - Standard Accounting):
        // Gross Profit = Net Revenue - COGS
        // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
        //   - Operational Expenses: biaya operasional (sewa, listrik, gaji, dll)
        //   - Unsold Inventory Expense: Purchase Expenses yang belum menjadi COGS (barang yang dibeli tapi belum terjual)
        //   - Logika: Jika Purchase Expenses > COGS, ada barang yang belum terjual (expense)
        //             Jika Purchase Expenses <= COGS, semua purchase sudah terjual (tidak perlu ditambahkan, karena COGS sudah dikurangkan)
        // Net Profit = Gross Profit - Operating Expenses

        $totalCogs = $this->calculateTotalCogs($dateFrom, $dateTo, $outletId);

        // Get purchase expenses
        $purchaseQuery = Purchase::where('status', 'paid')
            ->whereBetween('purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $purchaseQuery->where('outlet_id', $outletId);
        }
        $totalPurchaseExpenses = $purchaseQuery->sum('total_amount');

        // Get operational expenses
        $operationalQuery = Expense::whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $operationalQuery->where('outlet_id', $outletId);
        }
        $totalOperationalExpenses = $operationalQuery->sum('amount');

        // Calculate Gross Profit
        $grossProfit = $netRevenue - $totalCogs;

        // Calculate Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
        // Hanya purchase expenses yang belum menjadi COGS yang dihitung sebagai expense
        $unsoldInventoryExpense = max(0, $totalPurchaseExpenses - $totalCogs);
        $operatingExpenses = $totalOperationalExpenses + $unsoldInventoryExpense;

        // Calculate Net Profit
        $netProfit = $grossProfit - $operatingExpenses;
        $profitMargin = $netRevenue > 0 ? ($netProfit / $netRevenue) * 100 : 0;

        return [
            'total_revenue' => $totalRevenue,
            'total_refunds' => $totalRefunds,
            'net_revenue' => $netRevenue,
            'total_transactions' => $totalTransactions,
            'total_products' => Product::count(),
            'total_customers' => Customer::count(),
            'avg_transaction_value' => $totalTransactions > 0 ? $netRevenue / $totalTransactions : 0, // Use net_revenue for consistency
            'revenue_growth' => round($revenueGrowth, 2),
            'transaction_growth' => round($transactionGrowth, 2),
            'profit_margin' => round($profitMargin, 2),
            'net_profit' => round($netProfit, 2)
        ];
    }

    private function getChartData($dateFrom, $dateTo, $outletId, $period)
    {
        $dailyData = [];
        $monthlyData = [];
        $yearlyData = [];

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $yearExpression = $isSqlite ? "CAST(strftime('%Y', transaction_date) AS INTEGER)" : 'YEAR(transaction_date)';
        $monthExpression = $isSqlite ? "CAST(strftime('%m', transaction_date) AS INTEGER)" : 'MONTH(transaction_date)';
        $dateExpression = $isSqlite ? "date(transaction_date)" : 'DATE(transaction_date)'; // SQLite uses lowercase 'date()'

        if ($isSqlite) {
            DB::statement("PRAGMA case_sensitive_like = OFF");
        }

        // Daily data - completed transactions
        $dailyQuery = DB::table('transactions')
            ->select(
                DB::raw("{$dateExpression} as date"),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('AVG(total_amount) as avg_value')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $dailyQuery->where('outlet_id', $outletId);
        }

        $dailyResults = $dailyQuery->groupBy('date')->orderBy('date')->get();

        // Daily refunds
        $dailyRefundQuery = DB::table('transactions')
            ->select(
                DB::raw("{$dateExpression} as date"),
                DB::raw('SUM(total_amount) as refunds')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'refunded')
            ->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $dailyRefundQuery->where('outlet_id', $outletId);
        }

        $dailyRefunds = $dailyRefundQuery->groupBy('date')->get()->keyBy('date');

        foreach ($dailyResults as $result) {
            $refunds = $dailyRefunds->get($result->date)->refunds ?? 0;
            $netRevenue = (float) $result->revenue - (float) $refunds;
            $dailyData[] = [
                'date' => Carbon::parse($result->date)->format('M d'),
                'revenue' => (float) $result->revenue,
                'refunds' => (float) $refunds,
                'net_revenue' => $netRevenue,
                'transactions' => (int) $result->transactions,
                'avg_value' => (float) $result->avg_value
            ];
        }

        // Monthly data - completed transactions
        $monthlyQuery = DB::table('transactions')
            ->select(
                DB::raw($yearExpression . ' as year'),
                DB::raw($monthExpression . ' as month'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                Carbon::parse($dateFrom)->startOfYear()->format('Y-m-d'),
                Carbon::parse($dateTo)->endOfYear()->format('Y-m-d')
            ]);

        if ($outletId) {
            $monthlyQuery->where('outlet_id', $outletId);
        }

        $monthlyResults = $monthlyQuery->groupBy('year', 'month')->orderBy('year')->orderBy('month')->get();

        // Monthly refunds
        $monthlyRefundQuery = DB::table('transactions')
            ->select(
                DB::raw($yearExpression . ' as year'),
                DB::raw($monthExpression . ' as month'),
                DB::raw('SUM(total_amount) as refunds')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'refunded')
            ->whereBetween('transaction_date', [
                Carbon::parse($dateFrom)->startOfYear()->format('Y-m-d'),
                Carbon::parse($dateTo)->endOfYear()->format('Y-m-d')
            ]);

        if ($outletId) {
            $monthlyRefundQuery->where('outlet_id', $outletId);
        }

        $monthlyRefunds = $monthlyRefundQuery->groupBy('year', 'month')->get()->keyBy(function ($item) {
            return $item->year . '-' . $item->month;
        });

        $prevRevenue = 0;
        foreach ($monthlyResults as $result) {
            $refundKey = $result->year . '-' . $result->month;
            $refunds = $monthlyRefunds->get($refundKey)->refunds ?? 0;
            $netRevenue = (float) $result->revenue - (float) $refunds;

            $growth = $prevRevenue > 0 ? (($netRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;
            $monthlyData[] = [
                'month' => Carbon::create($result->year, $result->month)->format('M Y'),
                'revenue' => (float) $result->revenue,
                'refunds' => (float) $refunds,
                'net_revenue' => $netRevenue,
                'transactions' => (int) $result->transactions,
                'growth' => round($growth, 2)
            ];
            $prevRevenue = $netRevenue;
        }

        // Yearly data - completed transactions
        $yearlyQuery = DB::table('transactions')
            ->select(
                DB::raw($yearExpression . ' as year'),
                DB::raw('SUM(total_amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                Carbon::now()->subYears(5)->startOfYear()->format('Y-m-d'),
                Carbon::now()->endOfYear()->format('Y-m-d')
            ]);

        if ($outletId) {
            $yearlyQuery->where('outlet_id', $outletId);
        }

        $yearlyResults = $yearlyQuery->groupBy('year')->orderBy('year')->get();

        // Yearly refunds
        $yearlyRefundQuery = DB::table('transactions')
            ->select(
                DB::raw($yearExpression . ' as year'),
                DB::raw('SUM(total_amount) as refunds')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'refunded')
            ->whereBetween('transaction_date', [
                Carbon::now()->subYears(5)->startOfYear()->format('Y-m-d'),
                Carbon::now()->endOfYear()->format('Y-m-d')
            ]);

        if ($outletId) {
            $yearlyRefundQuery->where('outlet_id', $outletId);
        }

        $yearlyRefunds = $yearlyRefundQuery->groupBy('year')->get()->keyBy('year');

        $prevYearRevenue = 0;
        foreach ($yearlyResults as $result) {
            $refunds = $yearlyRefunds->get($result->year)->refunds ?? 0;
            $netRevenue = (float) $result->revenue - (float) $refunds;

            $growth = $prevYearRevenue > 0 ? (($netRevenue - $prevYearRevenue) / $prevYearRevenue) * 100 : 0;
            $yearlyData[] = [
                'year' => (string) $result->year,
                'revenue' => (float) $result->revenue,
                'refunds' => (float) $refunds,
                'net_revenue' => $netRevenue,
                'transactions' => (int) $result->transactions,
                'growth' => round($growth, 2)
            ];
            $prevYearRevenue = $netRevenue;
        }

        return [
            'daily' => $dailyData,
            'monthly' => $monthlyData,
            'yearly' => $yearlyData
        ];
    }

    private function getTopProducts($dateFrom, $dateTo, $outletId)
    {
        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('SUM(transaction_items.quantity) as sales'),
                DB::raw('SUM(transaction_items.total_price) as revenue')
            )
            ->where('transactions.tenant_id', Auth::user()->tenant_id) // Secure
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $query->where('transactions.outlet_id', $outletId);
        }

        $results = $query->groupBy('products.id', 'products.name')
            ->orderBy('revenue', 'desc')
            ->limit(10)
            ->get();

        $totalRevenue = $results->sum('revenue');

        return $results->map(function ($item) use ($totalRevenue) {
            return [
                'name' => $item->name,
                'sales' => (int) $item->sales,
                'revenue' => (float) $item->revenue,
                'percentage' => $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100, 2) : 0
            ];
        })->toArray();
    }

    private function getPaymentMethodsData($dateFrom, $dateTo, $outletId)
    {
        $query = DB::table('transactions')
            ->select(
                'payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(total_amount) as amount')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->groupBy('payment_method')->get();
        $totalAmount = $results->sum('amount');

        return $results->map(function ($item) use ($totalAmount) {
            return [
                'method' => ucfirst($item->payment_method),
                'count' => (int) $item->count,
                'amount' => (float) $item->amount,
                'percentage' => $totalAmount > 0 ? round(($item->amount / $totalAmount) * 100, 2) : 0
            ];
        })->toArray();
    }

    private function getCustomerSegments($dateFrom, $dateTo, $outletId)
    {
        $segmentExpression = <<<SQL
CASE
    WHEN customer_spending.total_spent >= 1000000 THEN "VIP"
    WHEN customer_spending.total_spent >= 500000 THEN "Premium"
    WHEN customer_spending.total_spent >= 100000 THEN "Regular"
    ELSE "New"
END
SQL;

        $spendingQuery = DB::table('transactions')
            ->select(
                'customer_id',
                DB::raw('SUM(total_amount) as total_spent')
            )
            ->whereNotNull('customer_id')
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $spendingQuery->where('outlet_id', $outletId);
        }

        $spendingQuery = $spendingQuery->groupBy('customer_id');

        $results = DB::table('customers')
            ->joinSub($spendingQuery, 'customer_spending', function ($join) {
                $join->on('customers.id', '=', 'customer_spending.customer_id');
            })
            ->select(
                DB::raw($segmentExpression . ' as segment'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(customer_spending.total_spent) as revenue')
            )
            ->groupBy(DB::raw($segmentExpression))
            ->get();
        $totalRevenue = $results->sum('revenue');

        return $results->map(function ($item) use ($totalRevenue) {
            return [
                'segment' => $item->segment,
                'count' => (int) $item->count,
                'revenue' => (float) $item->revenue,
                'percentage' => $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100, 2) : 0
            ];
        })->toArray();
    }

    private function calculateTotalCogs($dateFrom, $dateTo, $outletId)
    {
        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.tenant_id', Auth::user()->tenant_id) // Secure
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $query->where('transactions.outlet_id', $outletId);
        }

        $result = $query->selectRaw('SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cogs')->first();

        return $result->total_cogs ?? 0;
    }

    private function getHourlyAnalysis($dateFrom, $dateTo, $outletId)
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $hourExpression = $isSqlite ? "CAST(strftime('%H', transaction_date) AS INTEGER)" : 'HOUR(transaction_date)';

        $query = DB::table('transactions')
            ->select(
                DB::raw($hourExpression . ' as hour'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->where('tenant_id', Auth::user()->tenant_id) // Secure
            ->where('status', 'completed')
            ->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $results = $query->groupBy(DB::raw($hourExpression))->orderBy(DB::raw($hourExpression))->get();

        // Fill missing hours with zero values
        $hourlyData = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hourData = $results->firstWhere('hour', $hour);
            $hourlyData[] = [
                'hour' => sprintf('%02d:00', $hour),
                'transactions' => $hourData ? (int) $hourData->transactions : 0,
                'revenue' => $hourData ? (float) $hourData->revenue : 0
            ];
        }

        return $hourlyData;
    }

    private function getCategoryPerformance($dateFrom, $dateTo, $outletId)
    {
        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.tenant_id', Auth::user()->tenant_id) // Secure
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($outletId) {
            $query->where('transactions.outlet_id', $outletId);
        }

        $results = $query->select(
                DB::raw('COALESCE(categories.name, "Uncategorized") as category'),
                DB::raw('SUM(transaction_items.total_price) as revenue'),
                DB::raw('COUNT(DISTINCT products.id) as products')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = $results->sum('revenue');

        return $results->map(function ($item) use ($totalRevenue) {
            return [
                'category' => $item->category,
                'revenue' => (float) $item->revenue,
                'products' => (int) $item->products,
                'percentage' => $totalRevenue > 0 ? round(($item->revenue / $totalRevenue) * 100, 2) : 0
            ];
        })->toArray();
    }
}

