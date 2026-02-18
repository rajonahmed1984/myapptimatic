<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()?->customer;

        return view('client.services.index', [
            'customer' => $customer,
            'subscriptions' => $customer
                ? $customer->subscriptions()
                    ->with(['plan.product', 'licenses.domains'])
                    ->latest()
                    ->get()
                : collect(),
        ]);
    }

    public function show(Request $request, Subscription $subscription)
    {
        $customer = $request->user()?->customer;

        if (! $customer || $subscription->customer_id !== $customer->id) {
            abort(404);
        }

        $subscription->load(['plan.product', 'licenses.domains']);

        return view('client.services.show', [
            'subscription' => $subscription,
        ]);
    }
}
