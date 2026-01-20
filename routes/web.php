<?php

use Illuminate\Support\Facades\Route;


// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest Routes
    Route::middleware('guest')->group(function () {
        Route::get('login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('login.submit');
    });

    // Authenticated Routes
    Route::middleware(['auth:web', 'role:System Admin'])->group(function () {
        Route::post('logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])->name('logout');
        
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        
        // Tenants
        Route::patch('tenants/{tenant}/suspend', [\App\Http\Controllers\Admin\TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::patch('tenants/{tenant}/resume', [\App\Http\Controllers\Admin\TenantController::class, 'resume'])->name('tenants.resume');
        Route::get('tenants/{tenant}/extend', [\App\Http\Controllers\Admin\TenantController::class, 'extend'])->name('tenants.extend');
        Route::post('tenants/{tenant}/extend', [\App\Http\Controllers\Admin\TenantController::class, 'processExtend'])->name('tenants.process_extend');
        Route::get('tenants/{tenant}/impersonate', [\App\Http\Controllers\Admin\TenantController::class, 'impersonate'])->name('tenants.impersonate');
        Route::resource('tenants', \App\Http\Controllers\Admin\TenantController::class);
        
        // Plans
        Route::resource('plans', \App\Http\Controllers\Admin\SubscriptionPlanController::class);

        // Payments
        Route::resource('payments', \App\Http\Controllers\Admin\SubscriptionPaymentController::class)->only(['index', 'show']);

        // System Admins
        Route::resource('system-admins', \App\Http\Controllers\Admin\SystemAdminController::class)->parameters([
            'system-admins' => 'user' // Bind route param 'system_admin' to 'user' model
        ]);

        // Audit Logs (Database)
        Route::resource('audit-logs', \App\Http\Controllers\Admin\AuditLogController::class)->only(['index']);

        // System Tools (Cache, Logs, Maintenance)
        Route::prefix('system-tools')->name('system-tools.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SystemToolController::class, 'index'])->name('index');
            Route::post('cache/clear', [\App\Http\Controllers\Admin\SystemToolController::class, 'clearCache'])->name('cache.clear');
            Route::get('logs', [\App\Http\Controllers\Admin\SystemToolController::class, 'logs'])->name('logs');
            Route::get('maintenance', [\App\Http\Controllers\Admin\SystemToolController::class, 'maintenance'])->name('maintenance');
            Route::post('maintenance', [\App\Http\Controllers\Admin\SystemToolController::class, 'toggleMaintenance'])->name('maintenance.toggle');
        });
    });
});
