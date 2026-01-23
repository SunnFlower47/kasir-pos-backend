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
Route::prefix('v2')->group(function () {
    // V2 Authentication
    Route::post('/otp/send', [\App\Http\Controllers\Api\V2\OtpController::class, 'send'])
        ->middleware('throttle:5,10'); // 5 OTPs per 10 mins
        
    Route::post('/otp/verify', [\App\Http\Controllers\Api\V2\OtpController::class, 'verify'])
        ->middleware('throttle:5,10');

    Route::post('/register', [\App\Http\Controllers\Api\V2\AuthController::class, 'register'])
        ->middleware(['throttle:5,1', 'recaptcha:v3']);

    Route::post('/login', [\App\Http\Controllers\Api\V2\AuthController::class, 'login'])
        ->middleware(['throttle:5,1', 'recaptcha:v3']);
    
    // Password Reset
    Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\V2\AuthController::class, 'forgotPassword'])
        ->middleware(['throttle:3,10', 'recaptcha:v2']); // 3 requests per 10 mins

    Route::post('/auth/reset-password', [\App\Http\Controllers\Api\V2\AuthController::class, 'resetPassword'])
        ->middleware('throttle:3,10');
    
    // Public Midtrans Callback (Webhook)
    Route::post('/midtrans/callback', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'callback']);

    // Protected Subscription Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/subscription', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'index']);
        Route::get('/subscription/history', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'history']); // New Route
        Route::post('/subscription/trial', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'activateTrial']);
        Route::post('/subscription/pay', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'createPayment']);
        Route::post('/subscription/check-status', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'checkStatus']);

        // Profile & Settings
        Route::get('/profile', [\App\Http\Controllers\Api\V2\AuthController::class, 'profile']);
        Route::put('/profile', [\App\Http\Controllers\Api\V2\AuthController::class, 'updateProfile']);
        Route::put('/profile/password', [\App\Http\Controllers\Api\V2\AuthController::class, 'updatePassword']);
        Route::put('/company', [\App\Http\Controllers\Api\V2\CompanyController::class, 'update']);
    });

    // Public Subscription Routes
    Route::get('/subscription/plans', [\App\Http\Controllers\Api\V2\SubscriptionController::class, 'plans']);

    // --- MIGRATED V2 POS ROUTES ---
    Route::get('purchases/{purchase}/print', [\App\Http\Controllers\Api\V2\PurchaseController::class, 'print'])
        ->name('purchases.print')
        ->middleware('signed');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Api\V2\AuthController::class, 'logout']);

        // Products
        Route::get('products', [\App\Http\Controllers\Api\V2\ProductController::class, 'index']);
        Route::get('products/{product}', [\App\Http\Controllers\Api\V2\ProductController::class, 'show']);
        Route::get('products/barcode/scan', [\App\Http\Controllers\Api\V2\ProductController::class, 'getByBarcode']);
        Route::post('products', [\App\Http\Controllers\Api\V2\ProductController::class, 'store']);
        Route::put('products/{product}', [\App\Http\Controllers\Api\V2\ProductController::class, 'update']);
        Route::delete('products/{product}', [\App\Http\Controllers\Api\V2\ProductController::class, 'destroy']);

        // Categories
        Route::get('categories', [\App\Http\Controllers\Api\V2\CategoryController::class, 'index']);
        Route::get('categories/{category}', [\App\Http\Controllers\Api\V2\CategoryController::class, 'show']);
        Route::post('categories', [\App\Http\Controllers\Api\V2\CategoryController::class, 'store']);
        Route::put('categories/{category}', [\App\Http\Controllers\Api\V2\CategoryController::class, 'update']);
        Route::delete('categories/{category}', [\App\Http\Controllers\Api\V2\CategoryController::class, 'destroy']);

        // Units
        Route::get('units', [\App\Http\Controllers\Api\V2\UnitController::class, 'index']);
        Route::get('units/{unit}', [\App\Http\Controllers\Api\V2\UnitController::class, 'show']);
        Route::post('units', [\App\Http\Controllers\Api\V2\UnitController::class, 'store']);
        Route::put('units/{unit}', [\App\Http\Controllers\Api\V2\UnitController::class, 'update']);
        Route::delete('units/{unit}', [\App\Http\Controllers\Api\V2\UnitController::class, 'destroy']);

        // Stocks
        Route::get('stocks', [\App\Http\Controllers\Api\V2\StockController::class, 'index']);
        Route::get('stocks/movements', [\App\Http\Controllers\Api\V2\StockController::class, 'movements']);
        Route::post('stocks/adjust', [\App\Http\Controllers\Api\V2\StockController::class, 'adjust']);
        Route::post('stocks/opname', [\App\Http\Controllers\Api\V2\StockController::class, 'opname']);
        Route::post('stocks/incoming', [\App\Http\Controllers\Api\V2\StockController::class, 'incoming']);
        Route::post('stocks/transfer', [\App\Http\Controllers\Api\V2\StockController::class, 'transfer']);
        Route::get('stocks/low-stock-alerts', [\App\Http\Controllers\Api\V2\StockController::class, 'lowStockAlerts']);
        
        // Stock Transfers
        Route::apiResource('stock-transfers', \App\Http\Controllers\Api\V2\StockTransferController::class);
        Route::post('stock-transfers/{stockTransfer}/approve', [\App\Http\Controllers\Api\V2\StockTransferController::class, 'approve']);
        Route::post('stock-transfers/{stockTransfer}/cancel', [\App\Http\Controllers\Api\V2\StockTransferController::class, 'cancel']);

        // Transactions
        Route::apiResource('transactions', \App\Http\Controllers\Api\V2\TransactionController::class);
        Route::post('transactions/{transaction}/refund', [\App\Http\Controllers\Api\V2\TransactionController::class, 'refund']);
        Route::post('transactions/{transaction}/settle', [\App\Http\Controllers\Api\V2\TransactionController::class, 'settle']);

        // Receipts
        Route::get('transactions/{transaction}/receipt/pdf', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'generatePdf']);
        Route::get('transactions/{transaction}/receipt/simple', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'generateSimplePdf']);
        Route::get('transactions/{transaction}/receipt/58mm', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'generate58mmPdf']);
        Route::get('transactions/{transaction}/receipt/html', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'generateHtml']);

        // Shift Closings
        Route::get('shift-closings/last', [\App\Http\Controllers\Api\V2\ShiftClosingController::class, 'getLastClosing']);
        Route::post('shift-closings', [\App\Http\Controllers\Api\V2\ShiftClosingController::class, 'store']);
        Route::get('shift-closings', [\App\Http\Controllers\Api\V2\ShiftClosingController::class, 'index']);

        // Customers
        Route::apiResource('customers', \App\Http\Controllers\Api\V2\CustomerController::class);
        Route::post('customers/{customer}/loyalty/add', [\App\Http\Controllers\Api\V2\CustomerController::class, 'addLoyaltyPoints']);
        Route::post('customers/{customer}/loyalty/redeem', [\App\Http\Controllers\Api\V2\CustomerController::class, 'redeemLoyaltyPoints']);

        // Suppliers
        Route::apiResource('suppliers', \App\Http\Controllers\Api\V2\SupplierController::class);

        // Purchases
        Route::get('purchases/{purchase}/print-url', [\App\Http\Controllers\Api\V2\PurchaseController::class, 'getPrintUrl']);
        Route::apiResource('purchases', \App\Http\Controllers\Api\V2\PurchaseController::class);
        Route::patch('purchases/{purchase}/status', [\App\Http\Controllers\Api\V2\PurchaseController::class, 'updateStatus']);

        // Outlets
        Route::apiResource('outlets', \App\Http\Controllers\Api\V2\OutletController::class);
        Route::post('outlets/{outlet}/logo', [\App\Http\Controllers\Api\V2\OutletController::class, 'uploadLogo']);
        Route::get('outlets/{outlet}/dashboard', [\App\Http\Controllers\Api\V2\OutletController::class, 'dashboard']);

        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Api\V2\DashboardController::class, 'index']);
        Route::get('dashboard/outlet-comparison', [\App\Http\Controllers\Api\V2\DashboardController::class, 'outletComparison']);

        // Reports
        Route::get('reports/expenses', [\App\Http\Controllers\Api\V2\ReportController::class, 'expenses']);
        Route::get('reports/sales', [\App\Http\Controllers\Api\V2\ReportController::class, 'sales']);
        Route::get('reports/profit', [\App\Http\Controllers\Api\V2\ReportController::class, 'profit']);
        Route::get('reports/top-products', [\App\Http\Controllers\Api\V2\ReportController::class, 'topProducts']);
        Route::get('reports/business-intelligence', [\App\Http\Controllers\Api\V2\AdvancedReportController::class, 'businessIntelligence']);
        Route::get('reports/enhanced', [\App\Http\Controllers\Api\V2\EnhancedReportController::class, 'index']);
        Route::get('reports/financial/comprehensive', [\App\Http\Controllers\Api\V2\FinancialReportController::class, 'comprehensive']);
        Route::get('reports/financial/summary', [\App\Http\Controllers\Api\V2\FinancialReportController::class, 'summary']);
        Route::get('reports/purchases', [\App\Http\Controllers\Api\V2\ReportController::class, 'purchases']);
        Route::get('reports/stocks', [\App\Http\Controllers\Api\V2\ReportController::class, 'stocks']);

        // Expenses
        Route::apiResource('expenses', \App\Http\Controllers\Api\V2\ExpenseController::class);
        Route::get('expenses/categories/list', [\App\Http\Controllers\Api\V2\ExpenseController::class, 'categories']);

        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\V2\SettingController::class, 'index']);
        Route::put('settings', [\App\Http\Controllers\Api\V2\SettingController::class, 'update']);
        Route::get('settings/{group}', [\App\Http\Controllers\Api\V2\SettingController::class, 'getGroup']);
        Route::put('settings/{group}', [\App\Http\Controllers\Api\V2\SettingController::class, 'updateGroup']);
        Route::post('settings/logo/upload', [\App\Http\Controllers\Api\V2\SettingController::class, 'uploadLogo']);
        Route::delete('settings/logo/{type}', [\App\Http\Controllers\Api\V2\SettingController::class, 'deleteLogo']);
        Route::get('settings/receipt', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'getSettings']);
        Route::put('settings/receipt', [\App\Http\Controllers\Api\V2\ReceiptController::class, 'updateSettings']);

        // System & Backup (V2)
        Route::get('system/info', [\App\Http\Controllers\Api\V2\SettingController::class, 'systemInfo']);
        Route::get('system/backup/history', [\App\Http\Controllers\Api\V2\SettingController::class, 'backups']);
        Route::post('system/backup/create', [\App\Http\Controllers\Api\V2\SettingController::class, 'backup']);
        Route::get('system/backup/download/{filename}', [\App\Http\Controllers\Api\V2\SettingController::class, 'downloadBackup']);
        Route::delete('system/backup/{filename}', [\App\Http\Controllers\Api\V2\SettingController::class, 'deleteBackup']);
        
        // Backup Settings (mapped to SystemController as they are not in V2 SettingController yet)
        Route::get('system/backup/settings', [\App\Http\Controllers\SystemController::class, 'getBackupSettings']);
        Route::post('system/backup/settings', [\App\Http\Controllers\SystemController::class, 'updateBackupSettings']);

        // Export/Import
        Route::get('export/{type}/excel', [\App\Http\Controllers\Api\V2\ExportImportController::class, 'exportExcel']);
        Route::get('export/{type}/pdf', [\App\Http\Controllers\Api\V2\ExportImportController::class, 'exportPdf']);
        Route::get('export/template/{type}', [\App\Http\Controllers\Api\V2\ExportImportController::class, 'downloadTemplate']);
        Route::post('import/{type}', [\App\Http\Controllers\Api\V2\ExportImportController::class, 'import']);
        Route::post('import/{type}/preview', [\App\Http\Controllers\Api\V2\ExportImportController::class, 'previewImport']);

        // Users & Roles
        Route::apiResource('users', \App\Http\Controllers\Api\V2\UserController::class);
        Route::get('users/{user}/permissions', [\App\Http\Controllers\Api\V2\UserController::class, 'getPermissions']);
        Route::get('roles', [\App\Http\Controllers\Api\V2\UserController::class, 'getRoles']);
        Route::get('permissions', [\App\Http\Controllers\Api\V2\UserController::class, 'getAllPermissions']);
        Route::put('roles/{role}/permissions', [\App\Http\Controllers\Api\V2\UserController::class, 'updateRolePermissions']);

        // Audit Logs
        Route::get('audit-logs', [\App\Http\Controllers\Api\V2\AuditLogController::class, 'index']);
        Route::get('audit-logs/statistics', [\App\Http\Controllers\Api\V2\AuditLogController::class, 'statistics']);
        Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\V2\AuditLogController::class, 'show']);
        Route::delete('audit-logs/cleanup', [\App\Http\Controllers\Api\V2\AuditLogController::class, 'cleanup']);
    });
});

Route::prefix('v1')->group(function () {
    // Authentication routes with rate limiting
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute to prevent brute force

    // Public print route (validates token manually via query param)
    Route::get('purchases/{purchase}/print', [\App\Http\Controllers\Api\PurchaseController::class, 'print']);

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

    // Apply CheckSubscription middleware to all protected V1 routes (optional, but recommended for security)
    // For now, we'll keeping V1 as legacy/internal, but in production we should likely apply it here too.
    // To strictly follow "V2 separates new logic", users using V1 endpoints won't get subscription checks unless we add it.
    // Given the requirement "accounts without sub cannot login", V2 Controller handles that on login.
    // But middleware ensures even with a valid token they are blocked.
    
    // Receipt routes - Require authentication for security
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('transactions/{transaction}/receipt/pdf', [\App\Http\Controllers\Api\ReceiptController::class, 'generatePdf']);
        Route::get('transactions/{transaction}/receipt/simple', [\App\Http\Controllers\Api\ReceiptController::class, 'generateSimplePdf']);
        Route::get('transactions/{transaction}/receipt/58mm', [\App\Http\Controllers\Api\ReceiptController::class, 'generate58mmPdf']);
        Route::get('transactions/{transaction}/receipt/html', [\App\Http\Controllers\Api\ReceiptController::class, 'generateHtml']);
    });

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
    Route::middleware(['auth:sanctum', 'throttle:150,1'])->group(function () {
        // 200 requests per minute per user/IP for general API usage (increased for fast menu navigation)
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);

        // User management (Super Admin only)
        Route::middleware('role:Super Admin')->group(function () {
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
        Route::post('transactions/{transaction}/settle', [\App\Http\Controllers\Api\TransactionController::class, 'settle']);

        // Shift closing management - All authenticated users can access
        Route::get('shift-closings/last', [\App\Http\Controllers\Api\ShiftClosingController::class, 'getLastClosing']);
        Route::post('shift-closings', [\App\Http\Controllers\Api\ShiftClosingController::class, 'store']);
        Route::get('shift-closings', [\App\Http\Controllers\Api\ShiftClosingController::class, 'index']);

        // Receipt routes are now in authenticated group above (line 40-45)

        // Test route for debugging PDF - ONLY IN DEVELOPMENT
        if (app()->environment('local', 'development')) {
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
                    // Don't expose stack trace in production
                    return response()->json([
                        'error' => app()->environment('production')
                            ? 'An error occurred'
                            : $e->getMessage()
                    ], 500);
                }
            });
        }

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
