<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\License;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AdminNotificationService;
use App\Services\BillingService;
use App\Services\ClientNotificationService;
use App\Services\InvoiceTaxService;
use App\Support\Currency;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OrderController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        return Inertia::render('Client/Orders/Index', [
            'has_customer' => (bool) $customer,
            'products' => $products->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'plans' => $product->plans->map(function (Plan $plan) {
                        return [
                            'id' => $plan->id,
                            'name' => $plan->name,
                            'interval_label' => ucfirst((string) $plan->interval),
                            'price' => (float) $plan->price,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
            'routes' => [
                'dashboard' => route('client.dashboard'),
                'review' => route('client.orders.review'),
            ],
        ]);
    }

    public function review(Request $request): InertiaResponse|RedirectResponse
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

        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        $startDate = Carbon::today();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();
        $subtotal = $this->calculateSubtotal($plan->interval, (float) $plan->price, $startDate, $periodEnd);
        $periodDays = $startDate->diffInDays($periodEnd) + 1;
        $cycleDays = $plan->interval === 'monthly'
            ? $startDate->daysInMonth
            : ($plan->interval === 'yearly' ? $startDate->daysInYear : null);
        $showProration = $plan->interval === 'monthly'
            && $startDate->day !== 1
            && $periodEnd->isLastOfMonth();
        $dueDays = 0;
        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/Orders/Review', [
            'has_customer' => (bool) $customer,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval_label' => ucfirst((string) $plan->interval),
                'product_name' => $plan->product?->name ?? '--',
            ],
            'currency' => $currency,
            'start_date_display' => $startDate->format($dateFormat),
            'period_end_display' => $periodEnd->format($dateFormat),
            'subtotal' => $subtotal,
            'periodDays' => $periodDays,
            'cycleDays' => $cycleDays,
            'showProration' => $showProration,
            'dueDays' => $dueDays,
            'routes' => [
                'index' => route('client.orders.index'),
                'store' => route('client.orders.store'),
            ],
        ]);
    }

    public function store(
        Request $request,
        BillingService $billingService,
        InvoiceTaxService $taxService,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
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

        $result = DB::transaction(function () use ($customer, $plan, $startDate, $periodEnd, $billingService, $taxService, $request) {
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
            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            $dueDate = $issueDate->copy();

            $taxData = $taxService->calculateTotals($subtotal, 0.0, $issueDate);

            $invoice = Invoice::create([
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'number' => $billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $subtotal,
                'tax_rate_percent' => $taxData['tax_rate_percent'],
                'tax_mode' => $taxData['tax_mode'],
                'tax_amount' => $taxData['tax_amount'],
                'late_fee' => 0,
                'total' => $taxData['total'],
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
