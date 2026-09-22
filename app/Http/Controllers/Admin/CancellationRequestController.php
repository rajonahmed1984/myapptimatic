<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Setting;
use App\Services\ClientNotificationService;
use App\Services\SubscriptionCancellationService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CancellationRequestController extends Controller
{
    public function __construct(private readonly SubscriptionCancellationService $cancellations) {}

    public function index(Request $request): InertiaResponse
    {
        $status = (string) ($request->query('status') ?: 'pending');
        $allowed = ['pending', 'accepted', 'rejected', 'all'];

        if (! in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $search = trim((string) $request->input('search', ''));

        $query = CancellationRequest::query()
            ->with(['subscription.plan.product', 'customer', 'reviewer'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reason', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%');
                    });

                if (is_numeric($search)) {
                    $inner->orWhere('id', (int) $search)
                        ->orWhere('subscription_id', (int) $search);
                }
            });
        }

        return Inertia::render(
            'Admin/CancellationRequests/Index',
            $this->indexInertiaProps($query->get(), $status, $search)
        );
    }

    public function accept(
        Request $request,
        CancellationRequest $cancellationRequest,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        if (! $cancellationRequest->isPending()) {
            return back()->with('status', 'This request has already been reviewed.');
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $cancellationRequest->loadMissing('subscription.licenses');

        $message = $this->cancellations->apply($cancellationRequest);

        $cancellationRequest->update([
            'status' => CancellationRequest::STATUS_ACCEPTED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $clientNotifications->sendCancellationAccepted($cancellationRequest);

        SystemLogger::write('activity', 'Cancellation request accepted.', [
            'cancellation_request_id' => $cancellationRequest->id,
            'subscription_id' => $cancellationRequest->subscription_id,
            'type' => $cancellationRequest->type,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', $message);
    }

    public function reject(
        Request $request,
        CancellationRequest $cancellationRequest,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        if (! $cancellationRequest->isPending()) {
            return back()->with('status', 'This request has already been reviewed.');
        }

        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $cancellationRequest->update([
            'status' => CancellationRequest::STATUS_REJECTED,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'admin_note' => $data['admin_note'],
        ]);

        $clientNotifications->sendCancellationRejected($cancellationRequest);

        SystemLogger::write('activity', 'Cancellation request rejected.', [
            'cancellation_request_id' => $cancellationRequest->id,
            'subscription_id' => $cancellationRequest->subscription_id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Cancellation request rejected.');
    }

    /**
     * @param  Collection<int, CancellationRequest>  $requests
     * @return array<string, mixed>
     */
    private function indexInertiaProps(Collection $requests, string $status, string $search): array
    {
        $dateFormat = config('app.datetime_format', 'd-m-Y h:i A');
        $dateOnly = config('app.date_format', 'd-m-Y');

        return [
            'filters' => ['status' => $status, 'search' => $search],
            'counts' => [
                'pending' => CancellationRequest::where('status', CancellationRequest::STATUS_PENDING)->count(),
                'accepted' => CancellationRequest::where('status', CancellationRequest::STATUS_ACCEPTED)->count(),
                'rejected' => CancellationRequest::where('status', CancellationRequest::STATUS_REJECTED)->count(),
                'all' => CancellationRequest::count(),
            ],
            'requests' => $requests->map(function (CancellationRequest $item) use ($dateFormat, $dateOnly) {
                $subscription = $item->subscription;

                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'status_label' => ucfirst((string) $item->status),
                    'type' => $item->type,
                    'type_label' => $item->typeLabel(),
                    'reason' => (string) $item->reason,
                    'admin_note' => (string) $item->admin_note,
                    'customer_name' => (string) ($item->customer?->name ?? '--'),
                    'customer_email' => (string) ($item->customer?->email ?? '--'),
                    'service_name' => (string) ($subscription?->plan?->product?->name ?? 'Service'),
                    'plan_name' => (string) ($subscription?->plan?->name ?? '--'),
                    'subscription_id' => $item->subscription_id,
                    'subscription_status' => (string) ($subscription?->status ?? '--'),
                    'period_end_display' => $subscription?->current_period_end?->format($dateOnly) ?? '--',
                    'due_at_request' => (float) $item->due_at_request,
                    'requested_at_display' => $item->created_at?->format($dateFormat) ?? '--',
                    'reviewed_at_display' => $item->reviewed_at?->format($dateFormat) ?? '--',
                    'reviewer_name' => (string) ($item->reviewer?->name ?? '--'),
                    'routes' => [
                        'accept' => route('admin.cancellation-requests.accept', $item),
                        'reject' => route('admin.cancellation-requests.reject', $item),
                        'subscription' => $item->subscription_id
                            ? route('admin.subscriptions.edit', $item->subscription_id)
                            : null,
                    ],
                ];
            })->values()->all(),
            'currency' => strtoupper((string) (Setting::getValue('currency') ?: 'BDT')),
        ];
    }
}
