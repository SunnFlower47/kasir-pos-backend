<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $suspendedTenants = Tenant::where('is_active', false)->count();
        $revenueThisMonth = \App\Models\SubscriptionPayment::where('status', 'paid')
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');
        
        $newTenantsThisMonth = Tenant::whereMonth('created_at', now()->month)->count();
        
        return view('admin.dashboard.index', compact('totalTenants', 'activeTenants', 'suspendedTenants', 'revenueThisMonth', 'newTenantsThisMonth'));
    }
}
