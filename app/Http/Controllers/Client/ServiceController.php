<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ServiceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customer = $request->user()?->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $subscriptions = $customer
            ? $customer->subscriptions()
                ->with(['plan.product', 'licenses.domains'])
                ->latest()
                ->get()
            : collect();

        return Inertia::render('Client/Services/Index', [
            'has_customer' => (bool) $customer,
            'subscriptions' => $subscriptions->map(function (Subscription $subscription, int $index) use ($dateFormat) {
                $plan = $subscription->plan;
                $product = $plan?->product;

                return [
                    'id' => $subscription->id,
                    'serial' => $index + 1,
                    'service_name' => $product?->name ?: 'Service',
                    'plan_name' => $plan?->name ?? '--',
                    'status_label' => ucfirst((string) $subscription->status),
                    'cycle_label' => $plan?->interval ? ucfirst((string) $plan->interval) : '--',
                    'next_due_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                    'auto_renew_label' => $subscription->auto_renew ? 'Yes' : 'No',
                    'routes' => [
                        'show' => route('client.services.show', $subscription),
                    ],
                ];
            })->values()->all(),
            'routes' => [
                'dashboard' => route('client.dashboard'),
            ],
        ]);
    }

    public function show(Request $request, Subscription $subscription): InertiaResponse
    {
        $customer = $request->user()?->customer;

        if (! $customer || $subscription->customer_id !== $customer->id) {
            abort(404);
        }

        $subscription->load(['plan.product', 'licenses.domains']);
        $dateFormat = config('app.date_format', 'd-m-Y');
        $plan = $subscription->plan;
        $product = $plan?->product;

        return Inertia::render('Client/Services/Show', [
            'service' => [
                'name' => $product?->name ?: 'Service',
                'plan_name' => $plan?->name ?? '--',
                'status_label' => ucfirst((string) $subscription->status),
                'cycle_label' => $plan?->interval ? ucfirst((string) $plan->interval) : '--',
                'start_date_display' => $subscription->start_date?->format($dateFormat) ?? '--',
                'period_start_display' => $subscription->current_period_start?->format($dateFormat) ?? '--',
                'period_end_display' => $subscription->current_period_end?->format($dateFormat) ?? '--',
                'auto_renew_label' => $subscription->auto_renew ? 'Enabled' : 'Disabled',
            ],
            'licenses' => $subscription->licenses->map(function ($license) {
                $key = (string) ($license->license_key ?? '');
                $maskedKey = $key !== '' && strlen($key) > 8
                    ? substr($key, 0, 4).str_repeat('*', max(0, strlen($key) - 8)).substr($key, -4)
                    : $key;

                return [
                    'id' => $license->id,
                    'masked_key' => $maskedKey ?: '--',
                    'domains' => $license->domains->pluck('domain')->filter()->values()->all(),
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('client.services.index'),
            ],
        ]);
    }
}
