<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\SalesRepresentative;
use App\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommissionPayoutController extends Controller
{
    public function index()
    {
        $payouts = CommissionPayout::query()
            ->with('salesRep')
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

        return view('admin.commission-payouts.index', [
            'payouts' => $payouts,
            'salesReps' => $salesReps,
            'payableByRep' => $payableByRep,
        ]);
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

        return view('admin.commission-payouts.create', [
            'earnings' => $earnings,
            'salesReps' => $salesReps,
            'selectedRep' => $salesRepId,
        ]);
    }

    public function store(Request $request, CommissionService $commissionService): RedirectResponse
    {
        $data = $request->validate([
            'sales_rep_id' => ['required', 'exists:sales_representatives,id'],
            'earning_ids' => ['required', 'array', 'min:1'],
            'earning_ids.*' => ['integer', 'distinct'],
            'payout_method' => ['nullable', 'in:bank,mobile,cash'],
            'note' => ['nullable', 'string'],
        ]);

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
        $commissionPayout->load(['salesRep', 'earnings' => function ($query) {
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
            'payout_method' => ['nullable', 'in:bank,mobile,cash'],
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
}
