<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
// use Maatwebsite\Excel\Facades\Excel;
// use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Sales report
     */
    public function sales(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('reports.sales')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|in:cash,transfer,qris,e_wallet',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $query = Transaction::with(['outlet', 'user', 'customer'])
            ->where('status', 'completed');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('transaction_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
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

            // Apply date filter if provided
            if ($request->date_from && $request->date_to) {
                $topProductsQuery->whereBetween('transactions.transaction_date', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59'
                ]);
            }

            $topProducts = $topProductsQuery
                ->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
                ->orderBy('total_sold', 'desc')
                ->limit(10)
                ->get();

            // Get chart data (daily breakdown)
            $chartData = [];

            if ($request->date_from && $request->date_to) {
                $startDate = Carbon::parse($request->date_from);
                $endDate = Carbon::parse($request->date_to);

                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $dayData = Transaction::where('status', 'completed')
                        ->whereDate('transaction_date', $date->format('Y-m-d'))
                        ->selectRaw('
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
            } else {
                // If no date filter, get last 7 days of data
                $endDate = Carbon::now();
                $startDate = Carbon::now()->subDays(6);

                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    $dayData = Transaction::where('status', 'completed')
                        ->whereDate('transaction_date', $date->format('Y-m-d'))
                        ->selectRaw('
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
            }

            // Get customer and product counts
            $totalCustomers = Customer::count();
            $totalProducts = Product::count();
            $customersWithTransactions = Transaction::whereBetween('transaction_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ])
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count();

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
        $perPage = $request->get('per_page', 15);
        $transactions = $query->orderBy('transaction_date', 'desc')->paginate($perPage);

        $summary = [
            'total_transactions' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'total_discount' => $query->sum('discount_amount'),
            'total_tax' => $query->sum('tax_amount'),
            'avg_transaction_value' => $query->avg('total_amount'),
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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('reports.purchases')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'status' => 'nullable|in:pending,partial,paid,cancelled',
        ]);

        $query = Purchase::with(['outlet', 'supplier', 'user']);

        // Only include paid purchases in reports (cancelled purchases should not appear)
        $query->where('status', 'paid');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('purchase_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        // Apply filters
        if ($request->outlet_id) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Allow override for specific status if explicitly requested (for admin purposes)
        if ($request->status && $request->get('include_all_status') === 'true') {
            $query->where('status', $request->status);
        }

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

            // Get top suppliers (only paid purchases)
            $topSuppliers = Purchase::select('suppliers.name', 'suppliers.id')
                ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->where('purchases.status', 'paid')
                ->whereBetween('purchase_date', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59'
                ])
                ->selectRaw('suppliers.name, suppliers.id, COUNT(*) as purchase_count, SUM(total_amount) as total_spent')
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();

            // Get purchase items breakdown (only paid purchases)
            $itemsBreakdown = DB::table('purchase_items')
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->join('products', 'purchase_items.product_id', '=', 'products.id')
                ->where('purchases.status', 'paid')
                ->whereBetween('purchases.purchase_date', [
                    $request->date_from . ' 00:00:00',
                    $request->date_to . ' 23:59:59'
                ])
                ->selectRaw('
                    products.name,
                    products.sku,
                    SUM(purchase_items.quantity) as total_quantity,
                    SUM(purchase_items.total_price) as total_cost,
                    AVG(purchase_items.unit_price) as avg_unit_price
                ')
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

        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $purchases,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Stock report
     */
    public function stocks(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('reports.stocks')) {
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

            $perPage = $request->get('per_page', 15);
            $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'movements' => $movements,
                    'summary' => [
                        'total_movements' => $query->count(),
                        'stock_in' => $query->where('type', 'in')->sum('quantity'),
                        'stock_out' => $query->where('type', 'out')->sum('quantity'),
                        'adjustments' => $query->where('type', 'adjustment')->count(),
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

            $perPage = $request->get('per_page', 15);
            $stocks = $query->paginate($perPage);

            $summary = [
                'total_products' => $query->count(),
                'total_stock_value' => $query->sum(DB::raw('product_stocks.quantity * products.purchase_price')),
                'low_stock_products' => $query->whereRaw('product_stocks.quantity <= products.min_stock')->count(),
                'out_of_stock_products' => $query->where('product_stocks.quantity', 0)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'stocks' => $stocks,
                    'summary' => $summary,
                ]
            ]);
        }
    }
    /**
     * Profit report - FIXED LOGIC
     */
    public function profit(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('reports.profit')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        // Calculate revenue from sales (transactions)
        $revenueQuery = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $revenueQuery->whereBetween('transactions.transaction_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
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

        // Calculate purchase costs (expenses) from purchases
        $purchaseQuery = DB::table('purchases')
            ->where('status', '!=', 'cancelled');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $purchaseQuery->whereBetween('purchase_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
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

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $cogsQuery->whereBetween('transactions.transaction_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $cogsQuery->where('transactions.outlet_id', $request->outlet_id);
        }

        $cogsResult = $cogsQuery->select(
            DB::raw('SUM(transaction_items.quantity * products.purchase_price) as total_cogs')
        )->first();

        // Calculate final profit/loss
        $totalRevenue = $revenueResult->total_revenue ?? 0;
        $totalPurchaseCost = $purchaseResult->total_purchase_cost ?? 0;
        $totalCogs = $cogsResult->total_cogs ?? 0;

        // Profit = Revenue - COGS (not including all purchase costs to avoid double counting)
        $grossProfit = $totalRevenue - $totalCogs;

        // Net profit = Gross profit (we don't subtract all purchases as they become inventory)
        $netProfit = $grossProfit;

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => $totalRevenue,
                'total_purchase_cost' => $totalPurchaseCost, // Total purchases (becomes inventory)
                'total_cogs' => $totalCogs, // Cost of goods actually sold
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'profit_margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
                'total_transactions' => $revenueResult->total_transactions ?? 0,
                'total_purchases' => $purchaseResult->total_purchases ?? 0,
                'total_items_sold' => $revenueResult->total_items_sold ?? 0,
                'purchase_paid' => $purchaseResult->total_paid ?? 0,
                'purchase_remaining' => $purchaseResult->total_remaining ?? 0,
            ]
        ]);
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $limit = $request->get('limit', 10);

        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$request->date_from, $request->date_to])
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
        $user = Auth::user();
        if (!$user || !method_exists($user, 'can') || !$user->can('reports.purchases')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'outlet_id' => 'nullable|exists:outlets,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'status' => 'nullable|in:pending,partial,paid,cancelled',
        ]);

        // Get purchase expenses
        $purchaseQuery = DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->join('outlets', 'purchases.outlet_id', '=', 'outlets.id');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $purchaseQuery->whereBetween('purchases.purchase_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        if ($request->outlet_id) {
            $purchaseQuery->where('purchases.outlet_id', $request->outlet_id);
        }

        if ($request->supplier_id) {
            $purchaseQuery->where('purchases.supplier_id', $request->supplier_id);
        }

        if ($request->status) {
            $purchaseQuery->where('purchases.status', $request->status);
        }

        // Summary data
        $summary = $purchaseQuery->select(
            DB::raw('COUNT(*) as total_purchases'),
            DB::raw('SUM(purchases.total_amount) as total_amount'),
            DB::raw('SUM(purchases.paid_amount) as total_paid'),
            DB::raw('SUM(purchases.remaining_amount) as total_remaining'),
            DB::raw('AVG(purchases.total_amount) as avg_purchase_value')
        )->first();

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

        // Monthly breakdown - SQLite compatible
        $monthlyQuery = DB::table('purchases');

        // Apply date filter if provided
        if ($request->date_from && $request->date_to) {
            $monthlyQuery->whereBetween('purchase_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        $monthlyQuery->when($request->outlet_id, function($query) use ($request) {
                return $query->where('outlet_id', $request->outlet_id);
            })
            ->when($request->supplier_id, function($query) use ($request) {
                return $query->where('supplier_id', $request->supplier_id);
            })
            ->when($request->status, function($query) use ($request) {
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

        // Top purchased items
        $topItems = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->whereBetween('purchases.purchase_date', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ])
            ->when($request->outlet_id, function($query) use ($request) {
                return $query->where('purchases.outlet_id', $request->outlet_id);
            })
            ->select(
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(purchase_items.quantity) as total_quantity'),
                DB::raw('SUM(purchase_items.total_price) as total_cost'),
                DB::raw('AVG(purchase_items.unit_price) as avg_unit_price')
            )
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
                    'payment_completion_rate' => $summary->total_amount > 0 ?
                        round(($summary->total_paid / $summary->total_amount) * 100, 2) : 0,
                ],
                'top_suppliers' => $topSuppliers,
                'monthly_breakdown' => $monthlyBreakdown,
                'top_items' => $topItems,
            ]
        ]);
    }
}
