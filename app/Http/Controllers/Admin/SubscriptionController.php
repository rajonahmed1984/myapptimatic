<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\MyBuildingProvision;
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
            'subscription_amount' => ['nullable', 'numeric', 'min:0'],
            'sales_rep_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:200'],
            'contracted_flats' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'install_url' => ['nullable', 'string', 'max:255'],
            'district_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $startDate = Carbon::parse($data['start_date']);
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();
        $nextInvoiceAt = $startDate->copy();
        $baseAmount = array_key_exists('subscription_amount', $data) && $data['subscription_amount'] !== null
            ? (float) $data['subscription_amount']
            : (float) $plan->price;
        $salesRepId = $data['sales_rep_id'] ?? null;
        $commissionAmount = $salesRepId
            ? $this->resolveCommissionAmount(
                $data['sales_rep_commission_percent'] ?? null,
                $baseAmount
            )
            : null;

        $subscription = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $startDate, $periodEnd, $nextInvoiceAt, $baseAmount, $salesRepId, $commissionAmount, $request, $plan) {
            $subscription = Subscription::create([
                'customer_id' => $data['customer_id'],
                'plan_id' => $data['plan_id'],
                'sales_rep_id' => $salesRepId,
                'subscription_amount' => $data['subscription_amount'] ?? null,
                'sales_rep_commission_amount' => $commissionAmount,
                'status' => $data['status'],
                'start_date' => $startDate->toDateString(),
                'current_period_start' => $startDate->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $nextInvoiceAt->toDateString(),
                'auto_renew' => $request->boolean('auto_renew'),
                'cancel_at_period_end' => $request->boolean('cancel_at_period_end'),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($plan->isPerFlat() && $plan->product_id) {
                $license = $subscription->licenses()->create([
                    'product_id' => $plan->product_id,
                    'license_key' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(32)),
                    'status' => 'active',
                    'starts_at' => $startDate->toDateString(),
                    'expires_at' => $periodEnd->toDateString(),
                ]);
                $customer = Customer::find($data['customer_id']);
                $totalFloors = (int) ($request->input('total_floors') ?: 10);
                $contractedFlats = (int) ($request->input('contracted_flats') ?: 40);
                $flatsPerFloor = (int) ceil($contractedFlats / max(1, $totalFloors));

                MyBuildingProvision::create([
                    'license_id' => $license->id,
                    'customer_id' => $customer?->id,
                    'building_name' => $request->input('building_name') ?: ($customer?->company_name ?: $customer?->name),
                    'building_address' => $request->input('building_address') ?: $customer?->address,
                    'total_floors' => $totalFloors,
                    'flats_per_floor' => $flatsPerFloor,
                    'contracted_flats' => $contractedFlats,
                    'district_id' => $request->filled('district_id') ? (int) $request->input('district_id') : null,
                    'city_id' => $request->filled('city_id') ? (int) $request->input('city_id') : null,
                    'area_id' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
                    'install_url' => (string) ($request->input('install_url') ?: config('mybuilding.default_install_url') ?: ''),
                    'owner_name' => $customer?->name ?: 'Owner',
                    'owner_email' => $customer?->email ?: 'owner@example.com',
                    'owner_phone' => $customer?->phone ?: '',
                ]);
            }

            return $subscription;
        });

        if ($subscription->status === 'active' && $startDate->lessThanOrEqualTo(Carbon::today())) {
            app(BillingService::class)->generateInvoiceForSubscription($subscription, Carbon::today());
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(route('admin.subscriptions.edit', $subscription), 'Subscription created.');
        }

        return redirect()->route('admin.subscriptions.edit', $subscription)
            ->with('status', 'Subscription created.');
    }

    public function edit(Request $request, Subscription $subscription): InertiaResponse
    {
        $subscription = $subscription->load([
            'customer',
            'plan.product',
            'licenses.product',
            'licenses.domains',
        ]);
        $customers = Customer::query()->orderBy('name')->get();
        $plans = Plan::query()->with('product')->orderBy('name')->get();
        $salesReps = SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']);

        return Inertia::render(
            'Admin/Subscriptions/Form',
            array_merge(
                $this->formInertiaProps($subscription, $customers, $plans, $salesReps),
                ['licenseManager' => $this->licenseManagerInertiaProps($subscription, old('license'))]
            )
        );
    }

    public function show(Request $request, Subscription $subscription): InertiaResponse
    {
        $subscription->load([
            'customer',
            'plan.product',
            'salesRep',
            'invoices' => function ($query) {
                $query->latest('issue_date');
            },
        ])->loadCount([
            'invoices as open_invoices_count' => function ($query) {
                $query->whereIn('status', ['unpaid', 'overdue']);
            },
            'invoices as overdue_invoices_count' => function ($query) {
                $query->where('status', 'overdue');
            },
        ]);

        $dateFormat = (string) config('app.date_format', 'd-m-Y');
        $baseAmount = $subscription->subscription_amount !== null
            ? (float) $subscription->subscription_amount
            : (float) ($subscription->plan?->price ?? 0);
        $amountCurrency = (string) ($subscription->plan?->currency ?? '');
        $subscriptionCommissionAmount = $subscription->sales_rep_commission_amount !== null
            ? round((float) $subscription->sales_rep_commission_amount, 2)
            : null;
        $commissionPercent = null;

        if ($subscriptionCommissionAmount !== null && $baseAmount > 0) {
            $commissionPercent = round(($subscriptionCommissionAmount / $baseAmount) * 100, 2);
        }

        return Inertia::render('Admin/Subscriptions/Show', [
            'pageTitle' => 'Subscription #'.$subscription->id,
            'subscription' => [
                'id' => $subscription->id,
                'status' => (string) ($subscription->status ?? ''),
                'status_label' => ucfirst((string) ($subscription->status ?? '--')),
                'customer_name' => (string) ($subscription->customer?->name ?? '--'),
                'customer_url' => $subscription->customer
                    ? route('admin.customers.show', $subscription->customer)
                    : null,
                'product_name' => (string) ($subscription->plan?->product?->name ?? '--'),
                'plan_name' => (string) ($subscription->plan?->name ?? '--'),
                'plan_interval' => ucfirst((string) ($subscription->plan?->interval ?? '--')),
                'amount_display' => trim((string) (($amountCurrency ? $amountCurrency.' ' : '').number_format($baseAmount, 2))),
                'sales_rep_name' => (string) ($subscription->salesRep?->name ?? '--'),
                'sales_rep_status' => (string) ($subscription->salesRep?->status ?? '--'),
                'sales_rep_commission_percent' => $commissionPercent !== null ? number_format($commissionPercent, 2).'%' : '--',
                'sales_rep_commission_amount_display' => $subscription->sales_rep_commission_amount !== null
                    ? trim((string) (($amountCurrency ? $amountCurrency.' ' : '').number_format((float) $subscription->sales_rep_commission_amount, 2)))
                    : '--',
                'start_date' => $subscription->start_date?->format($dateFormat) ?? '--',
                'current_period_start' => $subscription->current_period_start?->format($dateFormat) ?? '--',
                'current_period_end' => $subscription->current_period_end?->format($dateFormat) ?? '--',
                'next_invoice_at' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                'open_invoices_count' => (int) ($subscription->open_invoices_count ?? 0),
                'overdue_invoices_count' => (int) ($subscription->overdue_invoices_count ?? 0),
                'auto_renew' => (bool) $subscription->auto_renew,
                'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
                'notes' => (string) ($subscription->notes ?? ''),
                'created_at' => $subscription->created_at?->format($dateFormat) ?? '--',
            ],
            'invoices' => $subscription->invoices->map(function ($invoice) use ($dateFormat, $subscriptionCommissionAmount) {
                $invoiceCurrency = (string) ($invoice->currency ?? '');
                $commissionDisplay = $subscriptionCommissionAmount !== null
                    ? trim((string) (($invoiceCurrency ? $invoiceCurrency.' ' : '').number_format($subscriptionCommissionAmount, 2)))
                    : '--';

                return [
                    'id' => $invoice->id,
                    'number' => (string) ($invoice->number ?: $invoice->id),
                    'status' => (string) ($invoice->status ?? ''),
                    'status_label' => ucfirst((string) ($invoice->status ?? '--')),
                    'issue_date' => $invoice->issue_date?->format($dateFormat) ?? '--',
                    'due_date' => $invoice->due_date?->format($dateFormat) ?? '--',
                    'paid_date' => $invoice->paid_at?->format($dateFormat) ?? '--',
                    'total_display' => trim((string) (((string) ($invoice->currency ?? '') ? ((string) ($invoice->currency ?? '')).' ' : '').number_format((float) ($invoice->total ?? 0), 2))),
                    'commission_display' => $commissionDisplay,
                    'can_record_payment' => (string) ($invoice->status ?? '') !== 'paid',
                    'show_url' => route('admin.invoices.show', $invoice),
                    'edit_url' => route('admin.invoices.show', $invoice),
                    'payment_url' => route('admin.accounting.create', [
                        'type' => 'payment',
                        'invoice_id' => $invoice->id,
                        'scope' => 'ledger',
                    ]),
                    'destroy_url' => route('admin.invoices.destroy', $invoice),
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('admin.subscriptions.index'),
                'edit' => route('admin.subscriptions.edit', $subscription),
                'move_owner' => route('admin.subscriptions.move-owner', $subscription),
                'renew_now' => route('admin.subscriptions.renew-now', $subscription),
                'change_plan' => route('admin.subscriptions.change-plan', $subscription),
                'transfer_store' => route('admin.subscriptions.transfers.store', $subscription),
            ],
            'transfer_ineligible_reason' => app(\App\Services\ProjectTransferService::class)->eligibilityErrorForSubscription($subscription),
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Plan $plan) => ['id' => (string) $plan->id, 'name' => (string) $plan->name])
                ->all(),
            'customers' => Customer::query()
                ->where('id', '!=', $subscription->customer_id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($c) => [
                    'id' => (string) $c->id,
                    'name' => (string) $c->name,
                ])
                ->all(),
            'related_counts' => [
                'projects' => (int) \App\Models\Project::where('subscription_id', $subscription->id)->count(),
                'orders' => (int) \App\Models\Order::where('subscription_id', $subscription->id)->count(),
                'invoices' => (int) \App\Models\Invoice::where('subscription_id', $subscription->id)->count(),
            ],
        ]);
    }

    public function moveOwner(
        Request $request,
        Subscription $subscription,
        \App\Services\OwnershipMoveService $moveService
    ): RedirectResponse {
        $data = $request->validate([
            'customer_id' => [
                'required',
                'exists:customers,id',
                Rule::notIn([$subscription->customer_id]),
            ],
            'move_projects' => ['nullable', 'boolean'],
            'move_orders' => ['nullable', 'boolean'],
            'move_invoices' => ['nullable', 'boolean'],
        ]);

        $target = \App\Models\Customer::findOrFail((int) $data['customer_id']);

        $moved = $moveService->moveSubscription(
            $subscription,
            $target,
            [
                'projects' => (bool) ($data['move_projects'] ?? false),
                'orders' => (bool) ($data['move_orders'] ?? false),
                'invoices' => (bool) ($data['move_invoices'] ?? false),
            ],
            $request->user()?->id
        );

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('status', sprintf(
                'Subscription moved to %s (%d license(s), %d project(s), %d invoice(s), %d order(s)).',
                $target->name,
                (int) ($moved['licenses'] ?? 0),
                (int) ($moved['projects'] ?? 0),
                (int) ($moved['invoices'] ?? 0),
                (int) ($moved['orders'] ?? 0)
            ));
    }

    public function renewNow(Request $request, Subscription $subscription, BillingService $billingService): RedirectResponse
    {
        if (! in_array((string) $subscription->status, ['active', 'suspended'], true)) {
            return redirect()->route('admin.subscriptions.show', $subscription)
                ->with('error', 'Only active or suspended subscriptions can be renewed.');
        }

        $invoice = $billingService->generateInvoiceForSubscription($subscription, Carbon::today());

        if (! $invoice) {
            return redirect()->route('admin.subscriptions.show', $subscription)
                ->with('status', 'No new invoice was needed — the current period is already billed.');
        }

        \App\Support\SystemLogger::write('activity', 'Subscription manually renewed.', [
            'subscription_id' => $subscription->id,
            'invoice_id' => $invoice->id,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('status', 'Invoice #'.($invoice->number ?: $invoice->id).' generated.');
    }

    public function changePlan(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id', Rule::notIn([$subscription->plan_id])],
            'subscription_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $oldPlan = $subscription->plan;
        $newPlan = Plan::findOrFail($data['plan_id']);
        $newAmount = array_key_exists('subscription_amount', $data) && $data['subscription_amount'] !== null
            ? (float) $data['subscription_amount']
            : (float) $newPlan->price;

        \Illuminate\Support\Facades\DB::transaction(function () use ($subscription, $newPlan, $newAmount) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'subscription_amount' => $newAmount,
            ]);
        });

        \App\Models\StatusAuditLog::logChange(
            Subscription::class,
            $subscription->id,
            (string) ($oldPlan?->name ?? 'unknown'),
            (string) $newPlan->name,
            'plan_change',
            $request->user()?->id,
            [
                'old_plan_id' => $oldPlan?->id,
                'new_plan_id' => $newPlan->id,
                'new_amount' => $newAmount,
            ]
        );

        return redirect()->route('admin.subscriptions.show', $subscription)
            ->with('status', 'Plan changed to '.$newPlan->name.'.');
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'status' => ['required', Rule::in(['active', 'cancelled', 'suspended'])],
            'start_date' => ['required', 'date'],
            'current_period_start' => ['required', 'date'],
            'current_period_end' => ['required', 'date', 'after:current_period_start'],
            'next_invoice_at' => ['required', 'date'],
            'access_override_until' => ['nullable', 'date'],
            'auto_renew' => ['nullable', 'boolean'],
            'cancel_at_period_end' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
            'subscription_amount' => ['nullable', 'numeric', 'min:0'],
            'sales_rep_commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:200'],
            'contracted_flats' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'install_url' => ['nullable', 'string', 'max:255'],
            'district_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $baseAmount = array_key_exists('subscription_amount', $data) && $data['subscription_amount'] !== null
            ? (float) $data['subscription_amount']
            : (float) $plan->price;
        $salesRepId = $data['sales_rep_id'] ?? null;
        $commissionAmount = $salesRepId
            ? $this->resolveCommissionAmount(
                $data['sales_rep_commission_percent'] ?? null,
                $baseAmount
            )
            : null;

        \Illuminate\Support\Facades\DB::transaction(function () use ($subscription, $data, $commissionAmount, $request, $plan) {
            $subscription->update([
                'customer_id' => $data['customer_id'],
                'plan_id' => $data['plan_id'],
                'sales_rep_id' => $data['sales_rep_id'] ?? null,
                'subscription_amount' => $data['subscription_amount'] ?? null,
                'sales_rep_commission_amount' => $commissionAmount,
                'status' => $data['status'],
                'start_date' => \App\Support\DateTimeFormat::parseDate($data['start_date'])?->toDateString(),
                'current_period_start' => \App\Support\DateTimeFormat::parseDate($data['current_period_start'])?->toDateString(),
                'current_period_end' => \App\Support\DateTimeFormat::parseDate($data['current_period_end'])?->toDateString(),
                'next_invoice_at' => \App\Support\DateTimeFormat::parseDate($data['next_invoice_at'])?->toDateString(),
                'auto_renew' => $request->boolean('auto_renew'),
                'cancel_at_period_end' => $request->boolean('cancel_at_period_end'),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($plan->isPerFlat()) {
                $license = $subscription->licenses()->first();
                if (! $license && $subscription->plan?->product_id) {
                    $license = $subscription->licenses()->create([
                        'product_id' => $subscription->plan->product_id,
                        'license_key' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(32)),
                        'status' => 'active',
                        'starts_at' => $subscription->start_date ?? now(),
                        'expires_at' => $subscription->current_period_end,
                    ]);
                }
                $customer = Customer::find($data['customer_id']);
                $totalFloors = (int) ($request->input('total_floors') ?: 10);
                $contractedFlats = (int) ($request->input('contracted_flats') ?: 40);
                $flatsPerFloor = (int) ceil($contractedFlats / max(1, $totalFloors));

                if ($license) {
                    MyBuildingProvision::updateOrCreate(
                        ['license_id' => $license->id],
                        [
                            'customer_id' => $customer?->id,
                            'building_name' => $request->input('building_name') ?: ($customer?->company_name ?: $customer?->name),
                            'building_address' => $request->input('building_address') ?: $customer?->address,
                            'total_floors' => $totalFloors,
                            'flats_per_floor' => $flatsPerFloor,
                            'contracted_flats' => $contractedFlats,
                            'district_id' => $request->filled('district_id') ? (int) $request->input('district_id') : null,
                            'city_id' => $request->filled('city_id') ? (int) $request->input('city_id') : null,
                            'area_id' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
                            'install_url' => (string) ($request->input('install_url') ?: config('mybuilding.default_install_url') ?: ''),
                            'owner_name' => $customer?->name ?: 'Owner',
                            'owner_email' => $customer?->email ?: 'owner@example.com',
                            'owner_phone' => $customer?->phone ?: '',
                        ]
                    );
                }
            }

            if (array_key_exists('access_override_until', $data)) {
                $overrideDate = \App\Support\DateTimeFormat::parseDate($data['access_override_until']);
                Customer::query()
                    ->whereKey($data['customer_id'])
                    ->update(['access_override_until' => $overrideDate ? $overrideDate->endOfDay() : null]);
            }
        });

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(route('admin.subscriptions.edit', $subscription), 'Subscription updated.');
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
            ->withCount([
                'invoices as open_invoices_count' => function ($query) {
                    $query->whereIn('status', ['unpaid', 'overdue']);
                },
                'invoices as overdue_invoices_count' => function ($query) {
                    $query->where('status', 'overdue');
                },
            ])
            ->withSum([
                'invoices as open_invoices_total' => function ($query) {
                    $query->whereIn('status', ['unpaid', 'overdue']);
                }
            ], 'total')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('status', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where(function ($customerSearch) use ($search) {
                                $customerSearch->where('name', 'like', '%'.$search.'%')
                                    ->orWhere('company_name', 'like', '%'.$search.'%');
                            });
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
            ->paginate(30)
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
                $planPrice = $subscription->subscription_amount ?? $plan?->price;
                $planCurrency = $plan?->currency;
                $planInterval = $plan?->interval;

                return [
                    'id' => $subscription->id,
                    'customer_name' => (string) ($customer?->name ?? '--'),
                    'customer_company_name' => (string) ($customer?->company_name ?? ''),
                    'customer_url' => $customer ? route('admin.customers.show', $customer) : null,
                    'product_plan' => (string) (($plan?->product?->name ?? '--').' - '.($plan?->name ?? '--')),
                    'amount_display' => $planPrice !== null
                        ? trim((string) (($planCurrency ? $planCurrency.' ' : '').number_format((float) $planPrice, 2)))
                        : '--',
                    'interval_label' => $planInterval ? ucfirst((string) $planInterval) : '--',
                    'status' => (string) $subscription->status,
                    'status_label' => ucfirst((string) $subscription->status),
                    'next_invoice_display' => $subscription->next_invoice_at?->format($dateFormat) ?? '--',
                    'open_invoices_count' => (int) ($subscription->open_invoices_count ?? 0),
                    'overdue_invoices_count' => (int) ($subscription->overdue_invoices_count ?? 0),
                    'open_invoices_total_display' => $subscription->open_invoices_total !== null && (float)$subscription->open_invoices_total > 0
                        ? trim((string) (($planCurrency ? $planCurrency.' ' : '').number_format((float) $subscription->open_invoices_total, 2)))
                        : null,
                    'routes' => [
                        'show' => route('admin.subscriptions.show', $subscription),
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
        $effectiveSubscriptionAmount = $subscription
            ? (float) ($subscription->subscription_amount ?? $subscription->plan?->price ?? 0)
            : 0.0;
        $commissionPercent = null;
        if (
            $subscription
            && $subscription->sales_rep_commission_amount !== null
            && $effectiveSubscriptionAmount > 0
        ) {
            $commissionPercent = round((((float) $subscription->sales_rep_commission_amount) / $effectiveSubscriptionAmount) * 100, 2);
        }

        $provision = null;
        if ($subscription) {
            $licenseIds = $subscription->licenses->pluck('id');
            $provisionModel = MyBuildingProvision::query()
                ->whereIn('license_id', $licenseIds)
                ->orWhere('customer_id', $subscription->customer_id)
                ->first();

            if ($provisionModel) {
                $provision = [
                    'id' => $provisionModel->id,
                    'building_name' => $provisionModel->building_name,
                    'building_address' => $provisionModel->building_address,
                    'total_floors' => $provisionModel->total_floors,
                    'flats_per_floor' => $provisionModel->flats_per_floor,
                    'contracted_flats' => $provisionModel->contracted_flats,
                    'status' => $provisionModel->status,
                    'remote_building_id' => $provisionModel->remote_building_id,
                    'registration_code' => $provisionModel->registration_code,
                    'last_error' => $provisionModel->last_error,
                    'install_url' => $provisionModel->install_url,
                    'district_id' => $provisionModel->district_id,
                    'city_id' => $provisionModel->city_id,
                    'area_id' => $provisionModel->area_id,
                    'routes' => [
                        'provision' => route('admin.mybuilding.provision', $provisionModel),
                    ],
                ];
            }
        }

        return [
            'pageTitle' => $isEdit ? 'Edit Subscription' : 'Add Subscription',
            'is_edit' => $isEdit,
            'provision' => $provision,
            'secret_configured' => !empty(config('mybuilding.provision_secret')),
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => (string) $customer->name,
                'company_name' => (string) ($customer->company_name ?? ''),
            ])->values()->all(),
            'plans' => $plans->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => (string) $plan->name,
                'product_name' => (string) ($plan->product?->name ?? '--'),
                'product_slug' => (string) ($plan->product?->slug ?? ''),
                'interval' => (string) $plan->interval,
                'price' => (float) $plan->price,
                'currency' => (string) ($plan->currency ?? ''),
                'pricing_model' => (string) ($plan->pricing_model ?? 'fixed'),
                'is_per_flat' => $plan->isPerFlat(),
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
                    'subscription_amount' => (string) old('subscription_amount', (string) ($subscription?->subscription_amount ?? '')),
                    'sales_rep_commission_percent' => (string) old('sales_rep_commission_percent', (string) ($commissionPercent ?? '')),
                    'building_name' => (string) old('building_name', (string) ($provision['building_name'] ?? ($subscription?->customer?->company_name ?: ($subscription?->customer?->name ?: '')))),
                    'building_address' => (string) old('building_address', (string) ($provision['building_address'] ?? ($subscription?->customer?->address ?: ''))),
                    'total_floors' => (string) old('total_floors', (string) ($provision['total_floors'] ?? 10)),
                    'contracted_flats' => (string) old('contracted_flats', (string) ($provision['contracted_flats'] ?? ($effectiveSubscriptionAmount > 0 && ($subscription?->plan?->price ?? 0) > 0 ? (int) round($effectiveSubscriptionAmount / (float) $subscription->plan->price) : 40))),
                    'install_url' => (string) old('install_url', (string) ($provision['install_url'] ?? config('mybuilding.default_install_url') ?? '')),
                    'status' => (string) old('status', (string) ($subscription?->status ?? 'active')),
                    'start_date' => (string) old('start_date', (string) ($subscription?->start_date?->format('d-m-Y') ?? now()->format('d-m-Y'))),
                    'current_period_start' => (string) old('current_period_start', (string) ($subscription?->current_period_start?->format('d-m-Y') ?? '')),
                    'current_period_end' => (string) old('current_period_end', (string) ($subscription?->current_period_end?->format('d-m-Y') ?? '')),
                    'next_invoice_at' => (string) old('next_invoice_at', (string) ($subscription?->next_invoice_at?->format('d-m-Y') ?? '')),
                    'access_override_until' => (string) old('access_override_until', (string) ($subscription?->customer?->access_override_until?->format('d-m-Y') ?? '')),
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

    private function licenseManagerInertiaProps(?Subscription $subscription, mixed $oldLicenseInput): array
    {
        if (! $subscription) {
            return [
                'product' => null,
                'create' => null,
                'licenses' => [],
            ];
        }

        $product = $subscription->plan?->product;
        $oldLicense = is_array($oldLicenseInput) ? $oldLicenseInput : [];
        $oldMode = (string) ($oldLicense['mode'] ?? '');
        $oldLicenseId = (string) ($oldLicense['id'] ?? '');
        $defaultStartDate = $subscription->current_period_start?->toDateString()
            ?? $subscription->start_date?->toDateString()
            ?? now()->toDateString();
        $defaultExpiryDate = $subscription->current_period_end?->toDateString() ?? '';
        $subscriptionLabel = sprintf(
            '#%d - %s%s',
            $subscription->id,
            (string) ($subscription->customer?->name ?? 'Unknown customer'),
            $subscription->plan?->name ? ' ('.$subscription->plan->name.')' : ''
        );

        // Candidate destinations for "move license". Any live subscription other
        // than this one qualifies — that is how a license changes owner without
        // moving the whole subscription.
        $moveTargets = Subscription::query()
            ->with(['customer:id,name', 'plan:id,name'])
            ->whereKeyNot($subscription->id)
            ->whereIn('status', ['active', 'suspended'])
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'customer_id', 'plan_id'])
            ->map(fn (Subscription $option) => [
                'value' => (string) $option->id,
                'label' => sprintf(
                    '#%d - %s%s',
                    $option->id,
                    (string) ($option->customer?->name ?? 'Unknown customer'),
                    $option->plan?->name ? ' ('.$option->plan->name.')' : ''
                ),
            ])
            ->values()
            ->all();

        return [
            'product' => $product ? [
                'id' => $product->id,
                'name' => (string) $product->name,
            ] : null,
            'subscription' => [
                'id' => $subscription->id,
                'label' => $subscriptionLabel,
            ],
            'move_targets' => $moveTargets,
            'create' => [
                'enabled' => $product !== null,
                'error_bag' => 'licenseCreate',
                'subscription_label' => $subscriptionLabel,
                'product_name' => (string) ($product?->name ?? ''),
                'fields' => [
                    'subscription_id' => (string) ($oldLicense['subscription_id'] ?? $subscription->id),
                    'product_id' => (string) ($oldLicense['product_id'] ?? ($product?->id ?? '')),
                    'license_key' => (string) ($oldLicense['license_key'] ?? ''),
                    'status' => (string) ($oldLicense['status'] ?? 'active'),
                    'starts_at' => (string) ($oldLicense['starts_at'] ?? $defaultStartDate),
                    'expires_at' => (string) ($oldLicense['expires_at'] ?? $defaultExpiryDate),
                    'auto_suspend_override_until' => (string) ($oldLicense['auto_suspend_override_until'] ?? ''),
                    'allowed_domains' => (string) ($oldLicense['allowed_domains'] ?? ''),
                    'notes' => (string) ($oldLicense['notes'] ?? ''),
                ],
                'form' => [
                    'action' => route('admin.licenses.store'),
                    'method' => 'POST',
                ],
            ],
            'licenses' => $subscription->licenses
                ->sortByDesc('id')
                ->values()
                ->map(function (License $license) use ($oldLicense, $oldLicenseId, $oldMode, $subscriptionLabel) {
                    $activeDomain = $license->domains->firstWhere('status', 'active')?->domain
                        ?? $license->domains->first()?->domain;
                    $useOldValues = $oldMode === 'edit' && $oldLicenseId !== '' && $oldLicenseId === (string) $license->id;

                    return [
                        'id' => $license->id,
                        'subscription_label' => $subscriptionLabel,
                        'product_name' => (string) ($license->product?->name ?? $license->subscription?->plan?->product?->name ?? 'Product'),
                        'error_bag' => 'licenseEdit'.$license->id,
                        'fields' => [
                            'subscription_id' => (string) ($useOldValues ? ($oldLicense['subscription_id'] ?? $license->subscription_id) : $license->subscription_id),
                            'product_id' => (string) ($useOldValues ? ($oldLicense['product_id'] ?? $license->product_id) : $license->product_id),
                            'license_key' => (string) ($useOldValues ? ($oldLicense['license_key'] ?? $license->license_key) : $license->license_key),
                            'status' => (string) ($useOldValues ? ($oldLicense['status'] ?? $license->status) : $license->status),
                            'starts_at' => (string) ($useOldValues ? ($oldLicense['starts_at'] ?? $license->starts_at?->toDateString()) : ($license->starts_at?->toDateString() ?? '')),
                            'expires_at' => (string) ($useOldValues ? ($oldLicense['expires_at'] ?? $license->expires_at?->toDateString()) : ($license->expires_at?->toDateString() ?? '')),
                            'auto_suspend_override_until' => (string) ($useOldValues
                                ? ($oldLicense['auto_suspend_override_until'] ?? $license->auto_suspend_override_until?->toDateString())
                                : ($license->auto_suspend_override_until?->toDateString() ?? '')),
                            'allowed_domains' => (string) ($useOldValues ? ($oldLicense['allowed_domains'] ?? $activeDomain) : ($activeDomain ?? '')),
                            'notes' => (string) ($useOldValues ? ($oldLicense['notes'] ?? $license->notes) : ($license->notes ?? '')),
                        ],
                        'form' => [
                            'action' => route('admin.licenses.update', $license),
                            'method' => 'PUT',
                        ],
                        'routes' => [
                            'suspend' => route('admin.licenses.suspend', $license),
                            'unsuspend' => route('admin.licenses.unsuspend', $license),
                            'reactivate' => route('admin.licenses.reactivate', $license),
                            'terminate' => route('admin.licenses.terminate', $license),
                            'move' => route('admin.licenses.move', $license),
                            'reissue_key' => route('admin.licenses.reissue-key', $license),
                            'certificate_issue' => route('admin.licenses.certificate.issue', $license),
                            'certificate_revoke' => $license->certificates->firstWhere('status', 'active')
                                ? route('admin.licenses.certificate.revoke', [$license, $license->certificates->firstWhere('status', 'active')])
                                : null,
                        ],
                        'certificate' => optional($license->certificates->firstWhere('status', 'active'), fn ($cert) => [
                            'cert_uuid' => $cert->cert_uuid,
                            'issued_at' => $cert->issued_at?->format('d-m-Y H:i'),
                        ]),
                        'domains' => $license->domains->map(function (LicenseDomain $domain) use ($license) {
                            return [
                                'id' => $domain->id,
                                'domain' => (string) $domain->domain,
                                'status' => (string) $domain->status,
                                'status_label' => ucfirst((string) $domain->status),
                                'can_revoke' => $domain->status !== 'revoked',
                                'routes' => [
                                    'revoke' => route('admin.licenses.domains.revoke', [$license, $domain]),
                                ],
                            ];
                        })->values()->all(),
                    ];
                })->all(),
        ];
    }

    private function resolveCommissionAmount(mixed $commissionPercent, float $baseAmount): ?float
    {
        if ($commissionPercent === null || $commissionPercent === '') {
            return null;
        }

        $percent = (float) $commissionPercent;
        if ($percent <= 0 || $baseAmount <= 0) {
            return null;
        }

        return round(($baseAmount * $percent) / 100, 2);
    }

}
