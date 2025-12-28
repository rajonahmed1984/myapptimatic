<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()->customer;

        return view('client.dashboard', [
            'customer' => $customer,
            'subscriptions' => $customer?->subscriptions()->with('plan.product')->get() ?? collect(),
            'invoices' => $customer?->invoices()->latest('issue_date')->limit(5)->get() ?? collect(),
            'licenses' => $customer?->licenses()->with('product')->get() ?? collect(),
        ]);
    }
}
