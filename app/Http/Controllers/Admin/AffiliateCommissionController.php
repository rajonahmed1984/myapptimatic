<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AffiliateCommissionController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        return Inertia::render('Admin/Affiliates/Commissions/Index', [
            'pageTitle' => 'Affiliate Commissions',
            'filters' => [
                'affiliate_id' => (string) $request->query('affiliate_id', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'status_options' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'paid', 'label' => 'Paid'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'routes' => [
                'index' => route('admin.affiliates.commissions.index'),
                'affiliates_index' => route('admin.affiliates.index'),
                'bulk_approve' => route('admin.affiliates.commissions.bulk-approve'),
            ],
            'affiliates' => $affiliates->map(function (Affiliate $affiliate) {
                return [
                    'id' => $affiliate->id,
                    'name' => (string) ($affiliate->customer?->name ?? 'Unknown'),
                    'code' => (string) ($affiliate->affiliate_code ?? ''),
                ];
            })->values()->all(),
            'commissions' => $commissions->getCollection()->map(function (AffiliateCommission $commission) {
                return [
                    'id' => $commission->id,
                    'date_display' => $commission->created_at?->format('M d, Y') ?? '--',
                    'affiliate_name' => (string) ($commission->affiliate?->customer?->name ?? 'Unknown'),
                    'affiliate_code' => (string) ($commission->affiliate?->affiliate_code ?? ''),
                    'description' => (string) $commission->description,
                    'amount_display' => '$' . number_format((float) $commission->amount, 2),
                    'status' => (string) $commission->status,
                    'status_label' => ucfirst((string) $commission->status),
                    'can_decide' => $commission->status === 'pending',
                    'routes' => [
                        'approve' => route('admin.affiliates.commissions.approve', $commission),
                        'reject' => route('admin.affiliates.commissions.reject', $commission),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $commissions->hasPages(),
                'current_page' => $commissions->currentPage(),
                'last_page' => $commissions->lastPage(),
                'previous_url' => $commissions->previousPageUrl(),
                'next_url' => $commissions->nextPageUrl(),
            ],
        ]);
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
