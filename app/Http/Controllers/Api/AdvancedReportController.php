<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Models\Outlet;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AdvancedReportController extends Controller
{
    /**
     * Comprehensive Business Intelligence Dashboard
     */
    public function businessIntelligence(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !Gate::allows('reports.sales')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            // If date_from and date_to are provided, period is optional
            // Otherwise, period is required
            $rules = [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'outlet_id' => 'nullable|exists:outlets,id',
            ];

            // Only validate period if date_from and date_to are not both provided
            if (!$request->has('date_from') || !$request->has('date_to')) {
                $rules['period'] = 'nullable|in:today,week,month,quarter,year,all';
            } else {
                $rules['period'] = 'nullable|in:today,week,month,quarter,year,all,custom';
            }

            $request->validate($rules, [
                'date_from.date' => 'Format tanggal awal tidak valid',
                'date_to.date' => 'Format tanggal akhir tidak valid',
                'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal',
                'outlet_id.exists' => 'Outlet yang dipilih tidak ditemukan',
                'period.in' => 'Periode yang dipilih tidak valid'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }

        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        $outletId = $request->outlet_id ? (int) $request->outlet_id : null;

        try {
            // 1. KEY PERFORMANCE INDICATORS (KPIs)
            $kpis = $this->calculateKPIs($dateFrom, $dateTo, $outletId);

            // 2. REVENUE ANALYTICS
            $revenueAnalytics = $this->getRevenueAnalytics($dateFrom, $dateTo, $outletId);

            // 3. CUSTOMER ANALYTICS
            $customerAnalytics = $this->getCustomerAnalytics($dateFrom, $dateTo, $outletId);

            // 4. PRODUCT PERFORMANCE
            $productAnalytics = $this->getProductAnalytics($dateFrom, $dateTo, $outletId);

            // 5. OPERATIONAL METRICS
            $operationalMetrics = $this->getOperationalMetrics($dateFrom, $dateTo, $outletId);

            // 6. FINANCIAL HEALTH
            $financialHealth = $this->getFinancialHealth($dateFrom, $dateTo, $outletId);

            // 7. TREND ANALYSIS
            $trendAnalysis = $this->getTrendAnalysis($dateFrom, $dateTo, $outletId);

            // 8. COMPARATIVE ANALYSIS
            $comparativeAnalysis = $this->getComparativeAnalysis($dateFrom, $dateTo, $outletId);

            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => $kpis,
                    'revenue_analytics' => $revenueAnalytics,
                    'customer_analytics' => $customerAnalytics,
                    'product_analytics' => $productAnalytics,
                    'operational_metrics' => $operationalMetrics,
                    'financial_health' => $financialHealth,
                    'trend_analysis' => $trendAnalysis,
                    'comparative_analysis' => $comparativeAnalysis,
                    'report_metadata' => [
                        'generated_at' => now()->toISOString(),
                        'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletId ? Outlet::find($outletId)?->name : 'All Outlets'
                    ]
                ]
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Log the error for debugging
            Log::error('Business Intelligence Query Error', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses data laporan',
                'error' => app()->environment('local', 'development')
                    ? $e->getMessage()
                    : 'Database error occurred'
            ], 500);
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Business Intelligence Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses data laporan',
                'error' => app()->environment('local', 'development')
                    ? $e->getMessage()
                    : 'An error occurred while processing the report'
            ], 500);
        }
    }

    /**
     * Calculate Key Performance Indicators
     */
    private function calculateKPIs(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('outlet_id', $outletId);
        }

        // Current period metrics
        $currentMetrics = $baseQuery->selectRaw('
            COUNT(*) as total_transactions,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value,
            SUM(discount_amount) as total_discounts,
            SUM(tax_amount) as total_taxes
        ')->first();

        // Calculate refunds for current period
        $refundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $refundQuery->where('outlet_id', $outletId);
        }
        $totalRefunds = $refundQuery->sum('total_amount');
        $netRevenue = ($currentMetrics->total_revenue ?? 0) - $totalRefunds;

        // Previous period for comparison
        $daysDiff = Carbon::parse($dateTo)->diffInDays(Carbon::parse($dateFrom));
        $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');

        $prevQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$prevDateFrom . ' 00:00:00', $prevDateTo . ' 23:59:59']);

        if ($outletId) {
            $prevQuery->where('outlet_id', $outletId);
        }

        $prevMetrics = $prevQuery->selectRaw('
            COUNT(*) as total_transactions,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')->first();

        // Calculate refunds for previous period
        $prevRefundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$prevDateFrom . ' 00:00:00', $prevDateTo . ' 23:59:59']);
        if ($outletId) {
            $prevRefundQuery->where('outlet_id', $outletId);
        }
        $prevRefunds = $prevRefundQuery->sum('total_amount');
        $prevNetRevenue = ($prevMetrics->total_revenue ?? 0) - $prevRefunds;

        // Calculate growth rates (using net_revenue)
        $revenueGrowth = $this->calculateGrowthRate($prevNetRevenue, $netRevenue);
        $transactionGrowth = $this->calculateGrowthRate($prevMetrics->total_transactions, $currentMetrics->total_transactions);
        $avgValueGrowth = $this->calculateGrowthRate($prevMetrics->avg_transaction_value, $currentMetrics->avg_transaction_value);

        // Customer metrics
        $uniqueCustomers = $baseQuery->distinct()->count('customer_id');
        $totalCustomers = Customer::count();

        // Product metrics
        $totalProducts = Product::count();
        $activeProducts = $baseQuery->join('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->distinct('transaction_items.product_id')
            ->count('transaction_items.product_id');

        return [
            'revenue' => [
                'total' => $currentMetrics->total_revenue ?? 0,
                'refunds' => $totalRefunds,
                'net_revenue' => $netRevenue,
                'current' => $netRevenue,
                'previous' => $prevNetRevenue,
                'growth_rate' => $revenueGrowth,
                'growth_direction' => $revenueGrowth >= 0 ? 'up' : 'down'
            ],
            'transactions' => [
                'current' => $currentMetrics->total_transactions ?? 0,
                'previous' => $prevMetrics->total_transactions ?? 0,
                'growth_rate' => $transactionGrowth,
                'growth_direction' => $transactionGrowth >= 0 ? 'up' : 'down'
            ],
            'avg_transaction_value' => [
                'current' => $currentMetrics->avg_transaction_value ?? 0,
                'previous' => $prevMetrics->avg_transaction_value ?? 0,
                'growth_rate' => $avgValueGrowth,
                'growth_direction' => $avgValueGrowth >= 0 ? 'up' : 'down'
            ],
            'discounts' => [
                'total' => $currentMetrics->total_discounts ?? 0,
                'percentage' => $currentMetrics->total_revenue > 0 ?
                    round(($currentMetrics->total_discounts / $currentMetrics->total_revenue) * 100, 2) : 0
            ],
            'taxes' => [
                'total' => $currentMetrics->total_taxes ?? 0,
                'percentage' => $currentMetrics->total_revenue > 0 ?
                    round(($currentMetrics->total_taxes / $currentMetrics->total_revenue) * 100, 2) : 0
            ],
            'customers' => [
                'active' => $uniqueCustomers,
                'total' => $totalCustomers,
                'engagement_rate' => $totalCustomers > 0 ? round(($uniqueCustomers / $totalCustomers) * 100, 2) : 0
            ],
            'products' => [
                'active' => $activeProducts,
                'total' => $totalProducts,
                'utilization_rate' => $totalProducts > 0 ? round(($activeProducts / $totalProducts) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get Revenue Analytics
     */
    private function getRevenueAnalytics(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('outlet_id', $outletId);
        }

        // Revenue by payment method (include refunds)
        $revenueByPayment = $baseQuery->selectRaw('
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')
        ->groupBy('payment_method')
        ->orderBy('total_revenue', 'desc')
        ->get();

        // Calculate refunds by payment method
        $refundByPaymentQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $refundByPaymentQuery->where('outlet_id', $outletId);
        }
        $refundByPayment = $refundByPaymentQuery->selectRaw('
            payment_method,
            SUM(total_amount) as refunds
        ')
        ->groupBy('payment_method')
        ->get()
        ->keyBy('payment_method');

        // Add net_revenue to revenue by payment method
        $revenueByPayment = $revenueByPayment->map(function($item) use ($refundByPayment) {
            $refunds = $refundByPayment->get($item->payment_method)->refunds ?? 0;
            $item->net_revenue = (float) $item->total_revenue - (float) $refunds;
            return $item;
        });

        // Revenue by hour (peak hours analysis)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // Use raw datetime extraction to avoid timezone conversion issues
        // Get hour directly from the stored datetime value in database
        if ($isSqlite) {
            $hourExpression = "CAST(strftime('%H', transaction_date) AS INTEGER)";
        } else {
            // MySQL: Extract hour using EXTRACT or HOUR function
            // Use EXTRACT to get hour from the raw datetime value stored in database
            $hourExpression = "EXTRACT(HOUR FROM transaction_date)";
        }

        $rawRevenueByHour = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $rawRevenueByHour->where('outlet_id', $outletId);
        }

        $rawRevenueByHour = $rawRevenueByHour->selectRaw("
            {$hourExpression} as hour_int,
            COUNT(id) as transaction_count,
            SUM(total_amount) as total_revenue
        ")
        ->groupBy(DB::raw($hourExpression))
        ->orderBy(DB::raw($hourExpression))
        ->get();

        // Calculate refunds by hour
        $refundByHourQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $refundByHourQuery->where('outlet_id', $outletId);
        }
        $refundByHour = $refundByHourQuery->selectRaw("
            {$hourExpression} as hour_int,
            SUM(total_amount) as refunds
        ")
        ->groupBy(DB::raw($hourExpression))
        ->get()
        ->keyBy('hour_int');

        // Fill in missing hours (0-23) with zero values for complete chart
        $revenueByHour = collect([]);
        for ($i = 0; $i < 24; $i++) {
            $hourData = $rawRevenueByHour->firstWhere('hour_int', $i);
            $refundData = $refundByHour->get($i);
            $totalRev = $hourData ? (float) $hourData->total_revenue : 0;
            $refunds = $refundData ? (float) $refundData->refunds : 0;
            $revenueByHour->push([
                'hour' => sprintf('%02d:00', $i),
                'transaction_count' => $hourData ? (int) $hourData->transaction_count : 0,
                'total_revenue' => $totalRev,
                'refunds' => $refunds,
                'net_revenue' => $totalRev - $refunds,
            ]);
        }

        // Revenue by day of week
        $dayOfWeekExpression = $isSqlite ? 'strftime("%w", transaction_date)' : 'DAYOFWEEK(transaction_date)';
        $revenueByDay = $baseQuery->selectRaw("
            {$dayOfWeekExpression} as day_of_week,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue
        ")
        ->groupBy('day_of_week')
        ->orderBy('day_of_week')
        ->get();

        // Calculate refunds by day of week
        $refundByDayQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $refundByDayQuery->where('outlet_id', $outletId);
        }
        $refundByDay = $refundByDayQuery->selectRaw("
            {$dayOfWeekExpression} as day_of_week,
            SUM(total_amount) as refunds
        ")
        ->groupBy('day_of_week')
        ->get()
        ->keyBy('day_of_week');

        // Add net_revenue to revenue by day of week
        $revenueByDay = $revenueByDay->map(function($item) use ($refundByDay) {
            $refunds = $refundByDay->get($item->day_of_week)->refunds ?? 0;
            $item->net_revenue = (float) $item->total_revenue - (float) $refunds;
            return $item;
        });

        // Monthly revenue trend (last 12 months)
        $monthlyTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $month->copy()->endOfMonth()->format('Y-m-d');

            $monthQuery = Transaction::where('status', 'completed')
                ->whereBetween('transaction_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

            if ($outletId) {
                $monthQuery->where('outlet_id', $outletId);
            }

            $monthData = $monthQuery->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_revenue
            ')->first();

            // Calculate refunds for this month
            $monthRefundQuery = Transaction::where('status', 'refunded')
                ->whereBetween('transaction_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
            if ($outletId) {
                $monthRefundQuery->where('outlet_id', $outletId);
            }
            $monthRefunds = $monthRefundQuery->sum('total_amount');

            $totalRevenue = $monthData->total_revenue ?? 0;
            $monthlyTrend[] = [
                'month' => $month->format('Y-m'),
                'month_name' => $month->format('M Y'),
                'transaction_count' => $monthData->transaction_count ?? 0,
                'total_revenue' => $totalRevenue,
                'refunds' => $monthRefunds,
                'net_revenue' => $totalRevenue - $monthRefunds
            ];
        }

        return [
            'by_payment_method' => $revenueByPayment,
            'by_hour' => $revenueByHour,
            'by_day_of_week' => $revenueByDay,
            'monthly_trend' => $monthlyTrend
        ];
    }

    /**
     * Get Customer Analytics
     */
    private function getCustomerAnalytics(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('outlet_id', $outletId);
        }

        // Customer segmentation by spending
        $customerSegmentation = $baseQuery->selectRaw('
            customer_id,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_spent,
            AVG(total_amount) as avg_transaction_value,
            MAX(transaction_date) as last_purchase
        ')
        ->whereNotNull('customer_id')
        ->groupBy('customer_id')
        ->get()
        ->map(function ($customer) {
            $segments = [
                'high_value' => $customer->total_spent >= 1000000, // >= 1M
                'medium_value' => $customer->total_spent >= 500000 && $customer->total_spent < 1000000,
                'low_value' => $customer->total_spent < 500000,
                'frequent' => $customer->transaction_count >= 10,
                'occasional' => $customer->transaction_count >= 3 && $customer->transaction_count < 10,
                'rare' => $customer->transaction_count < 3
            ];

            $segment = 'low_value';
            if ($segments['high_value']) $segment = 'high_value';
            elseif ($segments['medium_value']) $segment = 'medium_value';

            $frequency = 'rare';
            if ($segments['frequent']) $frequency = 'frequent';
            elseif ($segments['occasional']) $frequency = 'occasional';

            return [
                'customer_id' => $customer->customer_id,
                'transaction_count' => $customer->transaction_count,
                'total_spent' => $customer->total_spent,
                'avg_transaction_value' => $customer->avg_transaction_value,
                'last_purchase' => $customer->last_purchase,
                'value_segment' => $segment,
                'frequency_segment' => $frequency
            ];
        });

        // Customer lifetime value analysis
        $customerLTV = $baseQuery->selectRaw('
            customer_id,
            COUNT(*) as total_transactions,
            SUM(total_amount) as lifetime_value,
            MIN(transaction_date) as first_purchase,
            MAX(transaction_date) as last_purchase
        ')
        ->whereNotNull('customer_id')
        ->groupBy('customer_id')
        ->get()
        ->map(function ($customer) {
            $daysSinceFirst = Carbon::parse($customer->first_purchase)->diffInDays(now());
            $daysSinceLast = Carbon::parse($customer->last_purchase)->diffInDays(now());

            return [
                'customer_id' => $customer->customer_id,
                'lifetime_value' => $customer->lifetime_value,
                'total_transactions' => $customer->total_transactions,
                'avg_order_value' => $customer->lifetime_value / $customer->total_transactions,
                'days_since_first' => $daysSinceFirst,
                'days_since_last' => $daysSinceLast,
                'purchase_frequency' => $daysSinceFirst > 0 ? $customer->total_transactions / ($daysSinceFirst / 30) : 0
            ];
        });

        // New vs returning customers
        $newCustomers = $baseQuery->whereNotNull('customer_id')
            ->whereRaw('customer_id NOT IN (SELECT DISTINCT customer_id FROM transactions WHERE transaction_date < ? AND customer_id IS NOT NULL)',
                [$dateFrom . ' 00:00:00'])
            ->distinct()
            ->count('customer_id');

        $returningCustomers = $baseQuery->whereNotNull('customer_id')
            ->whereRaw('customer_id IN (SELECT DISTINCT customer_id FROM transactions WHERE transaction_date < ? AND customer_id IS NOT NULL)',
                [$dateFrom . ' 00:00:00'])
            ->distinct()
            ->count('customer_id');

        return [
            'segmentation' => $customerSegmentation,
            'lifetime_value' => $customerLTV,
            'new_vs_returning' => [
                'new_customers' => $newCustomers,
                'returning_customers' => $returningCustomers,
                'total_active_customers' => $newCustomers + $returningCustomers
            ],
            'summary' => [
                'total_customers' => Customer::count(),
                'active_customers' => $newCustomers + $returningCustomers,
                'customer_retention_rate' => ($newCustomers + $returningCustomers) > 0 ?
                    round(($returningCustomers / ($newCustomers + $returningCustomers)) * 100, 2) : 0
            ]
        ];
    }

    /**
     * Get Product Analytics
     */
    private function getProductAnalytics(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('transactions.outlet_id', $outletId);
        }

        // Top performing products - buat query baru
        $topProductsQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $topProductsQuery->where('transactions.outlet_id', $outletId);
        }

        $topProducts = $topProductsQuery->selectRaw('
                products.id,
                products.name,
                products.sku,
                categories.name as category_name,
                SUM(transaction_items.quantity) as total_sold,
                SUM(transaction_items.total_price) as total_revenue,
                AVG(transaction_items.unit_price) as avg_selling_price,
                products.purchase_price as current_purchase_price,
                SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cost,
                SUM(transaction_items.total_price - (transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price))) as total_profit
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name', 'products.purchase_price')
            ->orderByDesc(DB::raw('SUM(transaction_items.total_price)'))
            ->limit(20)
            ->get()
            ->map(function ($product) {
                $profitMargin = $product->total_revenue > 0 ?
                    round(($product->total_profit / $product->total_revenue) * 100, 2) : 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category_name,
                    'total_sold' => $product->total_sold,
                    'total_revenue' => $product->total_revenue,
                    'avg_selling_price' => $product->avg_selling_price,
                    'purchase_price' => $product->purchase_price,
                    'total_cost' => $product->total_cost,
                    'total_profit' => $product->total_profit,
                    'profit_margin' => $profitMargin
                ];
            });

        // Category performance - buat query baru
        $categoryPerformanceQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $categoryPerformanceQuery->where('transactions.outlet_id', $outletId);
        }

        $categoryPerformance = $categoryPerformanceQuery->selectRaw('
                categories.id,
                categories.name as category_name,
                COUNT(DISTINCT products.id) as product_count,
                SUM(transaction_items.quantity) as total_sold,
                SUM(transaction_items.total_price) as total_revenue,
                AVG(transaction_items.unit_price) as avg_selling_price
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(transaction_items.total_price)'))
            ->get();

        // Slow moving products - gunakan collection filter untuk menghindari HAVING clause issue
        $outletFilter = $outletId ? "AND t.outlet_id = {$outletId}" : "";
        $allProducts = Product::leftJoin('product_stocks', function($join) use ($outletId) {
                $join->on('products.id', '=', 'product_stocks.product_id');
                if ($outletId) {
                    $join->where('product_stocks.outlet_id', '=', $outletId);
                }
            })
            ->selectRaw("
                products.id,
                products.name,
                products.sku,
                COALESCE((
                    SELECT SUM(ti.quantity)
                    FROM transaction_items ti
                    INNER JOIN transactions t ON ti.transaction_id = t.id
                    WHERE ti.product_id = products.id
                    AND t.status = 'completed'
                    AND t.transaction_date BETWEEN '{$dateFrom} 00:00:00' AND '{$dateTo} 23:59:59'
                    {$outletFilter}
                ), 0) as total_sold,
                products.min_stock,
                product_stocks.quantity as current_stock
            ")
            ->get();

        // Filter dan sort dengan collection (menghindari HAVING clause issue di SQLite)
        $slowMovingProducts = $allProducts
            ->filter(function($product) {
                return $product->total_sold < 5;
            })
            ->sortBy('total_sold')
            ->take(20)
            ->values();

        return [
            'top_products' => $topProducts,
            'category_performance' => $categoryPerformance,
            'slow_moving_products' => $slowMovingProducts,
            'summary' => [
                'total_products' => Product::count(),
                'active_products' => $topProducts->count(),
                'categories_count' => $categoryPerformance->count(),
                'slow_moving_count' => $slowMovingProducts->count()
            ]
        ];
    }

    /**
     * Get Operational Metrics
     */
    private function getOperationalMetrics(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('outlet_id', $outletId);
        }

        // Transaction processing time (if we had timestamps)
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($isSqlite) {
            $avgTransactionTime = $baseQuery->selectRaw('
                AVG(julianday(updated_at) - julianday(created_at)) * 24 * 60 as avg_processing_time_minutes
            ')->first();
        } else {
            // MySQL compatible
            $avgTransactionTime = $baseQuery->selectRaw('
                AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) as avg_processing_time_minutes
            ')->first();
        }

        // Peak hours analysis - ambil semua jam yang ada datanya, urutkan berdasarkan transaction count
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        // For SQLite, use strftime to extract hour directly from transaction_date
        // For MySQL, use EXTRACT to get hour from raw datetime value
        if ($isSqlite) {
            $hourExpression = "CAST(strftime('%H', transaction_date) AS INTEGER)";
        } else {
            // Use EXTRACT to get hour from the raw datetime value stored in database
            $hourExpression = "EXTRACT(HOUR FROM transaction_date)";
        }

        $peakHoursQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $peakHoursQuery->where('outlet_id', $outletId);
        }

        // Get all hours with data (no limit, show all hours that have transactions)
        // Include raw transaction_date for debugging timezone issues
        $hourlyResults = $peakHoursQuery->selectRaw("
            {$hourExpression} as hour,
            COUNT(id) as transaction_count,
            SUM(total_amount) as total_revenue,
            MAX(transaction_date) as sample_date,
            MIN(transaction_date) as min_date
        ")
        ->groupBy(DB::raw($hourExpression))
        ->orderByDesc(DB::raw('COUNT(id)'))
        ->get();

        // Format results with hour in "HH:00" format
        $peakHours = $hourlyResults->map(function($item) {
            return [
                'hour' => str_pad($item->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'transaction_count' => (int) $item->transaction_count,
                'total_revenue' => (float) $item->total_revenue
            ];
        })->values();

        // Staff performance (if user tracking is available) - buat query baru untuk menghindari reuse baseQuery
        // Apply outlet filter BEFORE join to avoid ambiguous column error
        $staffPerformanceQuery = Transaction::where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $staffPerformanceQuery->where('transactions.outlet_id', $outletId);
        }

        $staffPerformance = $staffPerformanceQuery->join('users', 'transactions.user_id', '=', 'users.id')
            ->selectRaw('
                users.id,
                users.name,
                COUNT(transactions.id) as transaction_count,
                SUM(transactions.total_amount) as total_revenue,
                AVG(transactions.total_amount) as avg_transaction_value
            ')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc(DB::raw('SUM(transactions.total_amount)'))
            ->get();

        // Outlet performance comparison - buat query baru untuk menghindari ambiguous column
        $outletPerformanceQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        // Don't apply outlet_id filter here since we want to compare all outlets

        $outletPerformance = $outletPerformanceQuery->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->selectRaw('
                outlets.id,
                outlets.name,
                COUNT(transactions.id) as transaction_count,
                SUM(transactions.total_amount) as total_revenue,
                AVG(transactions.total_amount) as avg_transaction_value
            ')
            ->groupBy('outlets.id', 'outlets.name')
            ->orderByDesc(DB::raw('SUM(transactions.total_amount)'))
            ->get();

        return [
            'avg_processing_time' => $avgTransactionTime->avg_processing_time_minutes ?? 0,
            'peak_hours' => $peakHours,
            'staff_performance' => $staffPerformance,
            'outlet_performance' => $outletPerformance,
            'summary' => [
                'total_staff' => User::count(),
                'total_outlets' => Outlet::count(),
                'busiest_hour' => $peakHours->first()?->hour ?? 'N/A',
                'top_performing_outlet' => $outletPerformance->first()?->name ?? 'N/A'
            ]
        ];
    }

    /**
     * Get Financial Health Metrics
     */
    private function getFinancialHealth(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        // Revenue vs Expenses
        $revenue = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $revenue->where('outlet_id', $outletId);
        }

        $totalRevenue = $revenue->sum('total_amount');

        // Purchase expenses
        $purchaseExpenses = Purchase::where('status', 'paid')
            ->whereBetween('purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $purchaseExpenses->where('outlet_id', $outletId);
        }

        $totalPurchaseExpenses = $purchaseExpenses->sum('total_amount');

        // Operational expenses (from expenses table)
        $operationalExpenses = \App\Models\Expense::whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $operationalExpenses->where('outlet_id', $outletId);
        }
        $totalOperationalExpenses = $operationalExpenses->sum('amount');

        $totalExpenses = $totalPurchaseExpenses + $totalOperationalExpenses;

        // Calculate COGS (Cost of Goods Sold)
        $cogsQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $cogsQuery->where('transactions.outlet_id', $outletId);
        }
        $totalCOGS = $cogsQuery->sum(DB::raw('transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)'));

        // Calculate refunds
        $refundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
        if ($outletId) {
            $refundQuery->where('outlet_id', $outletId);
        }
        $totalRefunds = $refundQuery->sum('total_amount');
        $netRevenue = $totalRevenue - $totalRefunds;

        // Profit calculation according to accounting principles (Accrual Basis - Standard Accounting):
        // Gross Profit = Net Revenue - COGS
        // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
        //   - Operational Expenses: biaya operasional (sewa, listrik, gaji, dll)
        //   - Unsold Inventory Expense: Purchase Expenses yang belum menjadi COGS (barang yang dibeli tapi belum terjual)
        //   - Logika: Jika Purchase Expenses > COGS, ada barang yang belum terjual (expense)
        //             Jika Purchase Expenses <= COGS, semua purchase sudah terjual (tidak perlu ditambahkan, karena COGS sudah dikurangkan)
        // Net Profit = Gross Profit - Operating Expenses
        $grossProfit = $netRevenue - $totalCOGS;
        $unsoldInventoryExpense = max(0, $totalPurchaseExpenses - $totalCOGS);
        $operatingExpenses = $totalOperationalExpenses + $unsoldInventoryExpense;
        $netProfit = $grossProfit - $operatingExpenses;

        // Profit margin based on net profit
        $profitMargin = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 2) : 0;

        // Cash flow analysis
        // Cash inflow = total revenue (money received)
        // Cash outflow = total expenses (purchase + operational) + refunds (money returned)
        $totalCashOutflow = $totalExpenses + $totalRefunds;
        $cashFlow = [
            'inflow' => $totalRevenue,
            'outflow' => $totalCashOutflow,
            'refunds' => $totalRefunds,
            'net_inflow' => $netRevenue, // Net cash inflow (revenue - refunds)
            'net_cash_flow' => $netRevenue - ($totalExpenses), // Net cash flow (net revenue - expenses, excluding refunds from outflow for this calculation)
            'cash_flow_ratio' => $totalCashOutflow > 0 ? round($totalRevenue / $totalCashOutflow, 2) : 0
        ];

        // Inventory turnover (simplified)
        $inventoryTurnover = $this->calculateInventoryTurnover($dateFrom, $dateTo, $outletId);

        return [
            'revenue' => $totalRevenue,
            'refunds' => $totalRefunds,
            'net_revenue' => $netRevenue,
            'expenses' => $totalExpenses,
            'purchase_expenses' => $totalPurchaseExpenses,
            'operational_expenses' => $totalOperationalExpenses,
            'cogs' => $totalCOGS,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $operatingExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
            'cash_flow' => $cashFlow,
            'inventory_turnover' => $inventoryTurnover,
            'financial_ratios' => [
                'profit_margin' => $profitMargin,
                'cash_flow_ratio' => $cashFlow['cash_flow_ratio'],
                'revenue_growth' => 0, // Would need historical data
                'expense_ratio' => $netRevenue > 0 ? round(($totalExpenses / $netRevenue) * 100, 2) : 0 // Use net_revenue for consistency
            ]
        ];
    }

    /**
     * Get Trend Analysis
     */
    private function getTrendAnalysis(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $daysDiff = Carbon::parse($dateTo)->diffInDays(Carbon::parse($dateFrom));
        $trends = [];

        // Daily trends
        for ($i = 0; $i <= $daysDiff; $i++) {
            $date = Carbon::parse($dateFrom)->addDays($i);
            $dayStart = $date->format('Y-m-d') . ' 00:00:00';
            $dayEnd = $date->format('Y-m-d') . ' 23:59:59';

            $dayQuery = Transaction::where('status', 'completed')
                ->whereBetween('transaction_date', [$dayStart, $dayEnd]);

            if ($outletId) {
                $dayQuery->where('outlet_id', $outletId);
            }

            $dayData = $dayQuery->selectRaw('
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_transaction_value
            ')->first();

            // Calculate refunds for this day
            $dayRefundQuery = Transaction::where('status', 'refunded')
                ->whereBetween('transaction_date', [$dayStart, $dayEnd]);
            if ($outletId) {
                $dayRefundQuery->where('outlet_id', $outletId);
            }
            $dayRefunds = $dayRefundQuery->sum('total_amount');

            $totalRevenue = $dayData->total_revenue ?? 0;
            $trends[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'transaction_count' => $dayData->transaction_count ?? 0,
                'total_revenue' => $totalRevenue,
                'refunds' => $dayRefunds,
                'net_revenue' => $totalRevenue - $dayRefunds,
                'avg_transaction_value' => $dayData->avg_transaction_value ?? 0
            ];
        }

        // Calculate trend direction (use net_revenue for accuracy)
        $revenueTrend = $this->calculateTrendDirection(array_column($trends, 'net_revenue'));
        $transactionTrend = $this->calculateTrendDirection(array_column($trends, 'transaction_count'));

        return [
            'daily_trends' => $trends,
            'trend_direction' => [
                'revenue' => $revenueTrend,
                'transactions' => $transactionTrend
            ],
            'summary' => [
                'best_day' => collect($trends)->sortByDesc('net_revenue')->first(),
                'worst_day' => collect($trends)->sortBy('net_revenue')->first(),
                'avg_daily_revenue' => collect($trends)->avg('net_revenue'), // Use net_revenue for accuracy
                'avg_daily_transactions' => collect($trends)->avg('transaction_count')
            ]
        ];
    }

    /**
     * Get Comparative Analysis
     */
    private function getComparativeAnalysis(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $daysDiff = Carbon::parse($dateTo)->diffInDays(Carbon::parse($dateFrom));

        // Previous period comparison
        $prevDateFrom = Carbon::parse($dateFrom)->subDays($daysDiff + 1)->format('Y-m-d');
        $prevDateTo = Carbon::parse($dateFrom)->subDay()->format('Y-m-d');

        $currentQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $prevQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$prevDateFrom . ' 00:00:00', $prevDateTo . ' 23:59:59']);

        if ($outletId) {
            $currentQuery->where('outlet_id', $outletId);
            $prevQuery->where('outlet_id', $outletId);
        }

        $current = $currentQuery->selectRaw('
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')->first();

        $previous = $prevQuery->selectRaw('
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')->first();

        // Year-over-year comparison
        $yoyDateFrom = Carbon::parse($dateFrom)->subYear()->format('Y-m-d');
        $yoyDateTo = Carbon::parse($dateTo)->subYear()->format('Y-m-d');

        $yoyQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$yoyDateFrom . ' 00:00:00', $yoyDateTo . ' 23:59:59']);

        if ($outletId) {
            $yoyQuery->where('outlet_id', $outletId);
        }

        $yoy = $yoyQuery->selectRaw('
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')->first();

        return [
            'period_comparison' => [
                'current' => [
                    'period' => $dateFrom . ' to ' . $dateTo,
                    'transaction_count' => $current->transaction_count ?? 0,
                    'total_revenue' => $current->total_revenue ?? 0,
                    'avg_transaction_value' => $current->avg_transaction_value ?? 0
                ],
                'previous' => [
                    'period' => $prevDateFrom . ' to ' . $prevDateTo,
                    'transaction_count' => $previous->transaction_count ?? 0,
                    'total_revenue' => $previous->total_revenue ?? 0,
                    'avg_transaction_value' => $previous->avg_transaction_value ?? 0
                ],
                'growth' => [
                    'revenue_growth' => $this->calculateGrowthRate($previous->total_revenue, $current->total_revenue),
                    'transaction_growth' => $this->calculateGrowthRate($previous->transaction_count, $current->transaction_count),
                    'avg_value_growth' => $this->calculateGrowthRate($previous->avg_transaction_value, $current->avg_transaction_value)
                ]
            ],
            'year_over_year' => [
                'current' => [
                    'period' => $dateFrom . ' to ' . $dateTo,
                    'total_revenue' => $current->total_revenue ?? 0
                ],
                'previous_year' => [
                    'period' => $yoyDateFrom . ' to ' . $yoyDateTo,
                    'total_revenue' => $yoy->total_revenue ?? 0
                ],
                'yoy_growth' => $this->calculateGrowthRate($yoy->total_revenue, $current->total_revenue)
            ]
        ];
    }

    /**
     * Helper Methods
     */
    private function calculateGrowthRate($previous, $current): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }

    private function calculateTrendDirection(array $values): string
    {
        if (count($values) < 2) return 'stable';

        $firstHalf = array_slice($values, 0, floor(count($values) / 2));
        $secondHalf = array_slice($values, floor(count($values) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        if ($secondAvg > $firstAvg * 1.05) return 'increasing';
        if ($secondAvg < $firstAvg * 0.95) return 'decreasing';
        return 'stable';
    }

    private function calculateInventoryTurnover(string $dateFrom, string $dateTo, ?int $outletId): float
    {
        // Simplified inventory turnover calculation
        $cogs = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $cogs->where('transactions.outlet_id', $outletId);
        }

        $totalCogs = $cogs->sum(DB::raw('transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)'));

        // Average inventory (simplified)
        $avgInventory = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->when($outletId, function($query) use ($outletId) {
                return $query->where('product_stocks.outlet_id', $outletId);
            })
            ->sum(DB::raw('product_stocks.quantity * products.purchase_price'));

        return $avgInventory > 0 ? round($totalCogs / $avgInventory, 2) : 0;
    }
}
