<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Order;
use App\Models\Plan;
use App\Services\AdminNotificationService;
use App\Services\BillingService;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrderController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $status = $request->query('status');
        $allowed = ['pending', 'accepted', 'cancelled'];

        $ordersQuery = Order::query()
            ->with(['customer', 'plan.product', 'invoice'])
            ->latest();

        if ($status && in_array($status, $allowed, true)) {
            $ordersQuery->where('status', $status);
        }

        $orders = $ordersQuery->paginate(25);

        return Inertia::render(
            'Admin/Orders/Index',
            $this->indexInertiaProps($orders, $status)
        );
    }

    public function show(Order $order): InertiaResponse
    {
        $order->load([
            'customer',
            'plan.product.plans',
            'invoice',
            'subscription.licenses.domains',
            'approver',
            'canceller',
        ]);

        $planOptions = $order->plan?->product?->plans?->where('is_active', true) ?? collect();
        $intervalOptions = $planOptions
            ->pluck('interval')
            ->filter()
            ->unique()
            ->values();

        return Inertia::render(
            'Admin/Orders/Show',
            $this->showInertiaProps($order, $planOptions, $intervalOptions)
        );
    }

    public function approve(
        Request $request,
        Order $order,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        if ($order->status !== 'pending') {
            return back()->with('status', 'Order already processed.');
        }

        $subscription = $order->subscription;
        if (! $subscription) {
            return back()->withErrors(['license_key' => 'No subscription found for this order.']);
        }

        $license = $subscription->licenses()->first();

        $data = $request->validate([
            'license_key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('licenses', 'license_key')->ignore($license?->id),
            ],
            'license_url' => ['required', 'string', 'max:255'],
        ]);

        $domain = $this->normalizeDomain($data['license_url']);
        if (! $domain) {
            return back()->withErrors(['license_url' => 'Invalid URL format. Use a valid domain or URL.']);
        }

        $order->update([
            'status' => 'accepted',
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
        ]);

        $subscription->update([
            'status' => 'active',
        ]);

        if (! $license) {
            $license = License::create([
                'subscription_id' => $subscription->id,
                'product_id' => $order->product_id ?? $subscription->plan?->product_id,
                'license_key' => $data['license_key'],
                'status' => 'active',
                'starts_at' => $subscription->start_date ?? Carbon::today()->toDateString(),
                'expires_at' => $subscription->current_period_end,
                'max_domains' => 1,
            ]);
        } else {
            $license->update([
                'license_key' => $data['license_key'],
                'status' => 'active',
            ]);
        }

        LicenseDomain::updateOrCreate(
            [
                'license_id' => $license->id,
                'domain' => $domain,
            ],
            [
                'status' => 'active',
                'verified_at' => Carbon::now(),
            ]
        );

        $license->domains()
            ->where('domain', '!=', $domain)
            ->update(['status' => 'revoked']);

        $clientNotifications->sendOrderAccepted($order);
        $adminNotifications->sendOrderAccepted($order);

        SystemLogger::write('activity', 'Order approved.', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'subscription_id' => $subscription->id,
            'invoice_id' => $order->invoice_id,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Order accepted.');
    }

    public function cancel(Order $order, AdminNotificationService $adminNotifications): RedirectResponse
    {
        if ($order->status !== 'pending') {
            return back()->with('status', 'Order already processed.');
        }

        $order->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => Carbon::now(),
        ]);

        if ($order->invoice) {
            $order->invoice->update([
                'status' => 'cancelled',
            ]);
        }

        if ($order->subscription) {
            $order->subscription->update([
                'status' => 'cancelled',
                'auto_renew' => false,
                'cancelled_at' => Carbon::now(),
            ]);

            $order->subscription->licenses()
                ->whereIn('status', ['active', 'pending', 'suspended'])
                ->update(['status' => 'revoked']);
        }

        $adminNotifications->sendOrderCancelled($order->fresh(['customer', 'plan.product', 'invoice']));

        SystemLogger::write('activity', 'Order cancelled.', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'subscription_id' => $order->subscription_id,
            'invoice_id' => $order->invoice_id,
        ]);

        return back()->with('status', 'Order cancelled.');
    }

    public function updatePlan(Request $request, Order $order, BillingService $billingService): RedirectResponse
    {
        if (! in_array($order->status, ['pending', 'accepted'], true)) {
            return back()->with('status', 'Only pending or accepted orders can change interval.');
        }

        $data = $request->validate([
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'interval' => ['nullable', Rule::in(['monthly', 'yearly'])],
        ]);

        if (empty($data['plan_id']) && empty($data['interval'])) {
            return back()->withErrors(['interval' => 'Select an interval or plan.']);
        }

        $plan = null;

        if (! empty($data['plan_id'])) {
            $plan = Plan::query()->with('product')->findOrFail($data['plan_id']);
        }

        if (! $plan && ! empty($data['interval'])) {
            $planQuery = Plan::query()
                ->with('product')
                ->where('product_id', $order->product_id)
                ->where('interval', $data['interval'])
                ->where('is_active', true);

            if ($order->plan?->name) {
                $plan = (clone $planQuery)->where('name', $order->plan->name)->first();
            }

            $plan ??= $planQuery->orderBy('price')->first();
        }

        if (! $plan) {
            return back()->withErrors(['interval' => 'No matching plan found for this interval.']);
        }

        if ($order->product_id && $plan->product_id !== $order->product_id) {
            return back()->withErrors(['plan_id' => 'Selected plan does not match the order product.']);
        }

        $order->update([
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
        ]);

        if ($order->subscription) {
            $subscription = $order->subscription;
            $startDate = Carbon::parse($subscription->current_period_start ?? $subscription->start_date ?? now());
            $periodEnd = $plan->interval === 'monthly'
                ? $startDate->copy()->endOfMonth()
                : $startDate->copy()->addYear();
            $nextInvoiceAt = $plan->interval === 'monthly'
                ? $periodEnd->copy()->addDay()
                : $periodEnd->copy();

            $subscription->update([
                'plan_id' => $plan->id,
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $nextInvoiceAt->toDateString(),
            ]);
        }

        if ($order->invoice) {
            $billingService->recalculateInvoice($order->invoice->fresh('subscription.plan', 'items'));
        }

        SystemLogger::write('activity', 'Order updated.', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'plan_id' => $plan->id,
            'interval' => $plan->interval,
        ], $request->user()?->id, $request->ip());

        return back()->with('status', 'Order interval updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        SystemLogger::write('activity', 'Order deleted.', [
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'status' => $order->status,
        ]);

        $order->delete();

        return redirect()->route('admin.orders.index')
            ->with('status', 'Order deleted.');
    }

    private function normalizeDomain(string $input): ?string
    {
        $value = trim(strtolower($input));

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $value = parse_url($value, PHP_URL_HOST) ?: '';
        }

        $value = preg_replace('/^www\./', '', $value);

        if ($value === '' || ! preg_match('/^[a-z0-9.-]+$/', $value)) {
            return null;
        }

        return $value;
    }

    private function indexInertiaProps(LengthAwarePaginator $orders, ?string $status): array
    {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Orders',
            'status' => (string) ($status ?? ''),
            'routes' => [
                'index' => route('admin.orders.index'),
            ],
            'orders' => collect($orders->items())->map(function (Order $order) use ($dateFormat) {
                $plan = $order->plan;
                $product = $plan?->product;
                $service = $product
                    ? $product->name.' - '.($plan?->name ?? '')
                    : ($plan?->name ?? '--');

                $invoice = $order->invoice;
                $invoiceNumber = '--';
                $invoiceAmount = '--';

                if ($invoice) {
                    $invoiceNumber = is_numeric($invoice->number) ? (string) $invoice->number : (string) $invoice->id;
                    $invoiceAmount = trim((string) (($invoice->currency ?? '').' '.number_format((float) ($invoice->total ?? 0), 2)));
                }

                return [
                    'id' => $order->id,
                    'order_number' => (string) ($order->order_number ?? $order->id),
                    'customer_name' => (string) ($order->customer?->name ?? '--'),
                    'service' => (string) $service,
                    'status' => (string) $order->status,
                    'status_label' => ucfirst((string) $order->status),
                    'invoice_number' => $invoiceNumber,
                    'invoice_amount' => $invoiceAmount,
                    'created_at_display' => $order->created_at?->format($dateFormat) ?? '--',
                    'can_process' => (string) $order->status === 'pending',
                    'routes' => [
                        'show' => route('admin.orders.show', $order),
                        'invoice_show' => $invoice ? route('admin.invoices.show', $invoice) : null,
                        'approve' => route('admin.orders.approve', $order),
                        'cancel' => route('admin.orders.cancel', $order),
                        'destroy' => route('admin.orders.destroy', $order),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $orders->hasPages(),
                'previous_url' => $orders->previousPageUrl(),
                'next_url' => $orders->nextPageUrl(),
            ],
        ];
    }

    private function showInertiaProps(Order $order, $planOptions, $intervalOptions): array
    {
        $dateFormat = config('app.date_format', 'd-m-Y');
        $invoice = $order->invoice;

        return [
            'pageTitle' => 'Order Details',
            'order' => [
                'id' => $order->id,
                'order_number' => (string) ($order->order_number ?? $order->id),
                'status' => (string) $order->status,
                'status_label' => ucfirst((string) $order->status),
                'customer_name' => (string) ($order->customer?->name ?? '--'),
                'customer_email' => (string) ($order->customer?->email ?? '--'),
                'plan_name' => (string) ($order->plan?->name ?? '--'),
                'product_name' => (string) ($order->plan?->product?->name ?? '--'),
                'created_at_display' => $order->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                'approved_at_display' => $order->approved_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                'cancelled_at_display' => $order->cancelled_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                'invoice_number' => $invoice ? (string) ($invoice->number ?? $invoice->id) : '--',
                'invoice_total_display' => $invoice
                    ? trim((string) (($invoice->currency ?? '').' '.number_format((float) ($invoice->total ?? 0), 2)))
                    : '--',
                'invoice_url' => $invoice ? route('admin.invoices.show', $invoice) : null,
                'license_key' => (string) ($order->subscription?->licenses?->first()?->license_key ?? ''),
                'license_url' => (string) ($order->subscription?->licenses?->first()?->domains?->firstWhere('status', 'active')?->domain ?? ''),
            ],
            'plan_options' => collect($planOptions)->values()->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => (string) $plan->name,
                'interval' => (string) $plan->interval,
                'price' => (float) $plan->price,
                'currency' => (string) ($plan->currency ?? ''),
            ])->all(),
            'interval_options' => collect($intervalOptions)->values()->map(fn ($interval) => (string) $interval)->all(),
            'routes' => [
                'index' => route('admin.orders.index'),
                'approve' => route('admin.orders.approve', $order),
                'cancel' => route('admin.orders.cancel', $order),
                'update_plan' => route('admin.orders.plan', $order),
                'destroy' => route('admin.orders.destroy', $order),
            ],
        ];
    }
}
