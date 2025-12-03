<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class DashboardController extends Controller
{
    /**
     * Get main dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        $userOutletId = $user->outlet_id;

        // Determine data scope based on role and outlet assignment
        $isGlobalAccess = $user->hasRole(['Super Admin', 'Admin']) ||
                         ($user->hasRole('Manager') && !$userOutletId);

        // Allow outlet filtering via request parameter for global access users
        $requestedOutletId = $request->get('outlet_id');
        $effectiveOutletId = null;

        if (!$isGlobalAccess) {
            // Restricted users (Cashier, Warehouse, Manager with outlet) see only their outlet
            $effectiveOutletId = $userOutletId;
        } elseif ($requestedOutletId) {
            // Global access users can filter by specific outlet
            $effectiveOutletId = $requestedOutletId;
        }
        // If $effectiveOutletId is null, show all outlets (global view)

        $today = today();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        // Basic statistics
        $stats = [
            'total_outlets' => $isGlobalAccess ?
                Outlet::where('is_active', true)->count() : 1,
            'total_products' => Product::where('is_active', true)->count(),
            'total_customers' => Customer::count(),
            'total_suppliers' => Supplier::count(),
            'total_users' => $effectiveOutletId ?
                User::where('outlet_id', $effectiveOutletId)->where('is_active', true)->count() :
                User::where('is_active', true)->count(),
            'access_scope' => $isGlobalAccess ? 'global' : 'outlet',
            'filtered_outlet_id' => $effectiveOutletId,
        ];

        // Transaction statistics - use simpler queries for better reliability
        $completedQuery = Transaction::where('status', 'completed');
        $refundedQuery = Transaction::where('status', 'refunded');

        if ($effectiveOutletId) {
            $completedQuery->where('outlet_id', $effectiveOutletId);
            $refundedQuery->where('outlet_id', $effectiveOutletId);
        }

        // Use date strings for comparison (more compatible)
        $todayStart = $today->startOfDay()->toDateTimeString();
        $todayEnd = $today->endOfDay()->toDateTimeString();
        $thisMonthStart = $thisMonth->startOfDay()->toDateTimeString();
        $lastMonthStart = $lastMonth->startOfDay()->toDateTimeString();

        // Debug: Check if we have any transactions at all
        $totalCompleted = (clone $completedQuery)->count();
        \Illuminate\Support\Facades\Log::info('Dashboard query check', [
            'effectiveOutletId' => $effectiveOutletId,
            'totalCompleted' => $totalCompleted,
            'todayStart' => $todayStart,
            'todayEnd' => $todayEnd,
        ]);

        // Completed transactions stats - Use separate queries for better reliability
        try {
            // Today's stats
            $todayCompleted = (clone $completedQuery)
                ->whereBetween('transaction_date', [$todayStart, $todayEnd])
                ->get();
            $transactionsToday = $todayCompleted->count();
            $revenueToday = $todayCompleted->sum('total_amount');

            // This month's stats
            $thisMonthCompleted = (clone $completedQuery)
                ->where('transaction_date', '>=', $thisMonthStart)
                ->get();
            $transactionsThisMonth = $thisMonthCompleted->count();
            $revenueThisMonth = $thisMonthCompleted->sum('total_amount');

            // Last month's stats
            $lastMonthCompleted = (clone $completedQuery)
                ->whereBetween('transaction_date', [$lastMonthStart, $thisMonthStart])
                ->get();
            $revenueLastMonth = $lastMonthCompleted->sum('total_amount');

            $completedStats = (object)[
                'transactions_today' => $transactionsToday,
                'revenue_today' => (float) $revenueToday,
                'transactions_this_month' => $transactionsThisMonth,
                'revenue_this_month' => (float) $revenueThisMonth,
                'revenue_last_month' => (float) $revenueLastMonth,
            ];

            \Illuminate\Support\Facades\Log::info('Completed stats calculated', [
                'transactions_today' => $transactionsToday,
                'revenue_today' => $revenueToday,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error fetching completed stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $completedStats = null;
        }

        // Refunded transactions stats - Use separate queries for better reliability
        try {
            // Today's refunds
            $todayRefunded = (clone $refundedQuery)
                ->whereBetween('transaction_date', [$todayStart, $todayEnd])
                ->sum('total_amount');

            // This month's refunds
            $thisMonthRefunded = (clone $refundedQuery)
                ->where('transaction_date', '>=', $thisMonthStart)
                ->sum('total_amount');

            // Last month's refunds
            $lastMonthRefunded = (clone $refundedQuery)
                ->whereBetween('transaction_date', [$lastMonthStart, $thisMonthStart])
                ->sum('total_amount');

            $refundedStats = (object)[
                'refunds_today' => (float) $todayRefunded,
                'refunds_this_month' => (float) $thisMonthRefunded,
                'refunds_last_month' => (float) $lastMonthRefunded,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error fetching refunded stats', ['error' => $e->getMessage()]);
            $refundedStats = null;
        }

        // Ensure we have valid objects with default values
        if (!$completedStats) {
            $completedStats = (object)[
                'transactions_today' => 0,
                'revenue_today' => 0,
                'transactions_this_month' => 0,
                'revenue_this_month' => 0,
                'revenue_last_month' => 0,
            ];
        }

        if (!$refundedStats) {
            $refundedStats = (object)[
                'refunds_today' => 0,
                'refunds_this_month' => 0,
                'refunds_last_month' => 0,
            ];
        }

        // Convert to arrays and merge
        $completedArray = (array) $completedStats;
        $refundedArray = (array) $refundedStats;

        // Ensure all keys exist
        $completedArray['transactions_today'] = $completedArray['transactions_today'] ?? 0;
        $completedArray['revenue_today'] = $completedArray['revenue_today'] ?? 0;
        $completedArray['transactions_this_month'] = $completedArray['transactions_this_month'] ?? 0;
        $completedArray['revenue_this_month'] = $completedArray['revenue_this_month'] ?? 0;
        $completedArray['revenue_last_month'] = $completedArray['revenue_last_month'] ?? 0;

        $refundedArray['refunds_today'] = $refundedArray['refunds_today'] ?? 0;
        $refundedArray['refunds_this_month'] = $refundedArray['refunds_this_month'] ?? 0;
        $refundedArray['refunds_last_month'] = $refundedArray['refunds_last_month'] ?? 0;

        $transactionStatsRaw = (object) array_merge($completedArray, $refundedArray);

        // Log raw stats for debugging
        \Illuminate\Support\Facades\Log::info('Dashboard raw stats', [
            'completedStats' => $completedArray,
            'refundedStats' => $refundedArray,
            'merged' => (array) $transactionStatsRaw
        ]);

        // Ensure numeric values (handle NULL from database)
        $revenueToday = (float) ($transactionStatsRaw->revenue_today ?? 0);
        $refundsToday = (float) ($transactionStatsRaw->refunds_today ?? 0);
        $revenueThisMonth = (float) ($transactionStatsRaw->revenue_this_month ?? 0);
        $refundsThisMonth = (float) ($transactionStatsRaw->refunds_this_month ?? 0);
        $revenueLastMonth = (float) ($transactionStatsRaw->revenue_last_month ?? 0);
        $refundsLastMonth = (float) ($transactionStatsRaw->refunds_last_month ?? 0);

        $transactionStats = [
            'transactions_today' => (int) ($transactionStatsRaw->transactions_today ?? 0),
            'revenue_today' => $revenueToday,
            'refunds_today' => $refundsToday,
            'net_revenue_today' => $revenueToday - $refundsToday,
            'transactions_this_month' => (int) ($transactionStatsRaw->transactions_this_month ?? 0),
            'revenue_this_month' => $revenueThisMonth,
            'refunds_this_month' => $refundsThisMonth,
            'net_revenue_this_month' => $revenueThisMonth - $refundsThisMonth,
            'revenue_last_month' => $revenueLastMonth,
            'refunds_last_month' => $refundsLastMonth,
            'net_revenue_last_month' => $revenueLastMonth - $refundsLastMonth,
        ];

        // Calculate growth percentage (using net_revenue)
        $revenueGrowth = 0;
        if ($transactionStats['net_revenue_last_month'] > 0) {
            $revenueGrowth = (($transactionStats['net_revenue_this_month'] - $transactionStats['net_revenue_last_month'])
                / $transactionStats['net_revenue_last_month']) * 100;
        }

        // Stock statistics
        $stockQuery = ProductStock::join('products', 'product_stocks.product_id', '=', 'products.id');
        if ($effectiveOutletId) {
            $stockQuery->where('product_stocks.outlet_id', $effectiveOutletId);
        }

        $stockStats = [
            'low_stock_products' => (clone $stockQuery)
                ->whereRaw('product_stocks.quantity <= products.min_stock')
                ->count(),
            'out_of_stock_products' => (clone $stockQuery)
                ->where('product_stocks.quantity', 0)
                ->count(),
            'total_stock_value' => (clone $stockQuery)
                ->sum(DB::raw('product_stocks.quantity * products.purchase_price')),
        ];

        // Recent transactions
        $recentTransactionsQuery = Transaction::with(['customer', 'user', 'outlet'])
            ->orderBy('created_at', 'desc')
            ->take(10);
        if ($effectiveOutletId) {
            $recentTransactionsQuery->where('outlet_id', $effectiveOutletId);
        }
        $recentTransactions = $recentTransactionsQuery->get();

        // Top selling products (this month)
        $topProductsQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.transaction_date', '>=', $thisMonth)
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(transaction_items.quantity) as total_sold'),
                DB::raw('SUM(transaction_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_sold', 'desc')
            ->take(5);

        if ($effectiveOutletId) {
            $topProductsQuery->where('transactions.outlet_id', $effectiveOutletId);
        }
        $topProducts = $topProductsQuery->get();

        // Low stock products - Get ALL low stock products, not just 10
        $lowStockQuery = ProductStock::with(['product', 'outlet'])
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->whereRaw('product_stocks.quantity <= products.min_stock')
            ->select('product_stocks.*')
            ->orderBy('product_stocks.quantity', 'asc');

        if ($effectiveOutletId) {
            $lowStockQuery->where('product_stocks.outlet_id', $effectiveOutletId);
        }

        $lowStockProducts = $lowStockQuery->get();

        // Also get out of stock products separately
        $outOfStockQuery = ProductStock::with(['product', 'outlet'])
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->where('product_stocks.quantity', 0)
            ->select('product_stocks.*')
            ->orderBy('products.name', 'asc');

        if ($effectiveOutletId) {
            $outOfStockQuery->where('product_stocks.outlet_id', $effectiveOutletId);
        }
        $outOfStockProducts = $outOfStockQuery->get();

        // Sales chart data (last 7 days)
        $salesChartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $salesQuery = Transaction::where('status', 'completed')
                ->whereDate('transaction_date', $date);
            if ($effectiveOutletId) {
                $salesQuery->where('outlet_id', $effectiveOutletId);
            }

            $salesChartData[] = [
                'date' => $date,
                'revenue' => (float) ($salesQuery->sum('total_amount') ?? 0),
                'transactions' => (int) ($salesQuery->count() ?? 0),
            ];
        }

            // Log transaction stats for debugging
            \Illuminate\Support\Facades\Log::info('Dashboard transaction stats', [
                'transaction_stats' => $transactionStats,
                'revenue_today' => $transactionStats['revenue_today'],
                'revenue_this_month' => $transactionStats['revenue_this_month'],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'transaction_stats' => $transactionStats,
                    'stock_stats' => $stockStats,
                    'revenue_growth' => round($revenueGrowth, 2),
                    'recent_transactions' => $recentTransactions,
                    'top_products' => $topProducts,
                    'low_stock_products' => $lowStockProducts,
                    'out_of_stock_products' => $outOfStockProducts,
                    'sales_chart_data' => $salesChartData,
                    'user_outlet' => $effectiveOutletId ? Outlet::find($effectiveOutletId) : null,
                    'available_outlets' => $isGlobalAccess ? Outlet::where('is_active', true)->get() : null,
                    'stock_alerts' => [
                        'low_stock_count' => $lowStockProducts->count(),
                        'out_of_stock_count' => $outOfStockProducts->count(),
                        'total_alerts' => $lowStockProducts->count() + $outOfStockProducts->count(),
                    ]
                ]
            ]);
    }

    /**
     * Get outlet comparison data
     */
    public function outletComparison(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->hasRole(['Super Admin', 'Admin', 'Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $period = $request->get('period', 'this_month'); // this_month, last_month, today, yesterday

        [$rangeStart, $rangeEnd] = match($period) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'yesterday' => [
                now()->subDay()->toDateString(),
                now()->subDay()->toDateString()
            ],
            'last_month' => [
                now()->subMonth()->startOfMonth()->toDateString(),
                now()->subMonth()->endOfMonth()->toDateString()
            ],
            default => [now()->startOfMonth()->toDateString(), null],
        };

        $outlets = Outlet::where('is_active', true)->get();
        $comparison = [];

        foreach ($outlets as $outlet) {
            $transactionQuery = $outlet->transactions()->where('status', 'completed');

            if ($rangeEnd) {
                $transactionQuery->whereBetween('transaction_date', [$rangeStart, $rangeEnd]);
            } else {
                $transactionQuery->where('transaction_date', '>=', $rangeStart);
            }

            $comparison[] = [
                'outlet' => $outlet,
                'transactions_count' => $transactionQuery->count(),
                'revenue' => $transactionQuery->sum('total_amount'),
                'avg_transaction_value' => $transactionQuery->avg('total_amount') ?? 0,
                'active_users' => $outlet->users()->where('is_active', true)->count(),
                'low_stock_products' => $outlet->productStocks()
                    ->join('products', 'product_stocks.product_id', '=', 'products.id')
                    ->whereRaw('product_stocks.quantity <= products.min_stock')
                    ->count(),
            ];
        }

        // Sort by revenue
        usort($comparison, function($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'outlets' => $comparison,
                'total_revenue' => array_sum(array_column($comparison, 'revenue')),
                'total_transactions' => array_sum(array_column($comparison, 'transactions_count')),
            ]
        ]);
    }
}
