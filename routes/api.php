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
    // Authentication routes
    Route::post('/login', [AuthController::class, 'login']);

    // Test route for outlets (no auth)
    Route::get('test/outlets', function() {
        return response()->json([
            'success' => true,
            'data' => \App\Models\Outlet::all()
        ]);
    });

    // Public routes for direct printer access (fallback)
    Route::get('public/transactions/{transaction}/receipt/pdf', [\App\Http\Controllers\Api\ReceiptController::class, 'generatePdf']);
    Route::get('public/transactions/{transaction}/receipt/simple', [\App\Http\Controllers\Api\ReceiptController::class, 'generateSimplePdf']);
    Route::get('public/transactions/{transaction}/receipt/58mm', [\App\Http\Controllers\Api\ReceiptController::class, 'generate58mmPdf']);
    Route::get('public/transactions/{transaction}/receipt/html', [\App\Http\Controllers\Api\ReceiptController::class, 'generateHtml']);



    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
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
        Route::get('products/barcode/scan', [\App\Http\Controllers\Api\ProductController::class, 'getByBarcode']);

        // Category management - All roles can read categories
        Route::get('categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
        Route::get('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);

        // Unit management - All roles can read units
        Route::get('units', [\App\Http\Controllers\Api\UnitController::class, 'index']);
        Route::get('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'show']);

        // Product management - Write access (Admin, Manager, Warehouse only)
        Route::middleware('role:Super Admin,Admin,Manager,Warehouse')->group(function () {
            Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'store']);
            Route::put('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update']);
            Route::delete('products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy']);

            // Category management - Write access
            Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
            Route::put('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);
            Route::delete('categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'destroy']);

            // Unit management - Write access
            Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'store']);
            Route::put('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'update']);
            Route::delete('units/{unit}', [\App\Http\Controllers\Api\UnitController::class, 'destroy']);
        });

        // Stock management - All roles can read stocks for POS
        Route::get('stocks', [\App\Http\Controllers\Api\StockController::class, 'index']);
        Route::get('stocks/movements', [\App\Http\Controllers\Api\StockController::class, 'movements']);

        // Stock management - Write access (Admin, Manager, Warehouse only)
        Route::middleware('role:Super Admin,Admin,Manager,Warehouse')->group(function () {
            Route::post('stocks/adjust', [\App\Http\Controllers\Api\StockController::class, 'adjust']);
            Route::post('stocks/opname', [\App\Http\Controllers\Api\StockController::class, 'opname']);
            Route::post('stocks/incoming', [\App\Http\Controllers\Api\StockController::class, 'incoming']);
            Route::post('stocks/transfer', [\App\Http\Controllers\Api\StockController::class, 'transfer']);
            Route::get('stocks/low-stock-alerts', [\App\Http\Controllers\Api\StockController::class, 'lowStockAlerts']);

            // Stock transfers
            Route::apiResource('stock-transfers', \App\Http\Controllers\Api\StockTransferController::class);
            Route::post('stock-transfers/{stockTransfer}/approve', [\App\Http\Controllers\Api\StockTransferController::class, 'approve']);
            Route::post('stock-transfers/{stockTransfer}/cancel', [\App\Http\Controllers\Api\StockTransferController::class, 'cancel']);
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

        // Customer management - Write access and loyalty (Manager+ only)
        Route::middleware('role:Super Admin,Admin,Manager')->group(function () {
            Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
            Route::put('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
            Route::post('customers/{customer}/loyalty/add', [\App\Http\Controllers\Api\CustomerController::class, 'addLoyaltyPoints']);
            Route::post('customers/{customer}/loyalty/redeem', [\App\Http\Controllers\Api\CustomerController::class, 'redeemLoyaltyPoints']);
        });

        // Settings management
        Route::middleware('role:Super Admin,Admin')->group(function () {
            Route::get('settings', [\App\Http\Controllers\Api\SettingController::class, 'index']);
            Route::put('settings', [\App\Http\Controllers\Api\SettingController::class, 'update']);
            Route::get('settings/{group}', [\App\Http\Controllers\Api\SettingController::class, 'getGroup']);
            Route::put('settings/{group}', [\App\Http\Controllers\Api\SettingController::class, 'updateGroup']);

            // Receipt settings (legacy routes)
            Route::get('settings/receipt', [\App\Http\Controllers\Api\ReceiptController::class, 'getSettings']);
            Route::put('settings/receipt', [\App\Http\Controllers\Api\ReceiptController::class, 'updateSettings']);

            // System management
            Route::post('system/backup', [\App\Http\Controllers\Api\SettingController::class, 'backup']);
            Route::get('system/backups', [\App\Http\Controllers\Api\SettingController::class, 'backups']);
            Route::get('system/backups/{filename}/download', [\App\Http\Controllers\Api\SettingController::class, 'downloadBackup']);
            Route::delete('system/backups/{filename}', [\App\Http\Controllers\Api\SettingController::class, 'deleteBackup']);
            Route::get('system/info', [\App\Http\Controllers\Api\SettingController::class, 'systemInfo']);

            // Audit logs
            Route::get('audit-logs', [\App\Http\Controllers\Api\AuditLogController::class, 'index']);
            Route::get('audit-logs/{auditLog}', [\App\Http\Controllers\Api\AuditLogController::class, 'show']);
            Route::get('audit-logs/statistics', [\App\Http\Controllers\Api\AuditLogController::class, 'statistics']);
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

        // Outlet management - Write access (Super Admin, Admin only)
        Route::middleware('role:Super Admin,Admin')->group(function () {
            Route::post('outlets', [\App\Http\Controllers\Api\OutletController::class, 'store']);
            Route::put('outlets/{outlet}', [\App\Http\Controllers\Api\OutletController::class, 'update']);
            Route::delete('outlets/{outlet}', [\App\Http\Controllers\Api\OutletController::class, 'destroy']);
            Route::get('outlets/{outlet}/dashboard', [\App\Http\Controllers\Api\OutletController::class, 'dashboard']);
        });

        // Supplier & Purchase management
        Route::middleware('role:Super Admin,Admin,Manager,Warehouse')->group(function () {
            Route::apiResource('suppliers', \App\Http\Controllers\Api\SupplierController::class);
            Route::apiResource('purchases', \App\Http\Controllers\Api\PurchaseController::class);
            Route::patch('purchases/{purchase}/status', [\App\Http\Controllers\Api\PurchaseController::class, 'updateStatus']);
        });

        // Reports
        Route::middleware('role:Super Admin,Admin,Manager')->group(function () {
            Route::get('reports/sales', [\App\Http\Controllers\Api\ReportController::class, 'sales']);
            Route::get('reports/purchases', [\App\Http\Controllers\Api\ReportController::class, 'purchases']);
            Route::get('reports/expenses', [\App\Http\Controllers\Api\ReportController::class, 'expenses']);
            Route::get('reports/stocks', [\App\Http\Controllers\Api\ReportController::class, 'stocks']);
            Route::get('reports/profit', [\App\Http\Controllers\Api\ReportController::class, 'profit']);
            Route::get('reports/top-products', [\App\Http\Controllers\Api\ReportController::class, 'topProducts']);
        });
    });
});
