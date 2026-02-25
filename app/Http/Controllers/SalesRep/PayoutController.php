<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayoutController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $rep = $request->attributes->get('salesRep');

        $payouts = CommissionPayout::query()
            ->where('sales_representative_id', $rep->id)
            ->latest()
            ->paginate(25);

        return Inertia::render('Rep/Payouts/Index', [
            'payouts' => $payouts->getCollection()->map(function (CommissionPayout $payout) {
                return [
                    'id' => $payout->id,
                    'type_label' => ucfirst((string) ($payout->type ?? 'regular')),
                    'total_amount' => (float) ($payout->total_amount ?? 0),
                    'currency' => $payout->currency,
                    'status_label' => ucfirst((string) $payout->status),
                    'payout_method' => $payout->payout_method ?? '--',
                    'paid_at_display' => $payout->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
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
                'dashboard' => route('rep.dashboard'),
            ],
        ]);
    }
}
