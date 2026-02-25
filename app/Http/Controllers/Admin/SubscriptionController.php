<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\SalesRepresentative;
use App\Models\Subscription;
use App\Services\AccessBlockService;
use App\Services\BillingService;
use App\Support\AjaxResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SubscriptionController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $payload = $this->indexPayload($request);

        return Inertia::render(
            'Admin/Subscriptions/Index',
            $this->indexInertiaProps(
                $payload['subscriptions'],
                (string) ($payload['search'] ?? '')
            )
        );
    }

    public function create(Request $request): InertiaResponse
    {
        $customers = Customer::query()->orderBy('name')->get();
        $plans = Plan::query()->with('product')->orderBy('name')->get();
        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/Subscriptions/Form',
            $this->formInertiaProps(null, $customers, $plans, $salesReps)
        );
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'start_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'cancelled', 'suspended'])],
            'auto_renew' => ['nullable', 'boolean'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $startDate = Carbon::parse($data['start_date']);
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();

        $subscription = Subscription::create([
            'customer_id' => $data['customer_id'],
            'plan_id' => $data['plan_id'],
            'sales_rep_id' => $data['sales_rep_id'] ?? null,
            'status' => $data['status'],
            'start_date' => $startDate->toDateString(),
            'current_period_start' => $startDate->toDateString(),
            'current_period_end' => $periodEnd->toDateString(),
            'next_invoice_at' => $startDate->toDateString(),
            'auto_renew' => $request->boolean('auto_renew'),
            'cancel_at_period_end' => $request->boolean('cancel_at_period_end'),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($subscription->status === 'active' && $startDate->lessThanOrEqualTo(Carbon::today())) {
            app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.subscriptions.edit', $subscription),
                'Subscription created.',
            );
        }

        return redirect()->route('admin.subscriptions.edit', $subscription)
            ->with('status', 'Subscription created.');
    }

    public function edit(Request $request, Subscription $subscription): InertiaResponse
    {
        $subscription = $subscription->load(['customer', 'plan.product']);
        $customers = Customer::query()->orderBy('name')->get();
        $plans = Plan::query()->with('product')->orderBy('name')->get();
        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/Subscriptions/Form',
            $this->formInertiaProps($subscription, $customers, $plans, $salesReps)
        );
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', Rule::in(['active', 'cancelled', 'suspended'])],
            'current_period_start' => ['required', 'date'],
            'current_period_end' => ['required', 'date', 'after:current_period_start'],
            'next_invoice_at' => ['required', 'date'],
            'access_override_until' => ['nullable', 'date'],
            'auto_renew' => ['nullable', 'boolean'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
            'cancelled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
        ]);

        $subscription->update([
            'customer_id' => $data['customer_id'],
            'plan_id' => $data['plan_id'],
            'sales_rep_id' => $data['sales_rep_id'] ?? null,
            'status' => $data['status'],
            'current_period_start' => $data['current_period_start'],
            'current_period_end' => $data['current_period_end'],
            'next_invoice_at' => $data['next_invoice_at'],
            'auto_renew' => $request->boolean('auto_renew'),
            'cancel_at_period_end' => $request->boolean('cancel_at_period_end'),
            'cancelled_at' => $data['cancelled_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (array_key_exists('access_override_until', $data)) {
            Customer::query()
                ->whereKey($data['customer_id'])
                ->update(['access_override_until' => $data['access_override_until'] ?? null]);
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.subscriptions.edit', $subscription),
                'Subscription updated.',
            );
        }

        return redirect()->route('admin.subscriptions.edit', $subscription)
            ->with('status', 'Subscription updated.');
    }

    public function destroy(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $subscription->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.subscriptions.index'),
                'Subscription deleted.',
            );
        }

        return redirect()->route('admin.subscriptions.index')
            ->with('status', 'Subscription deleted.');
    }

    private function indexPayload(Request $request): array
    {
        $search = trim((string) $request->input('search', ''));

        $subscriptions = Subscription::query()
            ->with(['customer', 'plan.product', 'latestOrder'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('status', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('plan', function ($planQuery) use ($search) {
                            $planQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('product', function ($productQuery) use ($search) {
                                    $productQuery->where('name', 'like', '%'.$search.'%');
                                });
                        });

                    if (is_numeric($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $accessBlockService = app(AccessBlockService::class);
        $accessBlockedCustomers = [];

        foreach ($subscriptions as $subscription) {
            $customer = $subscription->customer;
            $customerId = $customer?->id;
            if (! $customerId || array_key_exists($customerId, $accessBlockedCustomers)) {
                continue;
            }

            $accessBlockedCustomers[$customerId] = $accessBlockService->isCustomerBlocked($customer);
        }

        return [
            'subscriptions' => $subscriptions,
            'accessBlockedCustomers' => $accessBlockedCustomers,
            'search' => $search,
        ];
    }

    private function indexInertiaProps(
        LengthAwarePaginator $subscriptions,
        string $search
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Subscriptions',
            'search' => $search,
            'routes' => [
                'index' => route('admin.subscriptions.index'),
                'create' => route('admin.subscriptions.create'),
            ],
            'subscriptions' => collect($subscriptions->items())->values()->map(function (Subscription $subscription) use ($dateFormat) {
                $customer = $subscription->customer;
                $plan = $subscription->plan;
                $planPrice = $plan?->price;
                $planCurrency = $plan?->currency;
                $planInterval = $plan?->interval;

                return [
                    'id' => $subscription->id,
                    'customer_name' => (string) ($customer?->name ?? '--'),
                    'customer_url' => $customer ? route('admin.customers.show', $customer) : null,
                    'product_plan' => (string) (($plan?->product?->name ?? '--').' - '.($plan?->name ?? '--')),
                    'amount_display' => $planPrice !== null
                        ? trim((string) (($planCurrency ? $planCurrency.' ' : '').number_format((float) $planPrice, 2)))
                        : '--',
                    'interval_label' => $planInterval ? ucfirst((string) $planInterval) : '--',
                    'status' => (string) $subscription->status,
                    'status_label' => ucfirst((string) $subscription->status),
                    'next_invoice_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                    'routes' => [
                        'edit' => route('admin.subscriptions.edit', $subscription),
                        'destroy' => route('admin.subscriptions.destroy', $subscription),
                    ],
                ];
            })->all(),
            'pagination' => [
                'has_pages' => $subscriptions->hasPages(),
                'previous_url' => $subscriptions->previousPageUrl(),
                'next_url' => $subscriptions->nextPageUrl(),
            ],
        ];
    }

    private function formInertiaProps(
        ?Subscription $subscription,
        Collection $customers,
        Collection $plans,
        Collection $salesReps
    ): array {
        $isEdit = $subscription !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Subscription' : 'Add Subscription',
            'is_edit' => $isEdit,
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => (string) $customer->name,
            ])->values()->all(),
            'plans' => $plans->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => (string) $plan->name,
                'product_name' => (string) ($plan->product?->name ?? '--'),
                'interval' => (string) $plan->interval,
                'price' => (float) $plan->price,
                'currency' => (string) ($plan->currency ?? ''),
            ])->values()->all(),
            'sales_reps' => $salesReps->map(fn (SalesRepresentative $rep) => [
                'id' => $rep->id,
                'name' => (string) $rep->name,
                'status' => (string) $rep->status,
            ])->values()->all(),
            'form' => [
                'action' => $isEdit
                    ? route('admin.subscriptions.update', $subscription)
                    : route('admin.subscriptions.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'customer_id' => (string) old('customer_id', (string) ($subscription?->customer_id ?? '')),
                    'plan_id' => (string) old('plan_id', (string) ($subscription?->plan_id ?? '')),
                    'sales_rep_id' => (string) old('sales_rep_id', (string) ($subscription?->sales_rep_id ?? '')),
                    'status' => (string) old('status', (string) ($subscription?->status ?? 'active')),
                    'start_date' => (string) old('start_date', (string) ($subscription?->start_date?->toDateString() ?? now()->toDateString())),
                    'current_period_start' => (string) old('current_period_start', (string) ($subscription?->current_period_start?->toDateString() ?? '')),
                    'current_period_end' => (string) old('current_period_end', (string) ($subscription?->current_period_end?->toDateString() ?? '')),
                    'next_invoice_at' => (string) old('next_invoice_at', (string) ($subscription?->next_invoice_at?->toDateString() ?? '')),
                    'access_override_until' => (string) old('access_override_until', (string) ($subscription?->customer?->access_override_until?->toDateString() ?? '')),
                    'cancelled_at' => (string) old('cancelled_at', (string) ($subscription?->cancelled_at?->toDateString() ?? '')),
                    'auto_renew' => (bool) old('auto_renew', (bool) ($subscription?->auto_renew ?? false)),
                    'cancel_at_period_end' => (bool) old('cancel_at_period_end', (bool) ($subscription?->cancel_at_period_end ?? false)),
                    'notes' => (string) old('notes', (string) ($subscription?->notes ?? '')),
                ],
            ],
            'routes' => [
                'index' => route('admin.subscriptions.index'),
            ],
        ];
    }
}
