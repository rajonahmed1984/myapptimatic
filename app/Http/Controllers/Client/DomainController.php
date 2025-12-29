<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LicenseDomain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()?->customer;

        $domains = $customer
            ? LicenseDomain::query()
                ->whereHas('license.subscription', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->with(['license.product', 'license.subscription.plan', 'clientRequests'])
                ->orderBy('domain')
                ->get()
            : collect();

        return view('client.domains.index', [
            'customer' => $customer,
            'domains' => $domains,
        ]);
    }

    public function show(Request $request, LicenseDomain $domain)
    {
        $customer = $request->user()?->customer;

        $domain->load(['license.product', 'license.subscription.plan', 'clientRequests']);

        if (! $customer || $domain->license?->subscription?->customer_id !== $customer->id) {
            abort(404);
        }

        return view('client.domains.show', [
            'domain' => $domain,
        ]);
    }
}
