<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AdminNotificationService;
use App\Services\BillingService;
use App\Services\ClientNotificationService;
use App\Services\InvoiceVatService;
use App\Services\MyBuildingProvisioner;
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
    public function __construct(private readonly ?MyBuildingProvisioner $provisioner = null)
    {
    }

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
            // MyBuilding is priced by building size, so the customer states it
            // when ordering. Ignored for every other product.
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:100'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:200'],
            'flats_per_floor' => ['nullable', 'integer', 'min:1', 'max:26'],
            'floor_plan' => ['nullable', 'array', 'max:200'],
            'floor_plan.*' => ['integer', 'min:0', 'max:26'],
            'district_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
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

        $isMybuilding = $plan->product?->slug === config('mybuilding.product_slug');
        $floors = (int) ($data['total_floors'] ?? 1);
        $flatsPerFloor = (int) ($data['flats_per_floor'] ?? 4);
        $rawFloorPlan = $data['floor_plan'] ?? null;
        $floorPlan = [];

        if ($isMybuilding && is_array($rawFloorPlan) && count($rawFloorPlan) > 0) {
            $floorPlan = array_map(fn ($v) => max(0, min(26, (int) $v)), array_values($rawFloorPlan));
            $floors = count($floorPlan);
            $contractedFlats = max(1, (int) array_sum($floorPlan));
            $flatsPerFloor = (int) ceil($contractedFlats / max(1, $floors));
        } else {
            $contractedFlats = $isMybuilding ? max(1, $floors * $flatsPerFloor) : 1;
            if ($isMybuilding) {
                $floorPlan = array_fill(0, $floors, $flatsPerFloor);
            }
        }

        $unitPrice = (float) $plan->price;
        $basePrice = $isMybuilding ? round($unitPrice * $contractedFlats, 2) : $unitPrice;

        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        $startDate = Carbon::today();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();
        $subtotal = $this->calculateSubtotal($plan->interval, $basePrice, $startDate, $periodEnd);
        $periodDays = $startDate->diffInDays($periodEnd) + 1;
        $cycleDays = $plan->interval === 'monthly'
            ? $startDate->daysInMonth
            : ($plan->interval === 'yearly' ? $startDate->daysInYear : null);
        $showProration = $plan->interval === 'monthly'
            && $startDate->day !== 1
            && $periodEnd->isLastOfMonth();
        $dueDays = 0;
        $dateFormat = config('app.date_format', 'd-m-Y');

        $locations = [];
        if ($isMybuilding && $this->provisioner) {
            $installUrl = (string) (config('mybuilding.default_install_url') ?: 'http://127.0.0.1:8000');
            $locationData = $this->provisioner->locations($installUrl);
            if (!empty($locationData['ok']) && !empty($locationData['districts'])) {
                $locations = $locationData['districts'];
            }
        }

        return Inertia::render('Client/Orders/Review', [
            'has_customer' => (bool) $customer,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval_label' => ucfirst((string) $plan->interval),
                'price' => $unitPrice,
                'product_name' => $plan->product?->name ?? '--',
                'product_slug' => $plan->product?->slug,
            ],
            // Carried through the review step so the order records the size
            // the customer chose.
            'is_mybuilding' => $isMybuilding,
            'locations' => $locations,
            'contracted_flats' => $contractedFlats,
            'unit_price' => $unitPrice,
            'building' => [
                'building_name' => $data['building_name'] ?? '',
                'building_number' => $data['building_number'] ?? '',
                'building_address' => $data['building_address'] ?? '',
                'total_floors' => $floors,
                'flats_per_floor' => $flatsPerFloor,
                'floor_plan' => $floorPlan,
                'district_id' => isset($data['district_id']) && $data['district_id'] !== '' ? (int) $data['district_id'] : null,
                'city_id' => isset($data['city_id']) && $data['city_id'] !== '' ? (int) $data['city_id'] : null,
                'area_id' => isset($data['area_id']) && $data['area_id'] !== '' ? (int) $data['area_id'] : null,
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
        InvoiceVatService $vatService,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            // MyBuilding is priced by building size, so the customer states it
            // when ordering. Ignored for every other product.
            'building_name' => ['nullable', 'string', 'max:255'],
            'building_number' => ['nullable', 'string', 'max:100'],
            'building_address' => ['nullable', 'string', 'max:500'],
            'total_floors' => ['nullable', 'integer', 'min:1', 'max:200'],
            'flats_per_floor' => ['nullable', 'integer', 'min:1', 'max:26'],
            'floor_plan' => ['nullable', 'array', 'max:200'],
            'floor_plan.*' => ['integer', 'min:0', 'max:26'],
            'district_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
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

        $isMybuilding = $plan->product?->slug === config('mybuilding.product_slug');
        $floors = (int) ($request->input('total_floors') ?: 1);
        $perFloor = (int) ($request->input('flats_per_floor') ?: 4);
        $rawFloorPlan = $request->input('floor_plan');
        $floorPlan = null;

        if ($isMybuilding && is_array($rawFloorPlan) && count($rawFloorPlan) > 0) {
            $floorPlan = array_map(fn ($v) => max(0, min(26, (int) $v)), array_values($rawFloorPlan));
            $contractedFlats = max(1, (int) array_sum($floorPlan));
            $floors = count($floorPlan);
            $perFloor = (int) ceil($contractedFlats / max(1, $floors));
        } else {
            $contractedFlats = $isMybuilding ? max(1, $floors * $perFloor) : 1;
            if ($isMybuilding) {
                $floorPlan = array_fill(0, $floors, $perFloor);
            }
        }

        $unitPrice = (float) $plan->price;
        $baseRecurringAmount = $isMybuilding ? round($unitPrice * $contractedFlats, 2) : $unitPrice;

        $buildingNumber = trim((string) $request->input('building_number', ''));
        $rawAddress = trim((string) $request->input('building_address', ''));
        $buildingAddress = $buildingNumber !== ''
            ? ($rawAddress !== '' ? "Holding/No: {$buildingNumber}, {$rawAddress}" : "Holding/No: {$buildingNumber}")
            : ($rawAddress !== '' ? $rawAddress : null);

        $startDate = Carbon::today();
        $periodEnd = $plan->interval === 'monthly'
            ? $startDate->copy()->endOfMonth()
            : $startDate->copy()->addYear();

        $result = DB::transaction(function () use (
            $customer,
            $plan,
            $startDate,
            $periodEnd,
            $billingService,
            $vatService,
            $request,
            $isMybuilding,
            $floors,
            $perFloor,
            $floorPlan,
            $contractedFlats,
            $unitPrice,
            $baseRecurringAmount,
            $buildingAddress
        ) {
            $nextInvoiceAt = $this->nextInvoiceAt($plan->interval, $periodEnd);
            $subscription = Subscription::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'subscription_amount' => $isMybuilding ? $baseRecurringAmount : null,
                'status' => 'pending',
                'start_date' => $startDate->toDateString(),
                'current_period_start' => $startDate->toDateString(),
                'current_period_end' => $periodEnd->toDateString(),
                'next_invoice_at' => $nextInvoiceAt->toDateString(),
                'auto_renew' => true,
                'cancel_at_period_end' => false,
            ]);

            $issueDate = Carbon::today();
            $subtotal = $this->calculateSubtotal($plan->interval, $baseRecurringAmount, $startDate, $periodEnd);
            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            $dueDate = $startDate->day === 1 ? $startDate->copy() : $issueDate->copy();

            $taxData = $vatService->calculateTotals($subtotal, 0.0, $issueDate);

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

            $itemDescription = $isMybuilding
                ? sprintf(
                    '%s (%s) - %d Flats across %d Floors @ %s %s (%s to %s)',
                    $plan->name,
                    $plan->interval,
                    $contractedFlats,
                    $floors,
                    $currency,
                    number_format($unitPrice, 2),
                    $startDate->format(config('app.date_format', 'd-m-Y')),
                    $periodEnd->format(config('app.date_format', 'd-m-Y'))
                )
                : sprintf(
                    '%s (%s) %s to %s',
                    $plan->name,
                    $plan->interval,
                    $startDate->format(config('app.date_format', 'd-m-Y')),
                    $periodEnd->format(config('app.date_format', 'd-m-Y'))
                );

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $itemDescription,
                'quantity' => $contractedFlats,
                'unit_price' => $unitPrice,
                'line_total' => $subtotal,
            ]);

            $license = License::create([
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

            // Record the ordered building so approval can provision it without
            // anyone re-keying the details.
            if ($isMybuilding) {
                MyBuildingProvision::updateOrCreate(
                    ['license_id' => $license->id],
                    [
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'building_name' => $request->input('building_name')
                            ?: ($customer->company_name ?: $customer->name),
                        'building_address' => $buildingAddress ?: $customer->address,
                        'total_floors' => $floors,
                        'flats_per_floor' => $perFloor,
                        'floor_plan' => $floorPlan,
                        'contracted_flats' => $contractedFlats,
                        'district_id' => $request->filled('district_id') ? (int) $request->input('district_id') : null,
                        'city_id' => $request->filled('city_id') ? (int) $request->input('city_id') : null,
                        'area_id' => $request->filled('area_id') ? (int) $request->input('area_id') : null,
                        'install_url' => (string) (config('mybuilding.default_install_url') ?: ''),
                        'owner_name' => $customer->name,
                        'owner_email' => $customer->email,
                        'owner_phone' => $customer->phone ?: '',
                        'status' => MyBuildingProvision::STATUS_PENDING,
                    ]
                );
            }

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
        $periodStart = $periodEnd->copy()->addDay();
        if ($periodStart->day === 1) {
            return $periodStart->copy()->subDays(10);
        }

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
