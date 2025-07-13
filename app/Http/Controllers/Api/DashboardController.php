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

        // Transaction statistics
        $transactionQuery = Transaction::where('status', 'completed');
        if ($effectiveOutletId) {
            $transactionQuery->where('outlet_id', $effectiveOutletId);
        }

        $transactionStats = [
            'transactions_today' => (clone $transactionQuery)->whereDate('transaction_date', $today)->count(),
            'revenue_today' => (clone $transactionQuery)->whereDate('transaction_date', $today)->sum('total_amount'),
            'transactions_this_month' => (clone $transactionQuery)->whereDate('transaction_date', '>=', $thisMonth)->count(),
            'revenue_this_month' => (clone $transactionQuery)->whereDate('transaction_date', '>=', $thisMonth)->sum('total_amount'),
            'revenue_last_month' => (clone $transactionQuery)
                ->whereBetween('transaction_date', [$lastMonth, $thisMonth])
                ->sum('total_amount'),
        ];

        // Calculate growth percentage
        $revenueGrowth = 0;
        if ($transactionStats['revenue_last_month'] > 0) {
            $revenueGrowth = (($transactionStats['revenue_this_month'] - $transactionStats['revenue_last_month'])
                / $transactionStats['revenue_last_month']) * 100;
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
            if ($userOutletId) {
                $salesQuery->where('outlet_id', $userOutletId);
            }

            $salesChartData[] = [
                'date' => $date,
                'revenue' => $salesQuery->sum('total_amount'),
                'transactions' => $salesQuery->count(),
            ];
        }

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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'hasRole') || !$user->hasRole(['Super Admin', 'Admin', 'Manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $period = $request->get('period', 'this_month'); // this_month, last_month, today, yesterday

        $dateFilter = match($period) {
            'today' => ['>=', today()],
            'yesterday' => ['>=', now()->subDay()->startOfDay(), '<=', now()->subDay()->endOfDay()],
            'this_month' => ['>=', now()->startOfMonth()],
            'last_month' => ['>=', now()->subMonth()->startOfMonth(), '<=', now()->subMonth()->endOfMonth()],
            default => ['>=', now()->startOfMonth()],
        };

        $outlets = Outlet::where('is_active', true)->get();
        $comparison = [];

        foreach ($outlets as $outlet) {
            $transactionQuery = $outlet->transactions()->where('status', 'completed');

            if (count($dateFilter) === 2) {
                $transactionQuery->where('transaction_date', $dateFilter[0], $dateFilter[1]);
            } else {
                $transactionQuery->whereBetween('transaction_date', [$dateFilter[1], $dateFilter[2]]);
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
