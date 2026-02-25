<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AffiliateController extends Controller
{
    public function index(): InertiaResponse
    {
        $customer = auth()->user()->customer;

        if (! $customer) {
            abort(404);
        }

        $affiliate = $customer->affiliate;

        if (! $affiliate) {
            return Inertia::render('Client/Affiliates/NotEnrolled', [
                'routes' => [
                    'apply' => route('client.affiliates.apply'),
                ],
            ]);
        }

        $affiliate->load(['referrals', 'commissions', 'payouts']);

        $stats = [
            'total_clicks' => $affiliate->referrals()->count(),
            'total_conversions' => $affiliate->referrals()->where('status', 'converted')->count(),
            'conversion_rate' => $affiliate->referrals()->count() > 0
                ? round(($affiliate->referrals()->where('status', 'converted')->count() / $affiliate->referrals()->count()) * 100, 2)
                : 0,
            'pending_commissions' => $affiliate->commissions()->where('status', 'pending')->sum('amount'),
            'approved_commissions' => $affiliate->commissions()->where('status', 'approved')->sum('amount'),
            'paid_commissions' => $affiliate->commissions()->where('status', 'paid')->sum('amount'),
            'recent_referrals' => $affiliate->referrals()->with('customer')->latest()->limit(10)->get(),
            'recent_commissions' => $affiliate->commissions()->with(['invoice', 'order'])->latest()->limit(10)->get(),
        ];

        return Inertia::render('Client/Affiliates/Dashboard', [
            'affiliate' => [
                'id' => $affiliate->id,
                'status' => $affiliate->status,
                'balance' => (float) $affiliate->balance,
                'total_earned' => (float) $affiliate->total_earned,
                'affiliate_code' => $affiliate->affiliate_code,
                'referral_link' => $affiliate->getReferralLink(),
            ],
            'stats' => [
                'total_clicks' => (int) $stats['total_clicks'],
                'total_conversions' => (int) $stats['total_conversions'],
                'conversion_rate' => (float) $stats['conversion_rate'],
                'pending_commissions' => (float) $stats['pending_commissions'],
                'approved_commissions' => (float) $stats['approved_commissions'],
                'paid_commissions' => (float) $stats['paid_commissions'],
                'recent_referrals' => $stats['recent_referrals']->map(function ($referral) {
                    return [
                        'id' => $referral->id,
                        'customer_name' => $referral->customer?->name ?? 'Visitor',
                        'status_label' => ucfirst((string) $referral->status),
                        'status' => (string) $referral->status,
                        'created_at_display' => $referral->created_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    ];
                })->values()->all(),
            ],
            'routes' => [
                'referrals' => route('client.affiliates.referrals'),
                'commissions' => route('client.affiliates.commissions'),
                'payouts' => route('client.affiliates.payouts'),
                'settings' => route('client.affiliates.settings'),
            ],
        ]);
    }

    public function apply(): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $customer = auth()->user()->customer;

        if (! $customer) {
            abort(404);
        }

        if ($customer->affiliate) {
            return redirect()->route('client.affiliates.index')
                ->with('status', 'You are already enrolled in the affiliate program.');
        }

        return Inertia::render('Client/Affiliates/Apply', [
            'routes' => [
                'store' => route('client.affiliates.apply.store'),
                'index' => route('client.affiliates.index'),
            ],
        ]);
    }

    public function storeApplication(Request $request)
    {
        $customer = auth()->user()->customer;

        if (! $customer) {
            abort(404);
        }

        if ($customer->affiliate) {
            return redirect()->route('client.affiliates.index')
                ->with('status', 'You are already enrolled in the affiliate program.');
        }

        $data = $request->validate([
            'payment_details' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $affiliate = Affiliate::create([
            'customer_id' => $customer->id,
            'affiliate_code' => Affiliate::generateUniqueCode(),
            'status' => 'inactive', // Requires admin approval
            'commission_rate' => 10.00, // Default 10%
            'commission_type' => 'percentage',
            'payment_details' => $data['payment_details'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('client.affiliates.index')
            ->with('status', 'Your affiliate application has been submitted. We will review it shortly.');
    }

    public function referrals(): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        $referrals = $affiliate->referrals()
            ->with('customer')
            ->latest()
            ->paginate(20);

        return Inertia::render('Client/Affiliates/Referrals', [
            'affiliate' => [
                'id' => $affiliate->id,
                'code' => $affiliate->affiliate_code,
            ],
            'referrals' => $referrals->getCollection()->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'customer_name' => $referral->customer?->name ?? 'Visitor',
                    'status' => (string) $referral->status,
                    'status_label' => ucfirst((string) $referral->status),
                    'created_at_display' => $referral->created_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
                'from' => $referrals->firstItem(),
                'to' => $referrals->lastItem(),
                'prev_page_url' => $referrals->previousPageUrl(),
                'next_page_url' => $referrals->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('client.affiliates.index'),
            ],
        ]);
    }

    public function commissions(): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        $commissions = $affiliate->commissions()
            ->with(['invoice', 'order', 'referral'])
            ->latest()
            ->paginate(20);

        return Inertia::render('Client/Affiliates/Commissions', [
            'affiliate' => [
                'id' => $affiliate->id,
                'code' => $affiliate->affiliate_code,
            ],
            'commissions' => $commissions->getCollection()->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'description' => $commission->description,
                    'amount' => (float) $commission->amount,
                    'status' => (string) $commission->status,
                    'status_label' => ucfirst((string) $commission->status),
                    'invoice_label' => $commission->invoice ? '#'.($commission->invoice->number ?? $commission->invoice->id) : '--',
                    'order_label' => $commission->order ? '#'.($commission->order->order_number ?? $commission->order->id) : '--',
                    'created_at_display' => $commission->created_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
                'per_page' => $commissions->perPage(),
                'total' => $commissions->total(),
                'from' => $commissions->firstItem(),
                'to' => $commissions->lastItem(),
                'prev_page_url' => $commissions->previousPageUrl(),
                'next_page_url' => $commissions->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('client.affiliates.index'),
            ],
        ]);
    }

    public function payouts(): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        $payouts = $affiliate->payouts()
            ->with('commissions')
            ->latest()
            ->paginate(20);

        return Inertia::render('Client/Affiliates/Payouts', [
            'affiliate' => [
                'id' => $affiliate->id,
                'code' => $affiliate->affiliate_code,
            ],
            'payouts' => $payouts->getCollection()->map(function ($payout) {
                return [
                    'id' => $payout->id,
                    'payout_number' => $payout->payout_number,
                    'amount' => (float) $payout->amount,
                    'status' => (string) $payout->status,
                    'status_label' => ucfirst((string) $payout->status),
                    'payment_method' => $payout->payment_method,
                    'processed_at_display' => $payout->processed_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'completed_at_display' => $payout->completed_at?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'commissions_count' => (int) $payout->commissions->count(),
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
                'from' => $payouts->firstItem(),
                'to' => $payouts->lastItem(),
                'prev_page_url' => $payouts->previousPageUrl(),
                'next_page_url' => $payouts->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('client.affiliates.index'),
            ],
        ]);
    }

    public function settings(): InertiaResponse|\Illuminate\Http\RedirectResponse
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        return Inertia::render('Client/Affiliates/Settings', [
            'affiliate' => [
                'id' => $affiliate->id,
                'code' => $affiliate->affiliate_code,
                'payment_details' => $affiliate->payment_details,
            ],
            'routes' => [
                'index' => route('client.affiliates.index'),
                'update' => route('client.affiliates.settings.update'),
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        $data = $request->validate([
            'payment_details' => ['nullable', 'string'],
        ]);

        $affiliate->update($data);

        return back()->with('status', 'Settings updated successfully.');
    }
}
