<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Setting;
use App\Services\BillingService;
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

    public function store(Request $request, BillingService $billingService): RedirectResponse
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

        $invoice = DB::transaction(function () use ($customer, $plan, $startDate, $periodEnd, $billingService) {
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_date' => $startDate->toDateString(),
                'current_period_start' => $startDate->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $startDate->toDateString(),
                'auto_renew' => true,
                'cancel_at_period_end' => false,
            ]);

            $invoice = $billingService->generateInvoiceForSubscription($subscription, Carbon::today());

            License::create([
                'subscription_id' => $subscription->id,
                'product_id' => $plan->product_id,
                'license_key' => $this->uniqueLicenseKey(),
                'status' => 'active',
                'starts_at' => $startDate->toDateString(),
                'max_domains' => 1,
            ]);

            return $invoice;
        });

        if ($invoice) {
            return redirect()->route('client.invoices.pay', $invoice)
                ->with('status', 'Order placed. Please complete payment to activate your service.');
        }

        return redirect()->route('client.dashboard')
            ->with('status', 'Order placed. An invoice will be generated shortly.');
    }

    private function uniqueLicenseKey(): string
    {
        do {
            $key = License::generateKey();
        } while (License::query()->where('license_key', $key)->exists());

        return $key;
    }
}
