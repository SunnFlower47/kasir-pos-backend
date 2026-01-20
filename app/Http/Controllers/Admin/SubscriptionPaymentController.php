<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = SubscriptionPayment::with(['subscription.tenant', 'subscription'])
            ->latest('payment_date');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(15);

        return view('admin.payments.index', compact('payments'));
    }

    public function show(SubscriptionPayment $payment)
    {
        $payment->load(['subscription.tenant', 'subscription']);
        return view('admin.payments.show', compact('payment'));
    }
}
