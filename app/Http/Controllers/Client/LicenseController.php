<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()->customer;

        return view('client.licenses.index', [
            'customer' => $customer,
            'licenses' => $customer?->licenses()
                ->with(['product', 'subscription.plan', 'domains'])
                ->latest()
                ->get() ?? collect(),
        ]);
    }
}
