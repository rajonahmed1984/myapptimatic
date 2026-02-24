<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('status', 'active')
            ->with(['plans' => function ($query) {
                $query->where('is_active', true)->orderBy('price');
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->plans->isNotEmpty());
        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));

        return Inertia::render('Public/Products/Index', [
            'currency' => $currency,
            'products' => $products->map(function (Product $product) {
                return [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'description' => $product->description,
                    'plan_count' => $product->plans->count(),
                    'min_price' => $product->plans->min('price') !== null
                        ? (float) $product->plans->min('price')
                        : null,
                    'routes' => [
                        'show' => route('products.public.show', ['product' => $product->slug]),
                    ],
                ];
            })->values()->all(),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        return $this->buildProductView($request, $product, $request->query('plan'));
    }

    public function showPlan(Request $request, Product $product, Plan $plan)
    {
        if ($plan->product_id !== $product->id) {
            abort(404);
        }

        return $this->buildProductView($request, $product, (string) $plan->id);
    }

    private function buildProductView(Request $request, Product $product, ?string $selectedPlanId)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $product->load(['plans' => function ($query) {
            $query->where('is_active', true)->orderBy('price');
        }]);
        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));

        $visiblePlans = $product->plans;
        if ($selectedPlanId !== null && $selectedPlanId !== '') {
            $visiblePlans = $product->plans->where('id', (int) $selectedPlanId);
        }

        $isClient = (bool) ($request->user() && method_exists($request->user(), 'isClient') && $request->user()->isClient());
        $hasCustomerProfile = (bool) ($request->user()?->customer);

        return Inertia::render('Public/Products/Show', [
            'currency' => $currency,
            'selected_plan_id' => $selectedPlanId,
            'can_order_as_client' => $isClient,
            'has_customer_profile' => $hasCustomerProfile,
            'product' => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'description' => $product->description,
            ],
            'plans' => $visiblePlans->map(function (Plan $plan) use ($currency, $product, $selectedPlanId) {
                $redirectUrl = route('products.public.show', [
                    'product' => $product->slug,
                    'plan' => $plan->id,
                ]);

                return [
                    'id' => $plan->id,
                    'name' => (string) $plan->name,
                    'interval' => ucfirst((string) $plan->interval),
                    'price' => (float) $plan->price,
                    'price_display' => $currency . ' ' . number_format((float) $plan->price, 2),
                    'is_selected' => (string) $selectedPlanId === (string) $plan->id,
                    'routes' => [
                        'plan' => route('products.public.plan', ['product' => $product->slug, 'plan' => $plan->slug]),
                        'review' => route('client.orders.review', ['plan_id' => $plan->id]),
                        'login' => route('login', ['redirect' => $redirectUrl]),
                        'register' => route('register', ['redirect' => $redirectUrl]),
                    ],
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('products.public.index'),
            ],
        ]);
    }
}
