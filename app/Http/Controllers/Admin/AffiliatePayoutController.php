<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePayout;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AffiliatePayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = AffiliatePayout::with('affiliate.customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payouts = $query->latest()->paginate(20);

        return view('admin.affiliates.payouts.index', compact('payouts'));
    }

    public function create(Request $request)
    {
        $affiliateId = $request->get('affiliate_id');
        $affiliate = null;
        $approvedCommissions = collect();

        if ($affiliateId) {
            $affiliate = Affiliate::with('customer')->findOrFail($affiliateId);
            $approvedCommissions = AffiliateCommission::where('affiliate_id', $affiliateId)
                ->where('status', 'approved')
                ->whereNull('payout_id')
                ->with(['invoice', 'order'])
                ->get();
        }

        $affiliates = Affiliate::where('status', 'active')
            ->where('balance', '>', 0)
            ->with('customer')
            ->get();

        return view('admin.affiliates.payouts.create', compact('affiliates', 'affiliate', 'approvedCommissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'affiliate_id' => ['required', 'exists:affiliates,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'payment_details' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'commission_ids' => ['nullable', 'array'],
            'commission_ids.*' => ['exists:affiliate_commissions,id'],
        ]);

        DB::transaction(function () use ($data) {
            $payout = AffiliatePayout::create([
                'affiliate_id' => $data['affiliate_id'],
                'payout_number' => AffiliatePayout::generatePayoutNumber(),
                'amount' => $data['amount'],
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'payment_details' => $data['payment_details'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (!empty($data['commission_ids'])) {
                AffiliateCommission::whereIn('id', $data['commission_ids'])
                    ->where('affiliate_id', $data['affiliate_id'])
                    ->where('status', 'approved')
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payout_id' => $payout->id,
                    ]);

                $payout->affiliate->increment('total_paid', $data['amount']);
                $payout->affiliate->updateBalance();
            }
        });

        return redirect()->route('admin.affiliates.payouts.index')
            ->with('status', 'Payout created successfully.');
    }

    public function show(AffiliatePayout $payout)
    {
        $payout->load(['affiliate.customer', 'commissions.invoice', 'commissions.order']);

        return view('admin.affiliates.payouts.show', compact('payout'));
    }

    public function markAsCompleted(AffiliatePayout $payout)
    {
        if ($payout->status === 'completed') {
            return back()->with('error', 'Payout is already completed.');
        }

        $payout->markAsCompleted();

        return back()->with('status', 'Payout marked as completed.');
    }

    public function destroy(AffiliatePayout $payout)
    {
        if ($payout->status === 'completed') {
            return back()->with('error', 'Cannot delete completed payout.');
        }

        DB::transaction(function () use ($payout) {
            // Revert commissions back to approved
            $payout->commissions()->update([
                'status' => 'approved',
                'paid_at' => null,
                'payout_id' => null,
            ]);

            $payout->affiliate->decrement('total_paid', $payout->amount);
            $payout->affiliate->updateBalance();

            $payout->delete();
        });

        return redirect()->route('admin.affiliates.payouts.index')
            ->with('status', 'Payout deleted successfully.');
    }
}
