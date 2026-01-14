<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Currency;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->where('status', 'active')
            ->with(['plans' => function ($query) {
                $query->where('is_active', true)->orderBy('price');
            }])
            ->orderBy('name')
            ->get()
            ->filter(fn (Product $product) => $product->plans->isNotEmpty());

        return view('public.products.index', [
            'products' => $products,
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
        ]);
    }

    public function show(Request $request, Product $product)
    {
        return $this->buildProductView($product, $request->query('plan'));
    }

    public function showPlan(Product $product, Plan $plan)
    {
        if ($plan->product_id !== $product->id) {
            abort(404);
        }

        return $this->buildProductView($product, $plan->id);
    }

    private function buildProductView(Product $product, ?string $selectedPlanId)
    {
        if ($product->status !== 'active') {
            abort(404);
        }

        $product->load(['plans' => function ($query) {
            $query->where('is_active', true)->orderBy('price');
        }]);

        return view('public.products.show', [
            'product' => $product,
            'currency' => strtoupper((string) Setting::getValue('currency', Currency::DEFAULT)),
            'selectedPlanId' => $selectedPlanId,
        ]);
    }
}
