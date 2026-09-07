<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LicenseController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customer = $request->user()->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $licenses = $customer?->licenses()
            ->with(['product', 'subscription.plan', 'domains'])
            ->latest()
            ->get() ?? collect();

        return Inertia::render('Client/Licenses/Index', [
            'licenses' => $licenses->map(function (License $license) use ($dateFormat) {
                $domain = $license->domains->firstWhere('status', 'active')?->domain
                    ?? $license->domains->first()?->domain;
                $plan = $license->subscription?->plan;
                $planName = strtolower((string) ($plan?->name ?? ''));
                $planSlug = strtolower((string) ($plan?->slug ?? ''));
                $notes = strtolower((string) ($license->notes ?? ''));
                $isPremium = (bool) (
                    ($planName !== '' && (
                        str_contains($planName, 'premium') ||
                        str_contains($planName, 'plus') ||
                        str_contains($planName, '+') ||
                        str_contains($planName, 'pro') ||
                        str_contains($planName, 'pms') ||
                        str_contains($planName, 'enterprise') ||
                        str_contains($planName, 'vip') ||
                        str_contains($planName, 'business')
                    )) ||
                    ($planSlug !== '' && (
                        str_contains($planSlug, 'premium') ||
                        str_contains($planSlug, 'plus') ||
                        str_contains($planSlug, 'pro') ||
                        str_contains($planSlug, 'pms')
                    )) ||
                    str_contains($notes, 'premium') ||
                    str_contains($notes, 'pro') ||
                    (int) ($license->max_domains ?? 1) > 1 ||
                    (int) ($license->seat_limit ?? 0) > 1 ||
                    (float) ($plan?->price ?? 0) >= 2500
                );
                $key = (string) ($license->license_key ?? '');
                $maskedKey = $key !== '' && strlen($key) > 8
                    ? substr($key, 0, 4).str_repeat('*', max(0, strlen($key) - 8)).substr($key, -4)
                    : $key;

                return [
                    'id' => $license->id,
                    'domain' => $domain,
                    'site_url' => $domain ? 'https://'.$domain : null,
                    'product_name' => $license->product?->name ?? '-',
                    'plan_name' => $plan?->name ?? '-',
                    'installed_on' => $license->starts_at?->format($dateFormat) ?? '-',
                    'masked_key' => $maskedKey ?: '-',
                    'license_key' => $key ?: '-',
                    'is_premium' => $isPremium,
                    'status_label' => ucfirst((string) $license->status),
                    'is_active' => $license->status === 'active',
                ];
            })->values()->all(),
            'routes' => [
                'dashboard' => route('client.dashboard'),
            ],
        ]);
    }
}
