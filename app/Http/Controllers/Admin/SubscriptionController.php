<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SalesRepresentative;
use App\Services\AccessBlockService;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $subscriptions = Subscription::query()
            ->with(['customer', 'plan.product', 'latestOrder'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('status', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('plan', function ($planQuery) use ($search) {
                            $planQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('product', function ($productQuery) use ($search) {
                                    $productQuery->where('name', 'like', '%' . $search . '%');
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

        $payload = [
            'subscriptions' => $subscriptions,
            'accessBlockedCustomers' => $accessBlockedCustomers,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.subscriptions.partials.table', $payload);
        }

        return view('admin.subscriptions.index', $payload);
    }

    public function create()
    {
        return view('admin.subscriptions.create', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'plans' => Plan::query()->with('product')->orderBy('name')->get(),
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function store(Request $request)
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

        return redirect()->route('admin.subscriptions.edit', $subscription)
            ->with('status', 'Subscription created.');
    }

    public function edit(Subscription $subscription)
    {
        return view('admin.subscriptions.edit', [
            'subscription' => $subscription->load(['customer', 'plan.product']),
            'customers' => Customer::query()->orderBy('name')->get(),
            'plans' => Plan::query()->with('product')->orderBy('name')->get(),
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function update(Request $request, Subscription $subscription)
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

        return redirect()->route('admin.subscriptions.edit', $subscription)
            ->with('status', 'Subscription updated.');
    }

    public function destroy(Subscription $subscription)
    {
        $subscription->delete();

        return redirect()->route('admin.subscriptions.index')
            ->with('status', 'Subscription deleted.');
    }
}
