<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\LicenseDomain;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DomainController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customer = $request->user()?->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $domains = $customer
            ? LicenseDomain::query()
                ->whereHas('license.subscription', function ($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                })
                ->with(['license.product', 'license.subscription.plan'])
                ->orderBy('domain')
                ->get()
            : collect();

        return Inertia::render('Client/Domains/Index', [
            'has_customer' => (bool) $customer,
            'domains' => $domains->map(function (LicenseDomain $domain) use ($dateFormat) {
                $plan = $domain->license?->subscription?->plan;
                $product = $domain->license?->product;
                $key = (string) ($domain->license?->license_key ?? '');
                $maskedKey = $key !== '' && strlen($key) > 8
                    ? substr($key, 0, 4).str_repeat('*', max(0, strlen($key) - 8)).substr($key, -4)
                    : $key;

                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'masked_key' => $maskedKey ?: '--',
                    'product_name' => $product?->name ?? '--',
                    'plan_name' => $plan?->name ?? '--',
                    'status_label' => ucfirst((string) $domain->status),
                    'verified_display' => $domain->verified_at?->format($dateFormat) ?? '--',
                    'last_seen_display' => $domain->last_seen_at?->format($dateFormat) ?? '--',
                    'routes' => [
                        'show' => route('client.domains.show', $domain),
                    ],
                ];
            })->values()->all(),
            'routes' => [
                'dashboard' => route('client.dashboard'),
            ],
        ]);
    }

    public function show(Request $request, LicenseDomain $domain): InertiaResponse
    {
        $customer = $request->user()?->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $domain->load(['license.product', 'license.subscription.plan']);

        if (! $customer || $domain->license?->subscription?->customer_id !== $customer->id) {
            abort(404);
        }

        $plan = $domain->license?->subscription?->plan;
        $product = $domain->license?->product;
        $key = (string) ($domain->license?->license_key ?? '');
        $maskedKey = $key !== '' && strlen($key) > 8
            ? substr($key, 0, 4).str_repeat('*', max(0, strlen($key) - 8)).substr($key, -4)
            : $key;

        return Inertia::render('Client/Domains/Show', [
            'domain' => [
                'name' => $domain->domain,
                'product_name' => $product?->name ?? '--',
                'plan_name' => $plan?->name ?? '--',
                'status_label' => ucfirst((string) $domain->status),
                'verified_display' => $domain->verified_at?->format($dateFormat) ?? '--',
                'last_seen_display' => $domain->last_seen_at?->format($dateFormat) ?? '--',
                'masked_key' => $maskedKey ?: '--',
            ],
            'routes' => [
                'index' => route('client.domains.index'),
            ],
        ]);
    }
}
