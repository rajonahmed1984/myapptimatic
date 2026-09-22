<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Subscription;
use App\Services\AdminNotificationService;
use App\Services\SubscriptionCancellationService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ServiceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customer = $request->user()?->customer;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $subscriptions = $customer
            ? $customer->subscriptions()
                ->with(['plan.product', 'licenses.domains'])
                ->latest()
                ->get()
            : collect();

        return Inertia::render('Client/Services/Index', [
            'has_customer' => (bool) $customer,
            'subscriptions' => $subscriptions->map(function (Subscription $subscription, int $index) use ($dateFormat) {
                $plan = $subscription->plan;
                $product = $plan?->product;

                return [
                    'id' => $subscription->id,
                    'serial' => $index + 1,
                    'service_name' => $product?->name ?: 'Service',
                    'plan_name' => $plan?->name ?? '--',
                    'status_label' => ucfirst((string) $subscription->status),
                    'cycle_label' => $plan?->interval ? ucfirst((string) $plan->interval) : '--',
                    'next_due_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                    'auto_renew_label' => $subscription->auto_renew ? 'Yes' : 'No',
                    'routes' => [
                        'show' => route('client.services.show', $subscription),
                    ],
                ];
            })->values()->all(),
            'routes' => [
                'dashboard' => route('client.dashboard'),
            ],
        ]);
    }

    public function show(Request $request, Subscription $subscription): InertiaResponse
    {
        $customer = $request->user()?->customer;

        if (! $customer || $subscription->customer_id !== $customer->id) {
            abort(404);
        }

        $subscription->load(['plan.product', 'licenses.domains']);
        $dateFormat = config('app.date_format', 'd-m-Y');
        $plan = $subscription->plan;
        $product = $plan?->product;

        return Inertia::render('Client/Services/Show', [
            'service' => [
                'name' => $product?->name ?: 'Service',
                'plan_name' => $plan?->name ?? '--',
                'status_label' => ucfirst((string) $subscription->status),
                'cycle_label' => $plan?->interval ? ucfirst((string) $plan->interval) : '--',
                'start_date_display' => $subscription->start_date?->format($dateFormat) ?? '--',
                'period_start_display' => $subscription->current_period_start?->format($dateFormat) ?? '--',
                'period_end_display' => $subscription->current_period_end?->format($dateFormat) ?? '--',
                'auto_renew_label' => $subscription->auto_renew ? 'Enabled' : 'Disabled',
            ],
            'licenses' => $subscription->licenses->map(function ($license) {
                $key = (string) ($license->license_key ?? '');
                $maskedKey = $key !== '' && strlen($key) > 8
                    ? substr($key, 0, 4).str_repeat('*', max(0, strlen($key) - 8)).substr($key, -4)
                    : $key;

                return [
                    'id' => $license->id,
                    'masked_key' => $maskedKey ?: '--',
                    'domains' => $license->domains->pluck('domain')->filter()->values()->all(),
                ];
            })->values()->all(),
            'cancellation' => $this->cancellationProps($subscription),
            'routes' => [
                'index' => route('client.services.index'),
                'request_cancellation' => route('client.services.cancel-request', $subscription),
            ],
        ]);
    }

    /**
     * Ask an admin to end this service.
     *
     * An outstanding balance does not block the request — cancelling stops
     * future invoices but leaves what is already owed collectable — so the
     * amount is recorded on the request for the admin to see instead.
     */
    public function requestCancellation(
        Request $request,
        Subscription $subscription,
        SubscriptionCancellationService $cancellations,
        AdminNotificationService $adminNotifications
    ): RedirectResponse {
        $customer = $request->user()?->customer;

        if (! $customer || $subscription->customer_id !== $customer->id) {
            abort(404);
        }

        if (in_array($subscription->status, ['cancelled', 'expired'], true)) {
            return back()->withErrors(['cancellation' => 'This service is already cancelled.']);
        }

        if ($this->pendingRequestFor($subscription)) {
            return back()->withErrors(['cancellation' => 'A cancellation request for this service is already awaiting review.']);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in([
                CancellationRequest::TYPE_END_OF_PERIOD,
                CancellationRequest::TYPE_IMMEDIATE,
            ])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $cancellationRequest = CancellationRequest::create([
            'subscription_id' => $subscription->id,
            'customer_id' => $customer->id,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'status' => CancellationRequest::STATUS_PENDING,
            'requested_by' => $request->user()?->id,
            'due_at_request' => $cancellations->outstandingFor($subscription),
        ]);

        $adminNotifications->sendCancellationRequested($cancellationRequest);

        SystemLogger::write('activity', 'Cancellation requested.', [
            'cancellation_request_id' => $cancellationRequest->id,
            'subscription_id' => $subscription->id,
            'customer_id' => $customer->id,
            'type' => $data['type'],
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Cancellation request submitted. Our team will review it shortly.');
    }

    private function pendingRequestFor(Subscription $subscription): ?CancellationRequest
    {
        return CancellationRequest::where('subscription_id', $subscription->id)
            ->where('status', CancellationRequest::STATUS_PENDING)
            ->latest()
            ->first();
    }

    /**
     * What the service page needs to show in place of the request form.
     *
     * @return array<string, mixed>
     */
    private function cancellationProps(Subscription $subscription): array
    {
        $dateFormat = config('app.date_format', 'd-m-Y');
        $pending = $this->pendingRequestFor($subscription);
        $alreadyCancelled = in_array($subscription->status, ['cancelled', 'expired'], true);

        return [
            'can_request' => ! $pending && ! $alreadyCancelled,
            'already_cancelled' => $alreadyCancelled,
            'scheduled' => (bool) $subscription->cancel_at_period_end,
            'period_end_display' => $subscription->current_period_end?->format($dateFormat) ?? '--',
            'pending' => $pending ? [
                'type_label' => $pending->typeLabel(),
                'reason' => (string) $pending->reason,
                'requested_at_display' => $pending->created_at?->format($dateFormat) ?? '--',
            ] : null,
        ];
    }
}
