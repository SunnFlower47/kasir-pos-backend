<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::prefix('v1')->group(function () {
    // Authentication routes with rate limiting
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute to prevent brute force

    // Test route for outlets (PROTECTED - remove in production or restrict to admin)
    if (app()->environment('local', 'development')) {
        Route::middleware(['auth:sanctum', 'role:Super Admin'])->group(function () {
            Route::get('test/outlets', function() {
                return response()->json([
                    'success' => true,
                    'data' => \App\Models\Outlet::all()
                ]);
            });
        });
    }

    // Public routes for direct printer access (fallback)
    Route::get('public/transactions/{transaction}/receipt/pdf', [\App\Http\Controllers\Api\ReceiptController::class, 'generatePdf']);
    Route::get('public/transactions/{transaction}/receipt/simple', [\App\Http\Controllers\Api\ReceiptController::class, 'generateSimplePdf']);
    Route::get('public/transactions/{transaction}/receipt/58mm', [\App\Http\Controllers\Api\ReceiptController::class, 'generate58mmPdf']);
    Route::get('public/transactions/{transaction}/receipt/html', [\App\Http\Controllers\Api\ReceiptController::class, 'generateHtml']);

    /**
     * High frequency POS endpoints
     * - Barcode scanning can be triggered very often by scanner hardware
     * - We give it a higher rate limit than the general API throttle
     */
    Route::middleware(['auth:sanctum', 'throttle:300,1'])->group(function () {
        // Barcode scan for POS
        Route::get('products/barcode/scan', [\App\Http\Controllers\Api\ProductController::class, 'getByBarcode']);
    });

    // Protected routes with general rate limiting
    Route::middleware(['auth:sanctum', 'throttle:250,1'])->group(function () {
        // 200 requests per minute per user/IP for general API usage (increased for fast menu navigation)
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        // User management (Super Admin only)
        Route::middleware('role:Super Admin')->group(function () {
            Route::post('/register', [AuthController::class, 'register']);
            Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
            Route::get('users/{user}/permissions', [\App\Http\Controllers\Api\UserController::class, 'getPermissions']);
            Route::get('roles', [\App\Http\Controllers\Api\UserController::class, 'getRoles']);
            Route::get('permissions', [\App\Http\Controllers\Api\UserController::class, 'getAllPermissions']);
            Route::put('roles/{role}/permissions', [\App\Http\Controllers\Api\UserController::class, 'updateRolePermissions']);
        });

        // Product management - All roles can read products for POS
        Route::get('products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
        Route::get('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show']);

        // Category management - All roles can read categories
        Route::get('categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
        Route::get('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);

        // Unit management - All roles can read units
        Route::get('units', [\App\Http\Controllers\Api\UnitController::class, 'index']);
        Route::get('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'show']);

        // Product management - Write access
        Route::middleware('permission:products.create')->group(function () {
            Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'store']);
        });
        Route::middleware('permission:products.edit')->group(function () {
            Route::put('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update']);
        });
        Route::middleware('permission:products.delete')->group(function () {
            Route::delete('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy']);
        });

        // Category management - Write access
        Route::middleware('permission:categories.create')->group(function () {
            Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
        });
        Route::middleware('permission:categories.edit')->group(function () {
            Route::put('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);
        });
        Route::middleware('permission:categories.delete')->group(function () {
            Route::delete('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'destroy']);
        });

        // Unit management - Write access
        Route::middleware('permission:units.create')->group(function () {
            Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'store']);
        });
        Route::middleware('permission:units.edit')->group(function () {
            Route::put('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'update']);
        });
        Route::middleware('permission:units.delete')->group(function () {
            Route::delete('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'destroy']);
        });

        // Stock management - All roles can read stocks for POS
        Route::get('stocks', [\App\Http\Controllers\Api\StockController::class, 'index']);
        Route::get('stocks/movements', [\App\Http\Controllers\Api\StockController::class, 'movements']);

        // Stock management - Write access
        Route::middleware('permission:stocks.adjustment')->group(function () {
            Route::post('stocks/adjust', [\App\Http\Controllers\Api\StockController::class, 'adjust']);
            Route::post('stocks/opname', [\App\Http\Controllers\Api\StockController::class, 'opname']);
            Route::post('stocks/incoming', [\App\Http\Controllers\Api\StockController::class, 'incoming']);
        });
        Route::middleware('permission:stocks.transfer')->group(function () {
            Route::post('stocks/transfer', [\App\Http\Controllers\Api\StockController::class, 'transfer']);
            Route::apiResource('stock-transfers', \App\Http\Controllers\Api\StockTransferController::class);
            Route::post('stock-transfers/{stockTransfer}/approve', [\App\Http\Controllers\Api\StockTransferController::class, 'approve']);
            Route::post('stock-transfers/{stockTransfer}/cancel', [\App\Http\Controllers\Api\StockTransferController::class, 'cancel']);
        });
        Route::middleware('permission:stocks.view')->group(function () {
            Route::get('stocks/low-stock-alerts', [\App\Http\Controllers\Api\StockController::class, 'lowStockAlerts']);
        });

        // Transaction management (POS) - All roles can create and view transactions
        Route::apiResource('transactions', \App\Http\Controllers\Api\TransactionController::class);
        Route::post('transactions/{transaction}/refund', [\App\Http\Controllers\Api\TransactionController::class, 'refund']);

        // Receipt management - All roles can generate receipts
        Route::get('transactions/{transaction}/receipt/pdf', [\App\Http\Controllers\Api\ReceiptController::class, 'generatePdf']);
        Route::get('transactions/{transaction}/receipt/html', [\App\Http\Controllers\Api\ReceiptController::class, 'generateHtml']);

        // Test route for debugging PDF
        Route::get('test/pdf/{id}', function($id) {
            try {
                $transaction = \App\Models\Transaction::with(['customer', 'outlet', 'user', 'transactionItems.product'])->find($id);
                if (!$transaction) {
                    return response()->json(['error' => 'Transaction not found'], 404);
                }

                return response()->json([
                    'success' => true,
                    'transaction' => $transaction,
                    'items_count' => $transaction->transactionItems->count(),
                    'user' => $transaction->user ? $transaction->user->name : 'No user',
                    'date' => $transaction->transaction_date
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ], 500);
            }
        });

        // Customer management - All roles can read customers for POS
        Route::get('customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
        Route::get('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);

        // Customer management - Write access
        Route::middleware('permission:customers.create')->group(function () {
            Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
        });
        Route::middleware('permission:customers.edit')->group(function () {
            Route::put('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
            Route::post('customers/{customer}/loyalty/add', [\App\Http\Controllers\Api\CustomerController::class, 'addLoyaltyPoints']);
            Route::post('customers/{customer}/loyalty/redeem', [\App\Http\Controllers\Api\CustomerController::class, 'redeemLoyaltyPoints']);
        });
        Route::middleware('permission:customers.delete')->group(function () {
            Route::delete('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
        });

        // Settings management
        Route::middleware('role:Super Admin,Admin')->group(function () {
            Route::get('settings', [\App\Http\Controllers\Api\SettingController::class, 'index']);
            Route::put('settings', [\App\Http\Controllers\Api\SettingController::class, 'update']);
            Route::get('settings/{group}', [\App\Http\Controllers\Api\SettingController::class, 'getGroup']);
            Route::put('settings/{group}', [\App\Http\Controllers\Api\SettingController::class, 'updateGroup']);
            Route::post('settings/logo/upload', [\App\Http\Controllers\Api\SettingController::class, 'uploadLogo']);
            Route::delete('settings/logo/{type}', [\App\Http\Controllers\Api\SettingController::class, 'deleteLogo']);

            // Receipt settings (legacy routes)
            Route::get('settings/receipt', [\App\Http\Controllers\Api\ReceiptController::class, 'getSettings']);
            Route::put('settings/receipt', [\App\Http\Controllers\Api\ReceiptController::class, 'updateSettings']);

            // System management
            Route::get('system/info', [\App\Http\Controllers\SystemController::class, 'getSystemInfo']);
            Route::get('system/backup/history', [\App\Http\Controllers\SystemController::class, 'getBackupHistory']);
            Route::post('system/backup/create', [\App\Http\Controllers\SystemController::class, 'createBackup']);
            Route::get('system/backup/download/{backupId}', [\App\Http\Controllers\SystemController::class, 'downloadBackup']);
            Route::get('system/backup/settings', [\App\Http\Controllers\SystemController::class, 'getBackupSettings']);
            Route::post('system/backup/settings', [\App\Http\Controllers\SystemController::class, 'updateBackupSettings']);

            // Legacy system routes (keep for compatibility)
            Route::post('system/backup', [\App\Http\Controllers\Api\SettingController::class, 'backup']);
            Route::get('system/backups', [\App\Http\Controllers\Api\SettingController::class, 'backups']);
            Route::get('system/backups/{filename}/download', [\App\Http\Controllers\Api\SettingController::class, 'downloadBackup']);
            Route::delete('system/backups/{filename}', [\App\Http\Controllers\Api\SettingController::class, 'deleteBackup']);
        });

        // Audit logs - Using permission middleware
        Route::middleware('permission:audit-logs.view')->group(function () {
            Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
            // IMPORTANT: Specific routes must come before parameterized routes
            Route::get('audit-logs/statistics', [\App\Http\Controllers\Api\AuditLogController::class, 'statistics']);
            Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\AuditLogController::class, 'show']);
        });
        Route::middleware('permission:audit-logs.delete')->group(function () {
            Route::delete('audit-logs/cleanup', [\App\Http\Controllers\Api\AuditLogController::class, 'cleanup']);
        });

        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);
        Route::middleware('role:Super Admin,Admin,Manager')->group(function () {
            Route::get('dashboard/outlet-comparison', [\App\Http\Controllers\Api\DashboardController::class, 'outletComparison']);
        });

        // Outlet management - All roles can read outlets for POS
        Route::get('outlets', [\App\Http\Controllers\Api\OutletController::class, 'index']);
        Route::get('outlets/{outlet}', [\App\Http\Controllers\Api\OutletController::class, 'show']);

        // Outlet management - Write access
        Route::middleware('permission:outlets.create')->group(function () {
            Route::post('outlets', [\App\Http\Controllers\Api\OutletController::class, 'store']);
        });
        Route::middleware('permission:outlets.edit')->group(function () {
            Route::put('outlets/{outlet}', [\App\Http\Controllers\Api\OutletController::class, 'update']);
            Route::post('outlets/{outlet}/logo', [\App\Http\Controllers\Api\OutletController::class, 'uploadLogo']);
            Route::get('outlets/{outlet}/dashboard', [\App\Http\Controllers\Api\OutletController::class, 'dashboard']);
        });
        Route::middleware('permission:outlets.delete')->group(function () {
            Route::delete('outlets/{outlet}', [\App\Http\Controllers\Api\OutletController::class, 'destroy']);
        });

        // Supplier & Purchase management
        Route::middleware('permission:suppliers.view')->group(function () {
            Route::get('suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'index']);
            Route::get('suppliers/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'show']);
        });
        Route::middleware('permission:suppliers.create')->group(function () {
            Route::post('suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'store']);
        });
        Route::middleware('permission:suppliers.edit')->group(function () {
            Route::put('suppliers/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'update']);
        });
        Route::middleware('permission:suppliers.delete')->group(function () {
            Route::delete('suppliers/{supplier}', [\App\Http\Controllers\Api\SupplierController::class, 'destroy']);
        });

        Route::middleware('permission:purchases.view')->group(function () {
            Route::get('purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'index']);
            Route::get('purchases/{purchase}', [\App\Http\Controllers\Api\PurchaseController::class, 'show']);
        });
        Route::middleware('permission:purchases.create')->group(function () {
            Route::post('purchases', [\App\Http\Controllers\Api\PurchaseController::class, 'store']);
        });
        Route::middleware('permission:purchases.edit')->group(function () {
            Route::put('purchases/{purchase}', [\App\Http\Controllers\Api\PurchaseController::class, 'update']);
            Route::patch('purchases/{purchase}/status', [\App\Http\Controllers\Api\PurchaseController::class, 'updateStatus']);
        });
        Route::middleware('permission:purchases.delete')->group(function () {
            Route::delete('purchases/{purchase}', [\App\Http\Controllers\Api\PurchaseController::class, 'destroy']);
        });


        Route::middleware('permission:reports.purchases')->group(function () {
            Route::get('reports/expenses', [\App\Http\Controllers\Api\ReportController::class, 'expenses']);
        });
        Route::middleware('permission:reports.sales')->group(function () {
            Route::get('reports/sales', [\App\Http\Controllers\Api\ReportController::class, 'sales']);
            Route::get('reports/profit', [\App\Http\Controllers\Api\ReportController::class, 'profit']);
            Route::get('reports/top-products', [\App\Http\Controllers\Api\ReportController::class, 'topProducts']);
            Route::get('reports/business-intelligence', [\App\Http\Controllers\Api\AdvancedReportController::class, 'businessIntelligence']);
            Route::get('reports/financial/comprehensive', [\App\Http\Controllers\Api\FinancialReportController::class, 'comprehensive']);
            Route::get('reports/financial/summary', [\App\Http\Controllers\Api\FinancialReportController::class, 'summary']);
            Route::get('reports/enhanced', [\App\Http\Controllers\Api\EnhancedReportController::class, 'index']);
        });
        Route::middleware('permission:reports.purchases')->group(function () {
            Route::get('reports/purchases', [\App\Http\Controllers\Api\ReportController::class, 'purchases']);
        });
        Route::middleware('permission:reports.stocks')->group(function () {
            Route::get('reports/stocks', [\App\Http\Controllers\Api\ReportController::class, 'stocks']);
        });

        // Expense management - Must come AFTER reports/expenses route
        Route::middleware('permission:expenses.view')->group(function () {
            Route::get('expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'index']);
            Route::get('expenses/categories/list', [\App\Http\Controllers\Api\ExpenseController::class, 'categories']);
            Route::get('expenses/{expense}', [\App\Http\Controllers\Api\ExpenseController::class, 'show'])->where('expense', '[0-9]+');
        });
        Route::middleware('permission:expenses.create')->group(function () {
            Route::post('expenses', [\App\Http\Controllers\Api\ExpenseController::class, 'store']);
        });
        Route::middleware('permission:expenses.edit')->group(function () {
            Route::put('expenses/{expense}', [\App\Http\Controllers\Api\ExpenseController::class, 'update'])->where('expense', '[0-9]+');
        });
        Route::middleware('permission:expenses.delete')->group(function () {
            Route::delete('expenses/{expense}', [\App\Http\Controllers\Api\ExpenseController::class, 'destroy'])->where('expense', '[0-9]+');
        });

        // Export/Import routes
        Route::middleware('permission:export.view')->group(function () {
            Route::get('export/{type}/excel', [\App\Http\Controllers\Api\ExportImportController::class, 'exportExcel']);
            Route::get('export/{type}/pdf', [\App\Http\Controllers\Api\ExportImportController::class, 'exportPdf']);
            Route::get('export/template/{type}', [\App\Http\Controllers\Api\ExportImportController::class, 'downloadTemplate']);
        });

        Route::middleware('permission:import.create')->group(function () {
            Route::post('import/{type}', [\App\Http\Controllers\Api\ExportImportController::class, 'import']);
            Route::post('import/{type}/preview', [\App\Http\Controllers\Api\ExportImportController::class, 'previewImport']);
        });
    });
});
