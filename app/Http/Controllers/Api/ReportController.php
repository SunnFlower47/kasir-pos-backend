<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
// Expense model is used with fully qualified namespace to avoid route model binding conflicts
// use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// use Maatwebsite\Excel\Facades\Excel;
// use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Sales report
     */
    public function sales(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->can('reports.sales')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing reports.sales permission'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|in:cash,transfer,qris,e_wallet',
            'group_by' => 'nullable|in:day,week,month',
            'show_all_data' => 'nullable|boolean',
        ]);

        $query = Transaction::with(['outlet', 'user', 'customer'])
            ->where('status', 'completed');

        // Handle date filtering
        if ($request->show_all_data) {
            // Show all data without date filter
            $dateFrom = null;
            $dateTo = null;
        } else {
            // Set default date range if not provided (last 30 days)
            $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->format('Y-m-d');

            // Apply date filter
            $query->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        // Apply filters
        if ($request->outlet_id) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        // Group by period if requested
        if ($request->group_by) {
            // SQLite compatible group by expressions
            $groupByExpression = match($request->group_by) {
                'day' => 'date(transaction_date)',
                'week' => 'strftime("%Y-%W", transaction_date)',
                'month' => 'strftime("%Y-%m", transaction_date)',
                default => 'date(transaction_date)',
            };

            $groupedData = (clone $query)
                ->select(
                    DB::raw($groupByExpression . ' as period'),
                    DB::raw('COUNT(*) as transactions_count'),
                    DB::raw('SUM(total_amount) as total_revenue'),
                    DB::raw('SUM(discount_amount) as total_discount'),
                    DB::raw('SUM(tax_amount) as total_tax'),
                    DB::raw('AVG(total_amount) as avg_transaction_value')
                )
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            // Get top products
            $topProductsQuery = TransactionItem::select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_sold'),
                DB::raw('SUM(transaction_items.total_price) as total_revenue')
            )
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed');

            // Apply date filter only if not showing all data
            if (!$request->show_all_data && $dateFrom && $dateTo) {
                $topProductsQuery->whereBetween('transactions.transaction_date', [
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59'
                ]);
            }

            if ($request->outlet_id) {
                $topProductsQuery->where('transactions.outlet_id', $request->outlet_id);
            }

            if ($request->user_id) {
                $topProductsQuery->where('transactions.user_id', $request->user_id);
            }

            if ($request->payment_method) {
                $topProductsQuery->where('transactions.payment_method', $request->payment_method);
            }

            $topProducts = $topProductsQuery
                ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
                ->orderBy('total_sold', 'desc')
                ->limit(10)
                ->get();

            // Get chart data (daily breakdown)
            $chartData = [];

            if ($request->show_all_data) {
                // For all data, get last 30 days for chart
                $startDate = now()->subDays(30);
                $endDate = now();
            } else {
                $startDate = Carbon::parse($dateFrom);
                $endDate = Carbon::parse($dateTo);
            }

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dayQuery = Transaction::where('status', 'completed')
                    ->whereDate('transaction_date', $date->format('Y-m-d'));

                // Apply same filters as main query
                if ($request->outlet_id) {
                    $dayQuery->where('outlet_id', $request->outlet_id);
                }
                if ($request->user_id) {
                    $dayQuery->where('user_id', $request->user_id);
                }
                if ($request->payment_method) {
                    $dayQuery->where('payment_method', $request->payment_method);
                }

                $dayData = $dayQuery->selectRaw('
                        date(transaction_date) as date,
                        COUNT(*) as transactions,
                        COALESCE(SUM(total_amount), 0) as revenue
                    ')
                    ->first();

                $chartData[] = [
                    'date' => $date->format('Y-m-d'),
                    'transactions' => $dayData->transactions ?? 0,
                    'revenue' => $dayData->revenue ?? 0
                ];
            }

            // Get customer and product counts
            $totalCustomers = Customer::count();
            $totalProducts = Product::count();
            $customersQuery = Transaction::whereNotNull('customer_id');

            // Apply date filter only if not showing all data
            if (!$request->show_all_data && $dateFrom && $dateTo) {
                $customersQuery->whereBetween('transaction_date', [
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59'
                ]);
            }

            $customersWithTransactions = $customersQuery->distinct('customer_id')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'grouped_data' => $groupedData,
                    'summary' => [
                        'total_transactions' => $groupedData->sum('transactions_count'),
                        'total_revenue' => $groupedData->sum('total_revenue'),
                        'total_discount' => $groupedData->sum('total_discount'),
                        'total_tax' => $groupedData->sum('total_tax'),
                        'avg_transaction_value' => $groupedData->avg('avg_transaction_value'),
                        'total_customers' => $totalCustomers,
                        'total_products' => $totalProducts,
                        'customers_with_transactions' => $customersWithTransactions,
                        'growth' => 0, // TODO: Calculate growth
                    ],
                    'top_products' => $topProducts,
                    'chart_data' => $chartData,
                ]
            ]);
        }

        // Regular detailed report
        // Clone query for summary calculation before pagination
        $summaryQuery = clone $query;

        $perPage = $request->get('per_page', 15);
        $transactions = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        $summary = [
            'total_transactions' => $summaryQuery->count(),
            'total_revenue' => $summaryQuery->sum('total_amount'),
            'total_discount' => $summaryQuery->sum('discount_amount'),
            'total_tax' => $summaryQuery->sum('tax_amount'),
            'avg_transaction_value' => $summaryQuery->avg('total_amount'),
            'customers_with_transactions' => $summaryQuery->whereNotNull('customer_id')->distinct('customer_id')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Purchase report
     */
    public function purchases(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->can('reports.purchases')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing reports.purchases permission'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'status' => 'nullable|in:pending,partial,paid,cancelled',
            'show_all_data' => 'nullable|boolean',
        ]);

        $showAllData = $request->boolean('show_all_data');
        $includeAllStatus = $request->boolean('include_all_status');
        $statusFilter = $request->status;
        $effectiveStatus = ($includeAllStatus && $statusFilter) ? $statusFilter : 'paid';

        // Handle date filtering
        if ($showAllData) {
            $dateFrom = null;
            $dateTo = null;
        } else {
            // Set default date range if not provided (last 30 days)
            $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->format('Y-m-d');
        }

        $applyPurchaseFilters = function ($builder, bool $prefixed = false) use ($request, $showAllData, $dateFrom, $dateTo, $effectiveStatus) {
            $statusColumn = $prefixed ? 'purchases.status' : 'status';
            $dateColumn = $prefixed ? 'purchases.purchase_date' : 'purchase_date';
            $outletColumn = $prefixed ? 'purchases.outlet_id' : 'outlet_id';
            $supplierColumn = $prefixed ? 'purchases.supplier_id' : 'supplier_id';

            $builder->where($statusColumn, $effectiveStatus);

            if (!$showAllData && $dateFrom && $dateTo) {
                $builder->whereBetween($dateColumn, [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
                $builder->where($outletColumn, $request->outlet_id);
        }

        if ($request->supplier_id) {
                $builder->where($supplierColumn, $request->supplier_id);
        }

            return $builder;
        };

        $query = Purchase::with(['outlet', 'supplier', 'user']);
        $query = $applyPurchaseFilters($query);

        // Check if summary is requested
        if ($request->get('summary') === 'true') {
            // Get summary data with grouping
            $summary = $query->selectRaw('
                COUNT(*) as total_purchases,
                SUM(total_amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(remaining_amount) as total_remaining,
                AVG(total_amount) as avg_purchase_value
            ')->first();

            // Get top suppliers (respecting filters)
            $topSuppliersQuery = Purchase::join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->selectRaw('suppliers.name, suppliers.id, COUNT(*) as purchase_count, SUM(total_amount) as total_spent');
            $topSuppliersQuery = $applyPurchaseFilters($topSuppliersQuery, true);
            $topSuppliers = $topSuppliersQuery
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();

            // Get purchase items breakdown (respecting filters)
            $itemsBreakdownQuery = DB::table('purchase_items')
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->join('products', 'purchase_items.product_id', '=', 'products.id')
                ->selectRaw('
                    products.name,
                    products.sku,
                    SUM(purchase_items.quantity) as total_quantity,
                    SUM(purchase_items.total_price) as total_cost,
                    AVG(purchase_items.unit_price) as avg_unit_price
                ');
            $itemsBreakdownQuery = $applyPurchaseFilters($itemsBreakdownQuery, true);
            $itemsBreakdown = $itemsBreakdownQuery
                ->groupBy('products.id', 'products.name', 'products.sku')
                ->orderBy('total_cost', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_purchases' => $summary->total_purchases ?? 0,
                        'total_amount' => $summary->total_amount ?? 0,
                        'total_paid' => $summary->total_paid ?? 0,
                        'total_remaining' => $summary->total_remaining ?? 0,
                        'avg_purchase_value' => $summary->avg_purchase_value ?? 0,
                        'total_suppliers' => $topSuppliers->count(),
                        'total_items' => $itemsBreakdown->sum('total_quantity'),
                    ],
                    'top_suppliers' => $topSuppliers,
                    'top_items' => $itemsBreakdown,
                ]
            ]);
        }

        $perPage = $request->get('per_page', 15);
        $purchases = $query->orderBy('purchase_date', 'desc')->paginate($perPage);

        $summary = [
            'total_purchases' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'total_paid' => $query->sum('paid_amount'),
            'total_remaining' => $query->sum('remaining_amount'),
        ];

        // Get top suppliers for consistency
        $topSuppliersQuery = Purchase::join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->selectRaw('suppliers.name, suppliers.id, COUNT(*) as purchase_count, SUM(total_amount) as total_spent');
        $topSuppliersQuery = $applyPurchaseFilters($topSuppliersQuery, true);
        $topSuppliers = $topSuppliersQuery
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();

        // Get top items for consistency
        $topItemsQuery = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                categories.name as category_name,
                SUM(purchase_items.quantity) as total_sold,
                SUM(purchase_items.total_price) as total_revenue
            ');
        $topItemsQuery = $applyPurchaseFilters($topItemsQuery, true);
        $topItems = $topItemsQuery
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        // Get daily chart data for purchases
        $chartData = [];
        if ($showAllData || !$dateFrom || !$dateTo) {
            $startDate = now()->subDays(30);
            $endDate = now();
        } else {
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        }

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayQuery = DB::table('purchases')
                ->whereDate('purchase_date', $date->format('Y-m-d'))
                ->selectRaw('
                    date(purchase_date) as date,
                    COUNT(*) as transactions_count,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as total_paid
                ');
            $dayQuery = $applyPurchaseFilters($dayQuery, true);
            $dayData = $dayQuery->first();

            $chartData[] = [
                'date' => $date->format('Y-m-d'),
                'period' => $date->format('Y-m-d'),
                'transactions_count' => $dayData->transactions_count ?? 0,
                'total_amount' => $dayData->total_amount ?? 0,
                'total_paid' => $dayData->total_paid ?? 0
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $purchases,
                'summary' => [
                    'total_purchases' => $query->count(),
                    'total_amount' => $query->sum('total_amount'),
                    'total_paid' => $query->sum('paid_amount'),
                    'total_remaining' => $query->sum('remaining_amount'),
                    'avg_purchase_value' => $query->avg('total_amount'),
                    'total_suppliers' => $topSuppliers->count(),
                    'total_items' => $topItems->sum('total_sold'),
                ],
                'top_products' => $topItems,
                'top_suppliers' => $topSuppliers,
                'grouped_data' => $chartData,
            ]
        ]);
    }

    /**
     * Stock report
     */
    public function stocks(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user || !$user->can('reports.stocks')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'category_id' => 'nullable|exists:categories,id',
            'low_stock_only' => 'nullable|boolean',
            'movement_type' => 'nullable|in:in,out,adjustment,transfer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($request->has('date_from') && $request->has('date_to')) {
            // Stock movements report
            $query = StockMovement::with(['product', 'outlet', 'user'])
                ->whereBetween('created_at', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59'
                ]);

            if ($request->outlet_id) {
                $query->where('outlet_id', $request->outlet_id);
            }

            if ($request->movement_type) {
                $query->where('type', $request->movement_type);
            }

            $summaryQuery = clone $query;
            $perPage = $request->get('per_page', 15);
            $movements = (clone $query)->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'movements' => $movements,
                    'summary' => [
                        'total_movements' => (clone $summaryQuery)->count(),
                        'stock_in' => (clone $summaryQuery)->where('type', 'in')->sum('quantity'),
                        'stock_out' => (clone $summaryQuery)->where('type', 'out')->sum('quantity'),
                        'adjustments' => (clone $summaryQuery)->where('type', 'adjustment')->count(),
                    ]
                ]
            ]);
        } else {
            // Current stock report
            $query = DB::table('product_stocks')
                ->join('products', 'product_stocks.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->join('outlets', 'product_stocks.outlet_id', '=', 'outlets.id')
                ->select(
                    'products.name as product_name',
                    'products.sku',
                    'categories.name as category_name',
                    'outlets.name as outlet_name',
                    'product_stocks.quantity',
                    'products.min_stock',
                    'products.purchase_price',
                    'products.selling_price',
                    DB::raw('(product_stocks.quantity * products.purchase_price) as stock_value'),
                    DB::raw('CASE WHEN product_stocks.quantity <= products.min_stock THEN 1 ELSE 0 END as is_low_stock')
                );

            if ($request->outlet_id) {
                $query->where('product_stocks.outlet_id', $request->outlet_id);
            }

            if ($request->category_id) {
                $query->where('products.category_id', $request->category_id);
            }

            if ($request->boolean('low_stock_only')) {
                $query->whereRaw('product_stocks.quantity <= products.min_stock');
            }

            $summaryQuery = clone $query;
            $perPage = $request->get('per_page', 15);
            $stocks = (clone $query)->paginate($perPage);

            $summary = [
                'total_products' => (clone $summaryQuery)->count(),
                'total_stock_value' => (clone $summaryQuery)->sum(DB::raw('product_stocks.quantity * products.purchase_price')),
                'low_stock_products' => (clone $summaryQuery)->whereRaw('product_stocks.quantity <= products.min_stock')->count(),
                'out_of_stock_products' => (clone $summaryQuery)->where('product_stocks.quantity', 0)->count(),
            ];

            // Get top products by stock movement
            $topStockProducts = DB::table('stock_movements')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->selectRaw('
                    products.id,
                    products.name,
                    products.sku,
                    categories.name as category_name,
                    SUM(CASE WHEN stock_movements.type = "in" THEN stock_movements.quantity ELSE 0 END) as stock_in,
                    SUM(CASE WHEN stock_movements.type = "out" THEN stock_movements.quantity ELSE 0 END) as stock_out,
                    SUM(CASE WHEN stock_movements.type = "in" THEN stock_movements.quantity ELSE -stock_movements.quantity END) as net_movement,
                    COUNT(*) as movement_count
                ')
                ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
                ->orderBy('movement_count', 'desc')
                ->limit(10)
                ->get();

            // Get daily stock movement chart data
            $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->format('Y-m-d');

            $chartData = [];
            $startDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dayData = DB::table('stock_movements')
                    ->whereDate('created_at', $date->format('Y-m-d'))
                    ->selectRaw('
                        date(created_at) as date,
                        SUM(CASE WHEN type = "in" THEN quantity ELSE 0 END) as stock_in,
                        SUM(CASE WHEN type = "out" THEN quantity ELSE 0 END) as stock_out,
                        COUNT(*) as movements_count
                    ')
                    ->first();

                $chartData[] = [
                    'date' => $date->format('Y-m-d'),
                    'period' => $date->format('Y-m-d'),
                    'stock_in' => $dayData->stock_in ?? 0,
                    'stock_out' => $dayData->stock_out ?? 0,
                    'movements_count' => $dayData->movements_count ?? 0,
                    'net_movement' => ($dayData->stock_in ?? 0) - ($dayData->stock_out ?? 0)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'stocks' => $stocks,
                    'summary' => $summary,
                    'top_products' => $topStockProducts,
                    'grouped_data' => $chartData,
                ]
            ]);
        }
    }
    /**
     * Profit report - FIXED LOGIC
     */
    public function profit(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->can('reports.profit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing reports.profit permission'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'show_all_data' => 'nullable|boolean',
        ]);

        // Handle date filtering
        if ($request->show_all_data) {
            // Show all data without date filter
            $dateFrom = null;
            $dateTo = null;
        } else {
            // Set default date range if not provided (last 30 days)
            $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->format('Y-m-d');
        }

        // Calculate revenue from sales (transactions)
        $revenueQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $revenueQuery->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $revenueQuery->where('transactions.outlet_id', $request->outlet_id);
        }

        $revenueResult = $revenueQuery->select(
            DB::raw('SUM(transaction_items.total_price) as total_revenue'),
            DB::raw('COUNT(DISTINCT transactions.id) as total_transactions'),
            DB::raw('SUM(transaction_items.quantity) as total_items_sold')
        )->first();

        // Calculate refunds from transactions created in the same period
        $refundQuery = DB::table('transactions')
            ->where('status', 'refunded');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $refundQuery->whereBetween('transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $refundQuery->where('outlet_id', $request->outlet_id);
        }

        $refundResult = $refundQuery->select(
            DB::raw('SUM(total_amount) as total_refunds'),
            DB::raw('COUNT(*) as refund_count')
        )->first();

        // Calculate purchase costs (expenses) from purchases
        $purchaseQuery = DB::table('purchases')
            ->where('status', '!=', 'cancelled');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $purchaseQuery->whereBetween('purchase_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $purchaseQuery->where('outlet_id', $request->outlet_id);
        }

        $purchaseResult = $purchaseQuery->select(
            DB::raw('SUM(total_amount) as total_purchase_cost'),
            DB::raw('COUNT(*) as total_purchases'),
            DB::raw('SUM(paid_amount) as total_paid'),
            DB::raw('SUM(remaining_amount) as total_remaining')
        )->first();

        // Calculate COGS (Cost of Goods Sold) based on actual sold items
        $cogsQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $cogsQuery->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $cogsQuery->where('transactions.outlet_id', $request->outlet_id);
        }

        $cogsResult = $cogsQuery->select(
            DB::raw('SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cogs')
        )->first();

        // Calculate final profit/loss - FIXED CALCULATION
        $totalRevenue = $revenueResult->total_revenue ?? 0;
        $totalRefunds = $refundResult->total_refunds ?? 0;
        $netRevenue = $totalRevenue - $totalRefunds;
        $totalPurchaseCost = $purchaseResult->total_purchase_cost ?? 0;
        $totalCogs = $cogsResult->total_cogs ?? 0;

        // Get operational expenses (from expenses table)
        $operationalExpenseQuery = DB::table('expenses')
            ->whereBetween('expense_date', [$dateFrom ?? '1900-01-01', $dateTo ?? '9999-12-31']);

        if ($request->outlet_id) {
            $operationalExpenseQuery->where('outlet_id', $request->outlet_id);
        }
        $totalOperationalExpenses = (float) ($operationalExpenseQuery->sum('amount') ?? 0);

        // Gross Profit = Net Revenue - COGS (Cost of Goods Sold)
        $grossProfit = $netRevenue - $totalCogs;

        // Operating Expenses calculation (Accrual Basis - Standard Accounting):
        // - Operational Expenses: biaya operasional (sewa, listrik, gaji, dll)
        // - Unsold Inventory Expense: Purchase Expenses yang belum menjadi COGS (barang yang dibeli tapi belum terjual)
        // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
        // Logika: Jika Purchase Expenses > COGS, ada barang yang belum terjual (expense)
        //         Jika Purchase Expenses <= COGS, semua purchase sudah terjual (tidak perlu ditambahkan, karena COGS sudah dikurangkan)
        $unsoldInventoryExpense = max(0, $totalPurchaseCost - $totalCogs);
        $operatingExpenses = $totalOperationalExpenses + $unsoldInventoryExpense;

        // Net Profit = Gross Profit - Operating Expenses
        $netProfit = $grossProfit - $operatingExpenses;

        // Get top profitable products for consistency
        $topProducts = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $topProducts->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);
        }

        $topProducts = $topProducts->select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_sold'),
                DB::raw('SUM(transaction_items.total_price) as total_revenue'),
                DB::raw('SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cost'),
                DB::raw('SUM(transaction_items.total_price - (transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price))) as total_profit')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderBy('total_profit', 'desc')
            ->limit(10)
            ->get();

        // Get daily profit chart data
        $chartData = [];
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Daily revenue
            $dailyRevenue = DB::table('transactions')
                ->where('status', 'completed')
                ->whereDate('transaction_date', $date->format('Y-m-d'))
                ->selectRaw('
                    COALESCE(SUM(total_amount), 0) as daily_revenue,
                    COUNT(*) as transactions_count
                ')
                ->first();

            // Daily refunds
            $dailyRefunds = DB::table('transactions')
                ->where('status', 'refunded')
                ->whereDate('transaction_date', $date->format('Y-m-d'))
                ->selectRaw('COALESCE(SUM(total_amount), 0) as daily_refunds')
                ->first();

            // Daily COGS
            $dailyCogs = DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->where('transactions.status', 'completed')
                ->whereDate('transactions.transaction_date', $date->format('Y-m-d'))
                ->selectRaw('
                    COALESCE(SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)), 0) as daily_cogs
                ')
                ->first();

            $revenue = $dailyRevenue->daily_revenue ?? 0;
            $refunds = $dailyRefunds->daily_refunds ?? 0;
            $netRevenue = $revenue - $refunds;
            $cogs = $dailyCogs->daily_cogs ?? 0;

            // Daily purchase expenses
            $dailyPurchaseExpenses = DB::table('purchases')
                ->where('status', 'paid')
                ->whereDate('purchase_date', $date->format('Y-m-d'))
                ->when($request->outlet_id, function($query) use ($request) {
                    return $query->where('outlet_id', $request->outlet_id);
                })
                ->sum('total_amount');

            // Daily operational expenses
            $dailyOperationalExpenses = DB::table('expenses')
                ->whereDate('expense_date', $date->format('Y-m-d'))
                ->when($request->outlet_id, function($query) use ($request) {
                    return $query->where('outlet_id', $request->outlet_id);
                })
                ->sum('amount');

            // Calculate profit according to accounting principles (Accrual Basis)
            $grossProfit = $netRevenue - $cogs;
            // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
            // Hanya purchase expenses yang belum menjadi COGS yang dihitung sebagai expense
            $dailyUnsoldInventoryExpense = max(0, $dailyPurchaseExpenses - $cogs);
            $dailyOperatingExpenses = $dailyOperationalExpenses + $dailyUnsoldInventoryExpense;
            $profit = $grossProfit - $dailyOperatingExpenses; // Net profit

            $chartData[] = [
                'date' => $date->format('Y-m-d'),
                'period' => $date->format('Y-m-d'),
                'total_revenue' => $revenue,
                'total_refunds' => $refunds,
                'net_revenue' => $netRevenue,
                'total_cogs' => $cogs,
                'net_profit' => $profit,
                'transactions_count' => $dailyRevenue->transactions_count ?? 0,
                'profit_margin' => $netRevenue > 0 ? round(($profit / $netRevenue) * 100, 2) : 0
            ];
        }

        $profitMargin = $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => [
                    'total' => $totalRevenue,
                    'refunds' => $totalRefunds,
                    'net_revenue' => $netRevenue,
                    'transaction_count' => $revenueResult->total_transactions ?? 0,
                    'refund_count' => $refundResult->refund_count ?? 0,
                ],
                'total_cost' => $totalCogs,
                'total_profit' => $netProfit,
                'profit_margin' => $profitMargin,
                'summary' => [
                    'total_purchase_cost' => $totalPurchaseCost,
                    'total_operational_expenses' => $totalOperationalExpenses,
                    'total_expenses' => $totalPurchaseCost + $totalOperationalExpenses,
                    'total_cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'net_profit' => $netProfit,
                    'operating_expenses' => $operatingExpenses,
                    'gross_margin' => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0,
                    'net_margin' => $profitMargin,
                    'total_transactions' => $revenueResult->total_transactions ?? 0,
                    'total_purchases' => $purchaseResult->total_purchases ?? 0,
                    'total_items_sold' => $revenueResult->total_items_sold ?? 0,
                    'purchase_paid' => $purchaseResult->total_paid ?? 0,
                    'purchase_remaining' => $purchaseResult->total_remaining ?? 0,
                ],
                'top_products' => $topProducts,
                'grouped_data' => $chartData,
            ]
        ]);
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request): JsonResponse
    {
        /** @var User $user */

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->can('reports.sales')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Missing reports.sales permission'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'limit' => 'nullable|integer|min:1|max:100',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $limit = $request->get('limit', 10);

        // Set default date range if not provided (last 30 days)
        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->format('Y-m-d');

        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                DB::raw('SUM(transaction_items.quantity) as total_sold'),
                DB::raw('SUM(transaction_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name');

        if ($request->outlet_id) {
            $query->where('transactions.outlet_id', $request->outlet_id);
        }

        $topProducts = $query->orderBy('total_sold', 'desc')->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $topProducts
        ]);
    }

    /**
     * Expense/Purchase report - NEW
     */
    public function expenses(Request $request): JsonResponse
    {
        try {
            /** @var User $user */

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            if (!$user->can('reports.purchases')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Missing reports.purchases permission'
                ], 403);
            }

            $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'outlet_id' => 'nullable|exists:outlets,id',
                'supplier_id' => 'nullable|exists:suppliers,id',
                'status' => 'nullable|in:pending,partial,paid,cancelled',
            ]);

            // Set default date range if not provided (last 30 days)
            $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->format('Y-m-d');

        // Get purchase expenses (only paid purchases for expenses report)
        $purchaseQuery = DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->join('outlets', 'purchases.outlet_id', '=', 'outlets.id')
            ->where('purchases.status', 'paid') // Only include paid purchases in expenses
            ->whereBetween('purchases.purchase_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ]);

        if ($request->outlet_id) {
            $purchaseQuery->where('purchases.outlet_id', $request->outlet_id);
        }

        if ($request->supplier_id) {
            $purchaseQuery->where('purchases.supplier_id', $request->supplier_id);
        }

        // Allow override for specific status if explicitly requested (for admin purposes)
        if ($request->status && $request->get('include_all_status') === 'true') {
            $purchaseQuery->where('purchases.status', $request->status);
        }

        // Summary data - Purchase expenses
        $summary = $purchaseQuery->select(
            DB::raw('COUNT(*) as total_purchases'),
            DB::raw('SUM(purchases.total_amount) as total_amount'),
            DB::raw('SUM(purchases.paid_amount) as total_paid'),
            DB::raw('SUM(purchases.remaining_amount) as total_remaining'),
            DB::raw('AVG(purchases.total_amount) as avg_purchase_value')
        )->first();

        // Operational expenses (from expenses table) - Use DB query builder to avoid namespace issues
        $operationalExpenseQuery = DB::table('expenses')
            ->whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($request->outlet_id) {
            $operationalExpenseQuery->where('outlet_id', $request->outlet_id);
        }
        $operationalExpenses = (float) ($operationalExpenseQuery->sum('amount') ?? 0);
        $operationalExpenseCount = (int) ($operationalExpenseQuery->count() ?? 0);

        // Top suppliers by expense
        $topSuppliers = $purchaseQuery->select(
            'suppliers.name as supplier_name',
            'suppliers.id as supplier_id',
            DB::raw('COUNT(*) as purchase_count'),
            DB::raw('SUM(purchases.total_amount) as total_spent')
        )
        ->groupBy('suppliers.id', 'suppliers.name')
        ->orderBy('total_spent', 'desc')
        ->limit(10)
        ->get();

        // Monthly breakdown - SQLite compatible (only paid purchases)
        $monthlyQuery = DB::table('purchases')
            ->where('status', 'paid'); // Only include paid purchases

        // Apply date filter (always apply default range)
        $monthlyQuery->whereBetween('purchase_date', [
            $dateFrom . ' 00:00:00',
            $dateTo . ' 23:59:59'
        ]);

        $monthlyQuery->when($request->outlet_id, function($query) use ($request) {
                return $query->where('outlet_id', $request->outlet_id);
            })
            ->when($request->supplier_id, function($query) use ($request) {
                return $query->where('supplier_id', $request->supplier_id);
            })
            ->when($request->status && $request->get('include_all_status') === 'true', function($query) use ($request) {
                return $query->where('status', $request->status);
            });

        // Use SQLite compatible date formatting
        $monthlyBreakdown = $monthlyQuery->select(
                DB::raw('strftime("%Y-%m", purchase_date) as month'),
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('SUM(paid_amount) as total_paid')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top purchased items (only from paid purchases) - Format konsisten dengan sales
        $topItems = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('purchases.status', 'paid') // Only include paid purchases
            ->whereBetween('purchases.purchase_date', [
                $dateFrom . ' 00:00:00',
                $dateTo . ' 23:59:59'
            ])
            ->when($request->outlet_id, function($query) use ($request) {
                return $query->where('purchases.outlet_id', $request->outlet_id);
            })
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                'categories.name as category_name',
                DB::raw('SUM(purchase_items.quantity) as total_sold'),
                DB::raw('SUM(purchase_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get();

        // Get daily chart data for expenses (purchase + operational)
        $dailyExpenses = [];
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Purchase expenses for this day
            $dayPurchaseQuery = DB::table('purchases')
                ->where('status', 'paid')
                ->whereDate('purchase_date', $date->format('Y-m-d'));
            if ($request->outlet_id) {
                $dayPurchaseQuery->where('outlet_id', $request->outlet_id);
            }
            $dayPurchaseData = $dayPurchaseQuery->selectRaw('
                    COUNT(*) as purchase_count,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(SUM(paid_amount), 0) as total_paid
                ')
                ->first();

            // Operational expenses for this day - Use DB query builder to avoid namespace issues
            $dayOperationalQuery = DB::table('expenses')
                ->whereDate('expense_date', $date->format('Y-m-d'));
            if ($request->outlet_id) {
                $dayOperationalQuery->where('outlet_id', $request->outlet_id);
            }
            $dayOperationalExpense = (float) ($dayOperationalQuery->sum('amount') ?? 0);
            $dayOperationalCount = (int) ($dayOperationalQuery->count() ?? 0);

            $totalDayExpense = ($dayPurchaseData->total_amount ?? 0) + $dayOperationalExpense;

            $dailyExpenses[] = [
                'date' => $date->format('Y-m-d'),
                'period' => $date->format('Y-m-d'),
                'purchase_count' => $dayPurchaseData->purchase_count ?? 0,
                'operational_count' => $dayOperationalCount,
                'total_amount' => $totalDayExpense,
                'purchase_amount' => $dayPurchaseData->total_amount ?? 0,
                'operational_amount' => $dayOperationalExpense,
                'total_paid' => $dayPurchaseData->total_paid ?? 0
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_purchases' => (int) ($summary->total_purchases ?? 0),
                    'total_amount' => (float) (($summary->total_amount ?? 0) + $operationalExpenses),
                    'purchase_amount' => (float) ($summary->total_amount ?? 0),
                    'operational_amount' => (float) $operationalExpenses,
                    'operational_count' => (int) $operationalExpenseCount,
                    'total_paid' => (float) ($summary->total_paid ?? 0),
                    'total_remaining' => (float) ($summary->total_remaining ?? 0),
                    'avg_purchase_value' => (float) ($summary->avg_purchase_value ?? 0),
                    'total_suppliers' => (int) $topSuppliers->count(),
                    'total_items' => (float) ($topItems->sum('total_sold') ?? 0),
                    'payment_completion_rate' => ($summary->total_amount ?? 0) > 0 ?
                        round((($summary->total_paid ?? 0) / ($summary->total_amount ?? 1)) * 100, 2) : 0,
                ],
                'top_products' => $topItems,
                'top_suppliers' => $topSuppliers,
                'monthly_breakdown' => $monthlyBreakdown,
                'grouped_data' => $dailyExpenses,
            ]
        ]);
        } catch (\Exception $e) {
            Log::error('Expenses Report Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate expenses report: ' . $e->getMessage()
            ], 500);
        }
    }
}
