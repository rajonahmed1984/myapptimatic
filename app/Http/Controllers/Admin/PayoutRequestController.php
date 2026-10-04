<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesRep\PayoutController;
use App\Models\CommissionPayoutRequest;
use App\Models\PaymentMethod;
use App\Models\SalesRepresentative;
use App\Services\SalesRepPayoutRequestService;
use App\Support\PaginationPayload;
use App\Support\PayoutRequestPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Every sales rep's payout requests in one place, waiting ones first, so
 * they can be paid or declined without opening each rep.
 */
class PayoutRequestController extends Controller
{
    private const STATUSES = ['pending', 'paid', 'rejected', 'cancelled', 'all'];

    public function index(Request $request, SalesRepPayoutRequestService $requests): InertiaResponse
    {
        $status = (string) $request->query('status', 'pending');
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }
        $search = trim((string) $request->query('search', ''));

        $query = CommissionPayoutRequest::query()
            ->with(['salesRep:id,name,email,payout_method_default,payout_details_encrypted', 'processor:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('salesRep', function ($rep) use ($search) {
                $rep->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            }))
            // Waiting first (oldest first, so nobody waits longest), then newest.
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'pending' THEN id END ASC")
            ->latest('id');

        $page = $query->paginate(30)->withQueryString();

        $methodNames = PayoutController::methodNames();
        $availabilityByRep = [];
        foreach ($page->getCollection()->where('status', CommissionPayoutRequest::STATUS_PENDING)->pluck('salesRep')->filter()->unique('id') as $rep) {
            /** @var SalesRepresentative $rep */
            $availabilityByRep[$rep->id] = $requests->availability($rep);
        }

        $counts = CommissionPayoutRequest::query()
            ->selectRaw('status, COUNT(*) as total, SUM(amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('Admin/PayoutRequests/Index', [
            'pageTitle' => 'Payout Requests',
            'status' => $status,
            'search' => $search,
            'tabs' => collect(self::STATUSES)->map(fn ($key) => [
                'key' => $key,
                'label' => match ($key) {
                    'pending' => 'Waiting',
                    'paid' => 'Paid',
                    'rejected' => 'Declined',
                    'cancelled' => 'Cancelled',
                    default => 'All',
                },
                'count' => $key === 'all' ? (int) $counts->sum('total') : (int) ($counts[$key]->total ?? 0),
            ])->values()->all(),
            'waiting_total' => (float) ($counts['pending']->amount ?? 0),
            'requests' => $page->getCollection()
                ->map(fn (CommissionPayoutRequest $item) => PayoutRequestPresenter::adminRow(
                    $item,
                    $availabilityByRep[$item->sales_representative_id] ?? null,
                    $methodNames
                ))
                ->values()
                ->all(),
            'paymentMethods' => PaymentMethod::commissionPayoutDropdownOptions()
                ->map(fn ($method) => ['code' => $method->code, 'name' => $method->name])
                ->values(),
            'pagination' => PaginationPayload::make($page),
            'routes' => [
                'index' => route('admin.payout-requests.index'),
            ],
        ]);
    }
}
