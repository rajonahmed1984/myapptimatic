<?php

namespace App\Http\Controllers\SalesRep;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\CommissionPayoutRequest;
use App\Models\PaymentMethod;
use App\Services\SalesRepPayoutRequestService;
use App\Support\PaginationPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

class PayoutController extends Controller
{
    public function index(Request $request, SalesRepPayoutRequestService $requests): InertiaResponse
    {
        $rep = $request->attributes->get('salesRep');
        $dateTimeFormat = (string) config('app.datetime_format', 'd-m-Y h:i A');
        $methodNames = self::methodNames();

        $payouts = CommissionPayout::query()
            ->where('sales_representative_id', $rep->id)
            ->latest()
            ->paginate(25);

        $payoutRequests = CommissionPayoutRequest::query()
            ->where('sales_representative_id', $rep->id)
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('Rep/Payouts/Index', [
            'availability' => $requests->availability($rep),
            'payout_requests' => $payoutRequests->map(fn (CommissionPayoutRequest $item) => [
                'id' => $item->id,
                'amount' => (float) $item->amount,
                'paid_amount' => $item->paid_amount !== null ? (float) $item->paid_amount : null,
                'currency' => $item->currency,
                'status' => $item->status,
                'note' => $item->note,
                'admin_note' => $item->admin_note,
                'method' => $item->payout_method ? ($methodNames[$item->payout_method] ?? $item->payout_method) : null,
                'reference' => $item->reference,
                'requested_at' => $item->created_at?->format($dateTimeFormat),
                'processed_at' => $item->processed_at?->format($dateTimeFormat),
                'cancel_url' => $item->isPending() ? route('rep.payouts.requests.cancel', $item) : null,
            ])->values()->all(),
            'payouts' => $payouts->getCollection()->map(function (CommissionPayout $payout) use ($methodNames, $dateTimeFormat) {
                return [
                    'id' => $payout->id,
                    'type_label' => ucfirst((string) ($payout->type ?? 'regular')),
                    'total_amount' => (float) ($payout->total_amount ?? 0),
                    'currency' => $payout->currency,
                    'status_label' => ucfirst((string) $payout->status),
                    'payout_method' => $payout->payout_method ? ($methodNames[$payout->payout_method] ?? $payout->payout_method) : '--',
                    'paid_at_display' => $payout->paid_at?->format($dateTimeFormat) ?? '--',
                ];
            })->values()->all(),
            'pagination' => PaginationPayload::make($payouts),
            'routes' => [
                'dashboard' => route('rep.dashboard'),
                'request' => route('rep.payouts.requests.store'),
                'profile' => route('rep.profile.edit'),
            ],
        ]);
    }

    public function storeRequest(Request $request, SalesRepPayoutRequestService $requests): RedirectResponse
    {
        $rep = $request->attributes->get('salesRep');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $requests->submit($rep, (float) $data['amount'], $data['note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Payout request sent. You will see here when the admin pays it.');
    }

    public function cancelRequest(Request $request, CommissionPayoutRequest $payoutRequest, SalesRepPayoutRequestService $requests): RedirectResponse
    {
        $rep = $request->attributes->get('salesRep');
        abort_unless((int) $payoutRequest->sales_representative_id === (int) $rep->id, 404);

        try {
            $requests->cancel($payoutRequest);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Payout request cancelled.');
    }

    /**
     * @return array<string, string> payout method code => name
     */
    public static function methodNames(): array
    {
        return PaymentMethod::commissionPayoutDropdownOptions()
            ->mapWithKeys(fn ($method) => [(string) $method->code => (string) $method->name])
            ->all();
    }
}
