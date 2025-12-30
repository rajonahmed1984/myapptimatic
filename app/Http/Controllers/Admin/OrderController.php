<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Services\BillingService;
use App\Services\AdminNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $allowed = ['pending', 'accepted', 'cancelled'];

        $ordersQuery = Order::query()
            ->with(['customer', 'plan.product', 'invoice'])
            ->latest();

        if ($status && in_array($status, $allowed, true)) {
            $ordersQuery->where('status', $status);
        }

        return view('admin.orders.index', [
            'orders' => $ordersQuery->get(),
            'status' => $status,
        ]);
    }

    public function show(Order $order)
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

        return view('admin.orders.show', [
            'order' => $order,
            'planOptions' => $planOptions,
            'intervalOptions' => $intervalOptions,
        ]);
    }

    public function approve(Request $request, Order $order): RedirectResponse
    {
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

        return back()->with('status', 'Order interval updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
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
}
