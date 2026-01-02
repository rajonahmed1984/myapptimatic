<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Setting;
use App\Services\BillingService;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()->customer;
        $products = Product::query()
            ->where('status', 'active')
            ->with(['plans' => function ($query) {
                $query->where('is_active', true)->orderBy('price');
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->plans->isNotEmpty());

        return view('client.orders.index', [
            'customer' => $customer,
            'products' => $products,
            'currency' => strtoupper((string) Setting::getValue('currency', 'USD')),
        ]);
    }

    public function review(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $customer = $request->user()->customer;

        if (! $customer) {
            return redirect()->route('client.orders.index')
                ->withErrors(['plan_id' => 'Your account is not linked to a customer profile.']);
        }

        $plan = Plan::query()->with('product')->findOrFail($data['plan_id']);

        if (! $plan->is_active || ! $plan->product || $plan->product->status !== 'active') {
            return redirect()->route('client.orders.index')
                ->withErrors(['plan_id' => 'This plan is not available for ordering.']);
        }

        $currency = strtoupper((string) Setting::getValue('currency', 'USD'));
        $startDate = Carbon::today();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();
        $dueDays = 0;

        return view('client.orders.review', [
            'customer' => $customer,
            'plan' => $plan,
            'currency' => $currency,
            'startDate' => $startDate,
            'periodEnd' => $periodEnd,
            'dueDays' => $dueDays,
        ]);
    }

    public function store(
        Request $request,
        BillingService $billingService,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $customer = $request->user()->customer;

        if (! $customer) {
            return redirect()->route('client.orders.index')
                ->withErrors(['plan_id' => 'Your account is not linked to a customer profile.']);
        }

        $plan = Plan::query()->with('product')->findOrFail($data['plan_id']);

        if (! $plan->is_active || ! $plan->product || $plan->product->status !== 'active') {
            return redirect()->route('client.orders.index')
                ->withErrors(['plan_id' => 'This plan is not available for ordering.']);
        }

        $startDate = Carbon::today();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();

        $result = DB::transaction(function () use ($customer, $plan, $startDate, $periodEnd, $billingService, $request) {
            $nextInvoiceAt = $this->nextInvoiceAt($plan->interval, $periodEnd);
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'start_date' => $startDate->toDateString(),
                'current_period_start' => $startDate->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $nextInvoiceAt->toDateString(),
                'auto_renew' => true,
                'cancel_at_period_end' => false,
            ]);

            $issueDate = Carbon::today();
            $subtotal = $this->calculateSubtotal($plan->interval, (float) $plan->price, $startDate, $periodEnd);
            $currency = strtoupper((string) Setting::getValue('currency', 'USD'));
            $dueDate = $issueDate->copy();

            $invoice = Invoice::create([
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $subtotal,
                'late_fee' => 0,
                'total' => $subtotal,
                'currency' => $currency,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf(
                    '%s (%s) %s to %s',
                    $plan->name,
                    $plan->interval,
                    $startDate->format('Y-m-d'),
                    $periodEnd->format('Y-m-d')
                ),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal,
            ]);

            License::create([
                'subscription_id' => $subscription->id,
                'product_id' => $plan->product_id,
                'license_key' => $this->uniqueLicenseKey(),
                'status' => 'pending',
                'starts_at' => $startDate->toDateString(),
                'max_domains' => 1,
            ]);

            $order = Order::create([
                'order_number' => Order::nextNumber(),
                'customer_id' => $customer->id,
                'user_id' => $request->user()?->id,
                'product_id' => $plan->product_id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'invoice_id' => $invoice->id,
                'status' => 'pending',
            ]);

            return [
                'invoice' => $invoice,
                'order' => $order,
            ];
        });

        $invoice = $result['invoice'] ?? null;
        $order = $result['order'] ?? null;

        if ($order) {
            $adminNotifications->sendNewOrder($order, $request->ip());
            $clientNotifications->sendOrderConfirmation($order);
        }

        if ($order) {
            SystemLogger::write('activity', 'Order placed.', [
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_id' => $order->invoice_id,
            ], $request->user()?->id, $request->ip());
        }

        if ($invoice) {
            $adminNotifications->sendInvoiceCreated($invoice);
            $clientNotifications->sendInvoiceCreated($invoice);
        }

        if ($invoice) {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'Order placed. Please complete payment or wait for approval.');
        }

        return redirect()->route('client.dashboard')
            ->with('status', 'Order placed. An invoice will be generated shortly.');
    }

    private function calculateSubtotal(string $interval, float $price, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($interval !== 'monthly') {
            return round($price, 2);
        }

        if ($periodStart->isSameMonth($periodEnd) && $periodEnd->isLastOfMonth() && $periodStart->day !== 1) {
            $daysInPeriod = $periodStart->diffInDays($periodEnd) + 1;
            $daysInMonth = $periodStart->daysInMonth;
            $ratio = $daysInMonth > 0 ? ($daysInPeriod / $daysInMonth) : 1;

            return round($price * min(1, $ratio), 2);
        }

        return round($price, 2);
    }

    private function nextInvoiceAt(string $interval, Carbon $periodEnd): Carbon
    {
        if ($interval === 'monthly') {
            return $periodEnd->copy()->addDay();
        }

        $invoiceGenerationDays = (int) Setting::getValue('invoice_generation_days');
        $nextInvoiceAt = $invoiceGenerationDays > 0
            ? $periodEnd->copy()->subDays($invoiceGenerationDays)
            : $periodEnd->copy();

        if ($nextInvoiceAt->lessThan(Carbon::today())) {
            $nextInvoiceAt = $periodEnd->copy();
        }

        return $nextInvoiceAt;
    }

    private function uniqueLicenseKey(): string
    {
        do {
            $key = License::generateKey();
        } while (License::query()->where('license_key', $key)->exists());

        return $key;
    }

}
