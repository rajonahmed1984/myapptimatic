<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\PaymentMethod;
use App\Models\SalesRepresentative;
use App\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CommissionPayoutController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $payouts = CommissionPayout::query()
            ->with(['salesRep', 'project:id,name'])
            ->latest()
            ->paginate(25);

        $payableByRep = CommissionEarning::query()
            ->select('sales_representative_id', DB::raw('COUNT(*) as earnings_count'), DB::raw('SUM(commission_amount) as total_amount'))
            ->where('status', 'payable')
            ->whereNull('commission_payout_id')
            ->groupBy('sales_representative_id')
            ->get()
            ->keyBy('sales_representative_id');

        $salesReps = SalesRepresentative::whereIn('id', $payableByRep->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/CommissionPayouts/Index',
            $this->indexInertiaProps($payouts, $salesReps, $payableByRep)
        );
    }

    public function create(Request $request)
    {
        $salesRepId = $request->query('sales_rep_id');

        $payableQuery = CommissionEarning::query()
            ->with(['invoice', 'subscription', 'project', 'customer'])
            ->where('status', 'payable')
            ->whereNull('commission_payout_id')
            ->orderBy('earned_at', 'asc');

        if ($salesRepId) {
            $payableQuery->where('sales_representative_id', $salesRepId);
        }

        $earnings = $payableQuery->get();
        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);
        $repBalance = null;

        if ($salesRepId) {
            $repBalance = app(CommissionService::class)->computeRepBalance((int) $salesRepId);
        }

        return view('admin.commission-payouts.create', [
            'earnings' => $earnings,
            'salesReps' => $salesReps,
            'selectedRep' => $salesRepId,
            'repBalance' => $repBalance,
        ]);
    }

    public function store(Request $request, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'sales_rep_id' => ['required', 'exists:sales_representatives,id'],
            'earning_ids' => ['required', 'array', 'min:1'],
            'earning_ids.*' => ['integer', 'distinct'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCommissionPayoutCodes())],
            'note' => ['nullable', 'string'],
        ]);

        $balance = $commissionService->computeRepBalance((int) $data['sales_rep_id']);
        $netPayable = (float) ($balance['payable_balance'] ?? 0);

        if ($netPayable <= 0) {
            return back()
                ->withErrors(['payout' => 'Advance payments already cover earned commission. Net payable is 0.'])
                ->withInput();
        }

        $selectedTotal = (float) CommissionEarning::query()
            ->whereIn('id', $data['earning_ids'])
            ->where('sales_representative_id', (int) $data['sales_rep_id'])
            ->where('status', 'payable')
            ->whereNull('commission_payout_id')
            ->sum('commission_amount');

        if ($selectedTotal > $netPayable) {
            return back()
                ->withErrors(['payout' => 'Selected payout exceeds net payable after advances.'])
                ->withInput();
        }

        try {
            $payout = $commissionService->createPayout(
                (int) $data['sales_rep_id'],
                $data['earning_ids'],
                'BDT',
                $data['payout_method'] ?? null,
                $data['note'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['payout' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.commission-payouts.show', $payout)
            ->with('status', 'Payout draft created.');
    }

    public function show(CommissionPayout $commissionPayout)
    {
        $commissionPayout->load(['salesRep', 'project', 'earnings' => function ($query) {
            $query->with(['invoice', 'subscription', 'project', 'customer']);
        }]);

        return view('admin.commission-payouts.show', [
            'payout' => $commissionPayout,
        ]);
    }

    public function markPaid(Request $request, CommissionPayout $commissionPayout, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'payout_method' => ['nullable', Rule::in(PaymentMethod::allowedCommissionPayoutCodes())],
        ]);

        try {
            $commissionService->markPayoutPaid(
                $commissionPayout,
                $data['reference'] ?? null,
                $data['note'] ?? null,
                $data['payout_method'] ?? null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }

        return back()->with('status', 'Payout marked as paid.');
    }

    public function reverse(Request $request, CommissionPayout $commissionPayout, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        try {
            $commissionService->reversePayout($commissionPayout, $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['payout' => $e->getMessage()]);
        }

        return back()->with('status', 'Payout reversed and earnings returned to payable.');
    }

    private function indexInertiaProps(
        LengthAwarePaginator $payouts,
        Collection $salesReps,
        Collection $payableByRep
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Commission Payouts',
            'routes' => [
                'export_payouts' => route('admin.commission-payouts.export'),
                'export_earnings' => route('admin.commission-earnings.export'),
                'create' => route('admin.commission-payouts.create'),
            ],
            'payable_by_rep' => $salesReps->map(function (SalesRepresentative $rep) use ($payableByRep) {
                $aggregate = $payableByRep->get($rep->id);

                return [
                    'id' => $rep->id,
                    'name' => $rep->name,
                    'status' => ucfirst((string) $rep->status),
                    'earnings_count' => (int) ($aggregate->earnings_count ?? 0),
                    'total_amount_display' => number_format((float) ($aggregate->total_amount ?? 0), 2),
                    'routes' => [
                        'create' => route('admin.commission-payouts.create', ['sales_rep_id' => $rep->id]),
                    ],
                ];
            })->values()->all(),
            'payouts' => collect($payouts->items())->map(function (CommissionPayout $payout) use ($dateFormat) {
                return [
                    'id' => $payout->id,
                    'sales_rep_name' => $payout->salesRep?->name ?? '--',
                    'project_name' => $payout->project?->name ?? '--',
                    'type_label' => ucfirst((string) ($payout->type ?? 'regular')),
                    'total_amount_display' => number_format((float) $payout->total_amount, 2).' '.$payout->currency,
                    'status_label' => ucfirst((string) $payout->status),
                    'paid_at_display' => $payout->paid_at?->format($dateFormat.' H:i') ?? '--',
                    'updated_at_display' => $payout->updated_at?->format($dateFormat.' H:i') ?? '--',
                    'routes' => [
                        'show' => route('admin.commission-payouts.show', $payout),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $payouts->hasPages(),
                'previous_url' => $payouts->previousPageUrl(),
                'next_url' => $payouts->nextPageUrl(),
            ],
        ];
    }
}
