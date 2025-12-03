<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinancialReportController extends Controller
{
    /**
     * Comprehensive Financial Report
     */
    public function comprehensive(Request $request): JsonResponse
    {
        try {
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
                'period' => 'nullable|in:monthly,quarterly,yearly',
            ]);

            $dateFrom = $request->date_from ?? now()->startOfMonth()->format('Y-m-d');
            $dateTo = $request->date_to ?? now()->endOfMonth()->format('Y-m-d');
            $outletId = $request->outlet_id ? (int)$request->outlet_id : null;
            $period = $request->period ?? 'monthly';

            // 1. PENDAPATAN (REVENUE)
            $revenue = $this->calculateRevenue($dateFrom, $dateTo, $outletId);

            // 2. PENGELUARAN (EXPENSES)
            $expenses = $this->calculateExpenses($dateFrom, $dateTo, $outletId);

            // 3. HPP (COST OF GOODS SOLD)
            $cogs = $this->calculateCOGS($dateFrom, $dateTo, $outletId);

            // 4. LABA RUGI
            $profitLoss = $this->calculateProfitLoss($revenue, $expenses, $cogs);

            // 5. ANALISIS BULANAN
            $monthlyAnalysis = $this->getMonthlyAnalysis($dateFrom, $dateTo, $outletId, $period);

            // 6. BREAKDOWN PENGELUARAN
            $expenseBreakdown = $this->getExpenseBreakdown($dateFrom, $dateTo, $outletId);

            // 7. CASH FLOW
            $cashFlow = $this->calculateCashFlow($dateFrom, $dateTo, $outletId);

            // 8. RATIO KEUANGAN
            $financialRatios = $this->calculateFinancialRatios($revenue, $expenses, $cogs, $profitLoss);

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'from' => $dateFrom,
                        'to' => $dateTo,
                        'type' => $period
                    ],
                    'revenue' => $revenue,
                    'expenses' => $expenses,
                    'cogs' => $cogs,
                    'profit_loss' => $profitLoss,
                    'monthly_analysis' => $monthlyAnalysis,
                    'expense_breakdown' => $expenseBreakdown,
                    'cash_flow' => $cashFlow,
                    'financial_ratios' => $financialRatios,
                    'summary' => [
                        'total_revenue' => $revenue['total'] ?? 0,
                        'total_refunds' => $revenue['refunds'] ?? 0,
                        'net_revenue' => $revenue['net_revenue'] ?? 0,
                        'total_expenses' => $expenses['total'] ?? 0,
                        'purchase_expenses' => $expenses['purchase_expenses'] ?? 0,
                        'operational_expenses' => $expenses['operational_expenses'] ?? 0,
                        'total_cogs' => $cogs['total'] ?? 0,
                        'gross_profit' => $profitLoss['gross_profit'] ?? 0,
                        'net_profit' => $profitLoss['net_profit'] ?? 0,
                        'profit_margin' => $profitLoss['net_profit_margin'] ?? 0,
                        'gross_profit_margin' => $profitLoss['gross_profit_margin'] ?? 0,
                        'expense_ratio' => $financialRatios['expense_ratio'] ?? 0
                    ]
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Financial Report Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate financial report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate Revenue
     */
    private function calculateRevenue(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $query = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }

        $totalRevenue = $query->sum('total_amount');
        $totalTransactions = $query->count();
        $avgTransactionValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Calculate refunds from transactions created in the same period
        $refundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $refundQuery->where('outlet_id', $outletId);
        }

        $totalRefunds = $refundQuery->sum('total_amount');
        $refundCount = $refundQuery->count();
        $netRevenue = $totalRevenue - $totalRefunds;

        // Revenue by payment method
        $revenueByPayment = $query->selectRaw('
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_transaction_value
        ')
        ->groupBy('payment_method')
        ->get()
        ->map(function ($item) use ($dateFrom, $dateTo, $outletId) {
            // Calculate refunds for this payment method
            $refundQuery = Transaction::where('status', 'refunded')
                ->where('payment_method', $item->payment_method)
                ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($outletId) {
                $refundQuery->where('outlet_id', $outletId);
            }

            $refunds = $refundQuery->sum('total_amount');
            $item->refunds = (float) $refunds;
            $item->net_revenue = (float) $item->total_revenue - (float) $refunds;

            return $item;
        });

        // Revenue by day (with refunds per day)
        $revenueByDay = [];
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayQuery = Transaction::where('status', 'completed')
                ->whereDate('transaction_date', $date->format('Y-m-d'));

            if ($outletId) {
                $dayQuery->where('outlet_id', $outletId);
            }

            $dayRevenue = $dayQuery->sum('total_amount');
            $dayTransactions = $dayQuery->count();

            // Calculate refunds for this day
            $dayRefundQuery = Transaction::where('status', 'refunded')
                ->whereDate('transaction_date', $date->format('Y-m-d'));

            if ($outletId) {
                $dayRefundQuery->where('outlet_id', $outletId);
            }

            $dayRefunds = $dayRefundQuery->sum('total_amount');
            $dayNetRevenue = $dayRevenue - $dayRefunds;

            $revenueByDay[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'revenue' => $dayRevenue,
                'refunds' => $dayRefunds,
                'net_revenue' => $dayNetRevenue,
                'transactions' => $dayTransactions,
                'avg_transaction' => $dayTransactions > 0 ? $dayRevenue / $dayTransactions : 0
            ];
        }

        return [
            'total' => $totalRevenue,
            'refunds' => $totalRefunds,
            'net_revenue' => $netRevenue,
            'transaction_count' => $totalTransactions,
            'refund_count' => $refundCount,
            'avg_transaction_value' => $avgTransactionValue,
            'by_payment_method' => $revenueByPayment,
            'by_day' => $revenueByDay
        ];
    }

    /**
     * Calculate Expenses
     */
    private function calculateExpenses(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        // 1. Pengeluaran Pembelian (Purchase Expenses)
        $purchaseQuery = Purchase::where('status', 'paid')
            ->whereBetween('purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $purchaseQuery->where('outlet_id', $outletId);
        }

        $purchaseExpenses = $purchaseQuery->sum('total_amount');
        $purchaseCount = $purchaseQuery->count();

        // 2. Pengeluaran Operasional (Operational Expenses dari tabel expenses)
        $operationalExpenseQuery = Expense::whereBetween('expense_date', [$dateFrom, $dateTo]);

        if ($outletId) {
            $operationalExpenseQuery->where('outlet_id', $outletId);
        }

        $operationalExpenses = $operationalExpenseQuery->sum('amount');
        $operationalExpenseCount = $operationalExpenseQuery->count();

        // 3. Total Pengeluaran
        $totalExpenses = $purchaseExpenses + $operationalExpenses;

        // Breakdown pengeluaran per supplier
        $expensesBySupplier = $purchaseQuery->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->selectRaw('
                suppliers.name as supplier_name,
                suppliers.id as supplier_id,
                COUNT(*) as purchase_count,
                SUM(purchases.total_amount) as total_expense,
                AVG(purchases.total_amount) as avg_purchase_value
            ')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderBy('total_expense', 'desc')
            ->get();

        // Pengeluaran per hari (purchase + operational)
        $expensesByDay = [];
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // Purchase expenses for this day
            $dayPurchaseQuery = Purchase::where('status', 'paid')
                ->whereDate('purchase_date', $date->format('Y-m-d'));
            if ($outletId) {
                $dayPurchaseQuery->where('outlet_id', $outletId);
            }
            $dayPurchaseExpense = $dayPurchaseQuery->sum('total_amount');
            $dayPurchases = $dayPurchaseQuery->count();

            // Operational expenses for this day
            $dayOperationalQuery = Expense::whereDate('expense_date', $date->format('Y-m-d'));
            if ($outletId) {
                $dayOperationalQuery->where('outlet_id', $outletId);
            }
            $dayOperationalExpense = $dayOperationalQuery->sum('amount');
            $dayOperationalCount = $dayOperationalQuery->count();

            $totalDayExpense = $dayPurchaseExpense + $dayOperationalExpense;

            $expensesByDay[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('l'),
                'expense' => $totalDayExpense,
                'purchase_expense' => $dayPurchaseExpense,
                'operational_expense' => $dayOperationalExpense,
                'purchases' => $dayPurchases,
                'operational_count' => $dayOperationalCount,
                'avg_purchase' => $dayPurchases > 0 ? $dayPurchaseExpense / $dayPurchases : 0
            ];
        }

        return [
            'total' => $totalExpenses,
            'purchase_expenses' => $purchaseExpenses,
            'operational_expenses' => $operationalExpenses,
            'purchase_count' => $purchaseCount,
            'by_supplier' => $expensesBySupplier,
            'by_day' => $expensesByDay
        ];
    }

    /**
     * Calculate Cost of Goods Sold (COGS)
     */
    private function calculateCOGS(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        $baseQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $baseQuery->where('transactions.outlet_id', $outletId);
        }

        $totalCOGS = (clone $baseQuery)->sum(DB::raw('transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)'));
        $totalItemsSold = (clone $baseQuery)->sum('transaction_items.quantity');

        // COGS by product category - buat query baru
        $cogsByCategoryQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $cogsByCategoryQuery->where('transactions.outlet_id', $outletId);
        }

        $cogsByCategory = $cogsByCategoryQuery->selectRaw('
                categories.id as category_id,
                categories.name as category_name,
                SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cogs,
                SUM(transaction_items.quantity) as total_quantity,
                AVG(COALESCE(transaction_items.purchase_price, products.purchase_price)) as avg_purchase_price
            ')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price))'))
            ->get();

        // COGS by product - buat query baru
        $cogsByProductQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $cogsByProductQuery->where('transactions.outlet_id', $outletId);
        }

        $cogsByProduct = $cogsByProductQuery->selectRaw('
                products.id,
                products.name,
                products.sku,
                categories.id as category_id,
                categories.name as category_name,
                SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)) as total_cogs,
                SUM(transaction_items.quantity) as total_quantity,
                products.purchase_price as current_purchase_price,
                AVG(COALESCE(transaction_items.purchase_price, products.purchase_price)) as avg_purchase_price_at_transaction,
                AVG(transaction_items.unit_price) as avg_selling_price
            ')
            ->groupBy('products.id', 'products.name', 'products.sku', 'categories.id', 'categories.name', 'products.purchase_price')
            ->orderByDesc(DB::raw('SUM(transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price))'))
            ->limit(20)
            ->get();

        return [
            'total' => $totalCOGS,
            'total_items_sold' => $totalItemsSold,
            'avg_cogs_per_item' => $totalItemsSold > 0 ? $totalCOGS / $totalItemsSold : 0,
            'by_category' => $cogsByCategory,
            'by_product' => $cogsByProduct
        ];
    }

    /**
     * Calculate Profit & Loss
     */
    private function calculateProfitLoss(array $revenue, array $expenses, array $cogs): array
    {
        $totalRevenue = $revenue['total'];
        $netRevenue = $revenue['net_revenue'] ?? $totalRevenue; // Use net_revenue if available
        $totalExpenses = $expenses['total'];
        $purchaseExpenses = $expenses['purchase_expenses'] ?? 0;
        $operationalExpenses = $expenses['operational_expenses'] ?? 0;
        $totalCOGS = $cogs['total'];

        // Gross Profit = Net Revenue - COGS
        $grossProfit = $netRevenue - $totalCOGS;

        // Operating Expenses calculation (Accrual Basis - Standard Accounting):
        // - Operational Expenses: biaya operasional (sewa, listrik, gaji, dll)
        // - Unsold Inventory Expense: Purchase Expenses yang belum menjadi COGS (barang yang dibeli tapi belum terjual)
        // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
        // Logika: Jika Purchase Expenses > COGS, ada barang yang belum terjual (expense)
        //         Jika Purchase Expenses <= COGS, semua purchase sudah terjual (tidak perlu ditambahkan, karena COGS sudah dikurangkan)
        $unsoldInventoryExpense = max(0, $purchaseExpenses - $totalCOGS);
        $operatingExpenses = $operationalExpenses + $unsoldInventoryExpense;

        // Net Profit = Gross Profit - Operating Expenses
        $netProfit = $grossProfit - $operatingExpenses;

        // Profit Margins (use net_revenue for calculations)
        $grossProfitMargin = $netRevenue > 0 ? ($grossProfit / $netRevenue) * 100 : 0;
        $netProfitMargin = $netRevenue > 0 ? ($netProfit / $netRevenue) * 100 : 0;

        return [
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'operating_expenses' => $operatingExpenses,
            'gross_profit_margin' => round($grossProfitMargin, 2),
            'net_profit_margin' => round($netProfitMargin, 2),
            'is_profitable' => $netProfit > 0
        ];
    }

    /**
     * Get Monthly Analysis
     */
    private function getMonthlyAnalysis(string $dateFrom, string $dateTo, ?int $outletId, string $period): array
    {
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $months = [];

        // Generate months in the range
        $current = $startDate->copy()->startOfMonth();
        while ($current->lte($endDate)) {
            $monthStart = $current->format('Y-m-d');
            $monthEnd = $current->copy()->endOfMonth()->format('Y-m-d');

            // Revenue for this month
            $revenueQuery = Transaction::where('status', 'completed')
                ->whereBetween('transaction_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

            if ($outletId) {
                $revenueQuery->where('outlet_id', $outletId);
            }

            $monthRevenue = $revenueQuery->sum('total_amount');
            $monthTransactions = $revenueQuery->count();

            // Calculate refunds for this month
            $monthRefundQuery = Transaction::where('status', 'refunded')
                ->whereBetween('transaction_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

            if ($outletId) {
                $monthRefundQuery->where('outlet_id', $outletId);
            }

            $monthRefunds = $monthRefundQuery->sum('total_amount');
            $monthNetRevenue = $monthRevenue - $monthRefunds;

            // Expenses for this month - Purchase expenses
            $monthPurchaseQuery = Purchase::where('status', 'paid')
                ->whereBetween('purchase_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

            if ($outletId) {
                $monthPurchaseQuery->where('outlet_id', $outletId);
            }

            $monthPurchaseExpenses = $monthPurchaseQuery->sum('total_amount');

            // Operational expenses for this month
            $monthOperationalQuery = Expense::whereBetween('expense_date', [$monthStart, $monthEnd]);
            if ($outletId) {
                $monthOperationalQuery->where('outlet_id', $outletId);
            }
            $monthOperationalExpenses = $monthOperationalQuery->sum('amount');

            // Total expenses (purchase + operational)
            $monthExpenses = $monthPurchaseExpenses + $monthOperationalExpenses;

            // COGS for this month
            $cogsQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->where('transactions.status', 'completed')
                ->whereBetween('transactions.transaction_date', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);

            if ($outletId) {
                $cogsQuery->where('transactions.outlet_id', $outletId);
            }

            $monthCOGS = $cogsQuery->sum(DB::raw('transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)'));

            // Calculate profit (using net_revenue) - consistent with calculateProfitLoss logic (Accrual Basis)
            $monthGrossProfit = $monthNetRevenue - $monthCOGS;
            // Operating Expenses = Operational Expenses + max(0, Purchase Expenses - COGS)
            // Hanya purchase expenses yang belum menjadi COGS yang dihitung sebagai expense
            $monthUnsoldInventoryExpense = max(0, $monthPurchaseExpenses - $monthCOGS);
            $monthOperatingExpenses = $monthOperationalExpenses + $monthUnsoldInventoryExpense;
            // Net Profit = Gross Profit - Operating Expenses
            $monthNetProfit = $monthGrossProfit - $monthOperatingExpenses;
            $monthProfitMargin = $monthNetRevenue > 0 ? ($monthNetProfit / $monthNetRevenue) * 100 : 0;

            $months[] = [
                'month' => $current->format('Y-m'),
                'month_name' => $current->format('F Y'),
                'revenue' => $monthRevenue,
                'refunds' => $monthRefunds,
                'net_revenue' => $monthNetRevenue,
                'expenses' => $monthExpenses,
                'cogs' => $monthCOGS,
                'gross_profit' => $monthGrossProfit,
                'net_profit' => $monthNetProfit,
                'profit_margin' => round($monthProfitMargin, 2),
                'transaction_count' => $monthTransactions,
                'is_profitable' => $monthNetProfit > 0
            ];

            $current->addMonth();
        }

        return $months;
    }

    /**
     * Get Expense Breakdown
     */
    private function getExpenseBreakdown(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        // Purchase expenses by category
        $purchaseExpenses = Purchase::join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('purchases.status', 'paid')
            ->whereBetween('purchases.purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $purchaseExpenses->where('purchases.outlet_id', $outletId);
        }

        $expensesByCategory = $purchaseExpenses->selectRaw('
            categories.id as category_id,
            categories.name as category_name,
            COUNT(DISTINCT purchases.id) as purchase_count,
            SUM(purchase_items.total_price) as total_expense,
            SUM(purchase_items.quantity) as total_quantity,
            AVG(purchase_items.unit_price) as avg_unit_price
        ')
        ->groupBy('categories.id', 'categories.name')
        ->orderByDesc(DB::raw('SUM(purchase_items.total_price)'))
        ->get();

        // Top expense items - buat query baru
        $topExpenseItemsQuery = Purchase::join('purchase_items', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'purchase_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('purchases.status', 'paid')
            ->whereBetween('purchases.purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $topExpenseItemsQuery->where('purchases.outlet_id', $outletId);
        }

        $topExpenseItems = $topExpenseItemsQuery->selectRaw('
            products.id,
            products.name,
            products.sku,
            categories.id as category_id,
            categories.name as category_name,
            SUM(purchase_items.total_price) as total_expense,
            SUM(purchase_items.quantity) as total_quantity,
            AVG(purchase_items.unit_price) as avg_unit_price
        ')
        ->groupBy('products.id', 'products.name', 'products.sku', 'categories.id', 'categories.name')
        ->orderByDesc(DB::raw('SUM(purchase_items.total_price)'))
        ->limit(20)
        ->get();

        return [
            'by_category' => $expensesByCategory,
            'top_items' => $topExpenseItems
        ];
    }

    /**
     * Calculate Cash Flow
     */
    private function calculateCashFlow(string $dateFrom, string $dateTo, ?int $outletId): array
    {
        // Cash Inflow (Revenue)
        $revenueQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $revenueQuery->where('outlet_id', $outletId);
        }

        $cashInflow = $revenueQuery->sum('total_amount');

        // Calculate refunds (cash outflow from refunds)
        $refundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $refundQuery->where('outlet_id', $outletId);
        }

        $refundCashOutflow = $refundQuery->sum('total_amount');
        $netCashInflow = $cashInflow - $refundCashOutflow;

        // Cash Outflow (Expenses - Purchase + Operational)
        $purchaseExpenseQuery = Purchase::where('status', 'paid')
            ->whereBetween('purchase_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($outletId) {
            $purchaseExpenseQuery->where('outlet_id', $outletId);
        }

        $purchaseCashOutflow = $purchaseExpenseQuery->sum('total_amount');

        // Operational expenses cash outflow
        $operationalExpenseQuery = Expense::whereBetween('expense_date', [$dateFrom, $dateTo]);
        if ($outletId) {
            $operationalExpenseQuery->where('outlet_id', $outletId);
        }
        $operationalCashOutflow = $operationalExpenseQuery->sum('amount');

        $cashOutflow = $purchaseCashOutflow + $operationalCashOutflow + $refundCashOutflow;

        // Net Cash Flow
        $netCashFlow = $netCashInflow - ($purchaseCashOutflow + $operationalCashOutflow);

        // Cash Flow Ratio
        $cashFlowRatio = $cashOutflow > 0 ? $cashInflow / $cashOutflow : 0;

        return [
            'inflow' => $cashInflow,
            'refunds' => $refundCashOutflow,
            'net_inflow' => $netCashInflow,
            'outflow' => $cashOutflow,
            'net_cash_flow' => $netCashFlow,
            'cash_flow_ratio' => round($cashFlowRatio, 2),
            'is_positive' => $netCashFlow > 0
        ];
    }

    /**
     * Calculate Financial Ratios
     */
    private function calculateFinancialRatios(array $revenue, array $expenses, array $cogs, array $profitLoss): array
    {
        $totalRevenue = $revenue['total'];
        $netRevenue = $revenue['net_revenue'] ?? $totalRevenue; // Use net_revenue if available
        $totalExpenses = $expenses['total'];
        $totalCOGS = $cogs['total'];
        $netProfit = $profitLoss['net_profit'];

        return [
            'profit_margin' => $profitLoss['net_profit_margin'],
            'gross_margin' => $profitLoss['gross_profit_margin'],
            'expense_ratio' => $netRevenue > 0 ? round(($totalExpenses / $netRevenue) * 100, 2) : 0,
            'cogs_ratio' => $netRevenue > 0 ? round(($totalCOGS / $netRevenue) * 100, 2) : 0,
            'return_on_sales' => $netRevenue > 0 ? round(($netProfit / $netRevenue) * 100, 2) : 0,
            'expense_efficiency' => $totalExpenses > 0 ? round(($netRevenue / $totalExpenses), 2) : 0
        ];
    }

    /**
     * Get Financial Summary for Dashboard
     */
    public function summary(Request $request): JsonResponse
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
            'outlet_id' => 'nullable|exists:outlets,id',
        ]);

        $outletId = $request->outlet_id;

        // Current month
        $currentMonth = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        // Previous month
        $previousMonth = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();

        // Current month data
        $currentData = $this->getMonthlyFinancialData($currentMonth, $currentMonthEnd, $outletId);

        // Previous month data
        $previousData = $this->getMonthlyFinancialData($previousMonth, $previousMonthEnd, $outletId);

        // Calculate growth rates
        $revenueGrowth = $this->calculateGrowthRate($previousData['revenue'], $currentData['revenue']);
        $expenseGrowth = $this->calculateGrowthRate($previousData['expenses'], $currentData['expenses']);
        $profitGrowth = $this->calculateGrowthRate($previousData['net_profit'], $currentData['net_profit']);

        return response()->json([
            'success' => true,
            'data' => [
                'current_month' => $currentData,
                'previous_month' => $previousData,
                'growth_rates' => [
                    'revenue' => $revenueGrowth,
                    'expenses' => $expenseGrowth,
                    'profit' => $profitGrowth
                ],
                'summary' => [
                    'is_profitable' => $currentData['net_profit'] > 0,
                    'profit_margin' => $currentData['profit_margin'],
                    'cash_flow_positive' => $currentData['net_cash_flow'] > 0
                ]
            ]
        ]);
    }

    /**
     * Get monthly financial data
     */
    private function getMonthlyFinancialData(Carbon $start, Carbon $end, ?int $outletId): array
    {
        // Revenue
        $revenueQuery = Transaction::where('status', 'completed')
            ->whereBetween('transaction_date', [$start->format('Y-m-d') . ' 00:00:00', $end->format('Y-m-d') . ' 23:59:59']);

        if ($outletId) {
            $revenueQuery->where('outlet_id', $outletId);
        }

        $revenue = $revenueQuery->sum('total_amount');

        // Calculate refunds
        $refundQuery = Transaction::where('status', 'refunded')
            ->whereBetween('transaction_date', [$start->format('Y-m-d') . ' 00:00:00', $end->format('Y-m-d') . ' 23:59:59']);

        if ($outletId) {
            $refundQuery->where('outlet_id', $outletId);
        }

        $refunds = $refundQuery->sum('total_amount');
        $netRevenue = $revenue - $refunds;

        // Expenses (Purchase + Operational)
        $purchaseExpenseQuery = Purchase::where('status', 'paid')
            ->whereBetween('purchase_date', [$start->format('Y-m-d') . ' 00:00:00', $end->format('Y-m-d') . ' 23:59:59']);

        if ($outletId) {
            $purchaseExpenseQuery->where('outlet_id', $outletId);
        }

        $purchaseExpenses = $purchaseExpenseQuery->sum('total_amount');

        // Operational expenses
        $operationalExpenseQuery = Expense::whereBetween('expense_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);
        if ($outletId) {
            $operationalExpenseQuery->where('outlet_id', $outletId);
        }
        $operationalExpenses = $operationalExpenseQuery->sum('amount');

        $expenses = $purchaseExpenses + $operationalExpenses;

        // COGS
        $cogsQuery = TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.transaction_date', [$start->format('Y-m-d') . ' 00:00:00', $end->format('Y-m-d') . ' 23:59:59']);

        if ($outletId) {
            $cogsQuery->where('transactions.outlet_id', $outletId);
        }

        $cogs = $cogsQuery->sum(DB::raw('transaction_items.quantity * COALESCE(transaction_items.purchase_price, products.purchase_price)'));

        // Calculate profits (using net_revenue)
        $grossProfit = $netRevenue - $cogs;
        $netProfit = $grossProfit - ($expenses - $cogs);
        $profitMargin = $netRevenue > 0 ? ($netProfit / $netRevenue) * 100 : 0;
        $netCashFlow = $netRevenue - $expenses;

        return [
            'revenue' => $revenue,
            'refunds' => $refunds,
            'net_revenue' => $netRevenue,
            'expenses' => $expenses,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'net_profit' => $netProfit,
            'profit_margin' => round($profitMargin, 2),
            'net_cash_flow' => $netCashFlow
        ];
    }

    /**
     * Calculate growth rate
     */
    private function calculateGrowthRate($previous, $current): float
    {
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
