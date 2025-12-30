<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\Affiliate;
use Illuminate\Http\Request;

class AffiliateCommissionController extends Controller
{
    public function index(Request $request)
    {
        $query = AffiliateCommission::with(['affiliate.customer', 'invoice', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        $commissions = $query->latest()->paginate(20);
        $affiliates = Affiliate::with('customer')->orderBy('id', 'desc')->get();

        return view('admin.affiliates.commissions.index', compact('commissions', 'affiliates'));
    }

    public function approve(AffiliateCommission $commission)
    {
        if ($commission->status !== 'pending') {
            return back()->with('error', 'Only pending commissions can be approved.');
        }

        $commission->approve();

        return back()->with('status', 'Commission approved successfully.');
    }

    public function reject(AffiliateCommission $commission)
    {
        if ($commission->status !== 'pending') {
            return back()->with('error', 'Only pending commissions can be rejected.');
        }

        $commission->update(['status' => 'cancelled']);

        return back()->with('status', 'Commission rejected.');
    }

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'commission_ids' => ['required', 'array'],
            'commission_ids.*' => ['exists:affiliate_commissions,id'],
        ]);

        $commissions = AffiliateCommission::whereIn('id', $request->commission_ids)
            ->where('status', 'pending')
            ->get();

        foreach ($commissions as $commission) {
            $commission->approve();
        }

        return back()->with('status', count($commissions) . ' commission(s) approved.');
    }
}
