<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function index()
    {
        $customer = auth()->user()->customer;
        
        if (! $customer) {
            abort(404);
        }

        $affiliate = $customer->affiliate;

        if (! $affiliate) {
            return view('client.affiliates.not-enrolled');
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

        return view('client.affiliates.dashboard', compact('affiliate', 'stats'));
    }

    public function apply()
    {
        $customer = auth()->user()->customer;
        
        if (! $customer) {
            abort(404);
        }

        if ($customer->affiliate) {
            return redirect()->route('client.affiliates.index')
                ->with('status', 'You are already enrolled in the affiliate program.');
        }

        return view('client.affiliates.apply');
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

    public function referrals()
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

        return view('client.affiliates.referrals', compact('affiliate', 'referrals'));
    }

    public function commissions()
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

        return view('client.affiliates.commissions', compact('affiliate', 'commissions'));
    }

    public function payouts()
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

        return view('client.affiliates.payouts', compact('affiliate', 'payouts'));
    }

    public function settings()
    {
        $customer = auth()->user()->customer;
        $affiliate = $customer?->affiliate;

        if (! $affiliate) {
            return redirect()->route('client.affiliates.index');
        }

        return view('client.affiliates.settings', compact('affiliate'));
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
