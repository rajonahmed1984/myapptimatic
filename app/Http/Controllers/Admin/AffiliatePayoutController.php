<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AffiliatePayoutController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $query = AffiliatePayout::with('affiliate.customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payouts = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Affiliates/Payouts/Index', [
            'pageTitle' => 'Affiliate Payouts',
            'filters' => [
                'status' => (string) $request->query('status', ''),
            ],
            'status_options' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
            'routes' => [
                'index' => route('admin.affiliates.payouts.index'),
                'create' => route('admin.affiliates.payouts.create'),
            ],
            'payouts' => $payouts->getCollection()->map(function (AffiliatePayout $payout) {
                return [
                    'id' => $payout->id,
                    'payout_number' => (string) $payout->payout_number,
                    'affiliate_name' => (string) ($payout->affiliate?->customer?->name ?? 'Unknown'),
                    'amount_display' => '$' . number_format((float) $payout->amount, 2),
                    'status' => (string) $payout->status,
                    'status_label' => ucfirst((string) $payout->status),
                    'created_at_display' => $payout->created_at?->format('M d, Y H:i') ?? '--',
                    'completed_at_display' => $payout->completed_at?->format('M d, Y H:i') ?? '--',
                    'routes' => [
                        'show' => route('admin.affiliates.payouts.show', $payout),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $payouts->hasPages(),
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'previous_url' => $payouts->previousPageUrl(),
                'next_url' => $payouts->nextPageUrl(),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
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

        return Inertia::render('Admin/Affiliates/Payouts/Create', [
            'pageTitle' => 'Create Affiliate Payout',
            'selected_affiliate_id' => $affiliateId !== null ? (string) $affiliateId : '',
            'affiliates' => $affiliates->map(function (Affiliate $item) {
                return [
                    'id' => $item->id,
                    'name' => (string) ($item->customer?->name ?? 'Unknown'),
                    'code' => (string) ($item->affiliate_code ?? ''),
                    'balance_display' => '$' . number_format((float) $item->balance, 2),
                    'routes' => [
                        'select' => route('admin.affiliates.payouts.create', ['affiliate_id' => $item->id]),
                    ],
                ];
            })->values()->all(),
            'selected_affiliate' => $affiliate ? [
                'id' => $affiliate->id,
                'name' => (string) ($affiliate->customer?->name ?? 'Unknown'),
                'code' => (string) ($affiliate->affiliate_code ?? ''),
                'balance_display' => '$' . number_format((float) $affiliate->balance, 2),
            ] : null,
            'approved_commissions' => $approvedCommissions->map(function (AffiliateCommission $commission) {
                return [
                    'id' => $commission->id,
                    'description' => (string) $commission->description,
                    'amount' => (float) $commission->amount,
                    'amount_display' => '$' . number_format((float) $commission->amount, 2),
                    'invoice_number' => (string) ($commission->invoice?->number ?? '--'),
                    'order_number' => (string) ($commission->order?->order_number ?? '--'),
                ];
            })->values()->all(),
            'suggested_amount' => (float) $approvedCommissions->sum('amount'),
            'routes' => [
                'index' => route('admin.affiliates.payouts.index'),
                'create' => route('admin.affiliates.payouts.create'),
                'store' => route('admin.affiliates.payouts.store'),
            ],
        ]);
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

    public function show(AffiliatePayout $payout): InertiaResponse
    {
        $payout->load(['affiliate.customer', 'commissions.invoice', 'commissions.order']);

        return Inertia::render('Admin/Affiliates/Payouts/Show', [
            'pageTitle' => 'Affiliate Payout Details',
            'payout' => [
                'id' => $payout->id,
                'payout_number' => (string) $payout->payout_number,
                'affiliate_name' => (string) ($payout->affiliate?->customer?->name ?? 'Unknown'),
                'amount_display' => '$' . number_format((float) $payout->amount, 2),
                'status' => (string) $payout->status,
                'status_label' => ucfirst((string) $payout->status),
                'payment_method' => (string) ($payout->payment_method ?? '--'),
                'payment_details' => (string) ($payout->payment_details ?? '--'),
                'notes' => (string) ($payout->notes ?? '--'),
                'created_at_display' => $payout->created_at?->format('M d, Y H:i') ?? '--',
                'completed_at_display' => $payout->completed_at?->format('M d, Y H:i') ?? '--',
            ],
            'commissions' => $payout->commissions->map(function (AffiliateCommission $commission) {
                return [
                    'id' => $commission->id,
                    'description' => (string) $commission->description,
                    'amount_display' => '$' . number_format((float) $commission->amount, 2),
                    'invoice_number' => (string) ($commission->invoice?->number ?? '--'),
                    'order_number' => (string) ($commission->order?->order_number ?? '--'),
                    'status_label' => ucfirst((string) $commission->status),
                ];
            })->values()->all(),
            'can_complete' => $payout->status !== 'completed',
            'can_delete' => $payout->status !== 'completed',
            'routes' => [
                'index' => route('admin.affiliates.payouts.index'),
                'complete' => route('admin.affiliates.payouts.complete', $payout),
                'destroy' => route('admin.affiliates.payouts.destroy', $payout),
            ],
        ]);
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
