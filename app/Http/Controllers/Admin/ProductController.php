<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $products = Product::query()
            ->select('products.*')
            ->selectSub(function ($query) {
                $query->from('subscriptions')
                    ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                    ->whereColumn('plans.product_id', 'products.id')
                    ->where('subscriptions.status', 'active')
                    ->selectRaw('COUNT(DISTINCT subscriptions.customer_id)');
            }, 'usage_count')
            ->latest()
            ->get();

        return Inertia::render(
            'Admin/Products/Index',
            $this->indexInertiaProps($products)
        );
    }

    public function create(Request $request): InertiaResponse
    {
        return Inertia::render(
            'Admin/Products/Form',
            $this->formInertiaProps(null)
        );
    }

    public function show(Request $request, Product $product): InertiaResponse
    {
        $product->loadMissing(['plans']);

        $planIds = $product->plans->pluck('id');

        $subscriptions = Subscription::query()
            ->whereIn('plan_id', $planIds)
            ->with(['customer', 'plan', 'licenses.domains'])
            ->latest()
            ->get();

        $directLicenses = License::query()
            ->where('product_id', $product->id)
            ->with(['subscription.customer', 'subscription.plan', 'domains'])
            ->latest()
            ->get();

        $clientServices = collect();
        $seenLicenseIds = [];

        foreach ($directLicenses as $license) {
            $seenLicenseIds[] = $license->id;
            $customer = $license->subscription?->customer;
            $plan = $license->subscription?->plan;

            $clientServices->push([
                'id' => $license->id,
                'type' => 'license',
                'license_id' => $license->id,
                'license_key' => (string) $license->license_key,
                'status' => (string) $license->status,
                'status_label' => ucfirst((string) $license->status),
                'starts_at' => $license->starts_at?->format('d M Y') ?? '--',
                'expires_at' => $license->expires_at?->format('d M Y') ?? 'Lifetime',
                'domains' => $license->domains->pluck('domain')->filter()->values()->all(),
                'max_domains' => (int) ($license->max_domains ?? 1),
                'last_check_at' => $license->last_check_at?->format('d M Y, h:i A') ?? '--',
                'last_check_ip' => (string) ($license->last_check_ip ?? '--'),
                'customer_id' => $customer?->id,
                'customer_name' => (string) ($customer?->name ?? 'Unknown Customer'),
                'customer_company' => (string) ($customer?->company_name ?? ''),
                'customer_email' => (string) ($customer?->email ?? ''),
                'customer_route' => $customer ? route('admin.customers.show', $customer) : null,
                'plan_id' => $plan?->id,
                'plan_name' => (string) ($plan?->name ?? 'Standard'),
                'subscription_id' => $license->subscription_id,
                'subscription_route' => $license->subscription ? route('admin.subscriptions.show', $license->subscription) : null,
            ]);
        }

        foreach ($subscriptions as $sub) {
            foreach ($sub->licenses as $license) {
                if (in_array($license->id, $seenLicenseIds)) {
                    continue;
                }
                $seenLicenseIds[] = $license->id;
                $customer = $sub->customer;
                $plan = $sub->plan;

                $clientServices->push([
                    'id' => $license->id,
                    'type' => 'license',
                    'license_id' => $license->id,
                    'license_key' => (string) $license->license_key,
                    'status' => (string) $license->status,
                    'status_label' => ucfirst((string) $license->status),
                    'starts_at' => $license->starts_at?->format('d M Y') ?? '--',
                    'expires_at' => $license->expires_at?->format('d M Y') ?? 'Lifetime',
                    'domains' => $license->domains->pluck('domain')->filter()->values()->all(),
                    'max_domains' => (int) ($license->max_domains ?? 1),
                    'last_check_at' => $license->last_check_at?->format('d M Y, h:i A') ?? '--',
                    'last_check_ip' => (string) ($license->last_check_ip ?? '--'),
                    'customer_id' => $customer?->id,
                    'customer_name' => (string) ($customer?->name ?? 'Unknown Customer'),
                    'customer_company' => (string) ($customer?->company_name ?? ''),
                    'customer_email' => (string) ($customer?->email ?? ''),
                    'customer_route' => $customer ? route('admin.customers.show', $customer) : null,
                    'plan_id' => $plan?->id,
                    'plan_name' => (string) ($plan?->name ?? 'Standard'),
                    'subscription_id' => $sub->id,
                    'subscription_route' => route('admin.subscriptions.show', $sub),
                ]);
            }

            if ($sub->licenses->isEmpty()) {
                $customer = $sub->customer;
                $plan = $sub->plan;

                $clientServices->push([
                    'id' => 'sub-'.$sub->id,
                    'type' => 'subscription',
                    'license_id' => null,
                    'license_key' => '--',
                    'status' => (string) $sub->status,
                    'status_label' => ucfirst((string) $sub->status),
                    'starts_at' => $sub->start_date?->format('d M Y') ?? '--',
                    'expires_at' => $sub->current_period_end?->format('d M Y') ?? '--',
                    'domains' => [],
                    'max_domains' => 1,
                    'last_check_at' => '--',
                    'last_check_ip' => '--',
                    'customer_id' => $customer?->id,
                    'customer_name' => (string) ($customer?->name ?? 'Unknown Customer'),
                    'customer_company' => (string) ($customer?->company_name ?? ''),
                    'customer_email' => (string) ($customer?->email ?? ''),
                    'customer_route' => $customer ? route('admin.customers.show', $customer) : null,
                    'plan_id' => $plan?->id,
                    'plan_name' => (string) ($plan?->name ?? 'Standard'),
                    'subscription_id' => $sub->id,
                    'subscription_route' => route('admin.subscriptions.show', $sub),
                ]);
            }
        }

        $totalClients = $clientServices->pluck('customer_id')->filter()->unique()->count();
        $activeLicenses = $clientServices->where('status', 'active')->count();
        $totalLicenses = $clientServices->count();

        $plans = $product->plans->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => (string) $plan->name,
                'slug' => (string) $plan->slug,
                'price' => (float) $plan->price,
                'price_formatted' => sprintf('%s %s', $plan->currency ?? 'USD', number_format((float) $plan->price, 2)),
                'interval' => ucfirst((string) $plan->interval),
                'is_active' => (bool) $plan->is_active,
                'seat_limit' => (int) ($plan->seat_limit ?? 0),
                'routes' => [
                    'edit' => route('admin.plans.edit', $plan),
                ],
            ];
        });

        return Inertia::render('Admin/Products/Show', [
            'pageTitle' => $product->name.' - Product Details',
            'product' => [
                'id' => $product->id,
                'name' => (string) $product->name,
                'slug' => (string) $product->slug,
                'description' => (string) ($product->description ?? ''),
                'status' => (string) $product->status,
                'status_label' => ucfirst((string) $product->status),
                'created_at' => $product->created_at?->format('d M Y') ?? '--',
            ],
            'stats' => [
                'total_clients' => $totalClients,
                'active_licenses' => $activeLicenses,
                'total_licenses' => $totalLicenses,
                'total_plans' => $plans->count(),
            ],
            'plans' => $plans->values()->all(),
            'client_services' => $clientServices->values()->all(),
            'routes' => [
                'index' => route('admin.products.index'),
                'edit' => route('admin.products.edit', $product),
                'create_plan' => route('admin.plans.create'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $product = Product::create($data);

        SystemLogger::write('activity', 'Product created.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.products.index'),
                'Product created.'
            );
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Product created.');
    }

    public function edit(Request $request, Product $product): InertiaResponse
    {
        return Inertia::render(
            'Admin/Products/Form',
            $this->formInertiaProps($product)
        );
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $product->update($data);

        SystemLogger::write('activity', 'Product updated.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.products.edit', $product),
                'Product updated.'
            );
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $planIds = $product->plans()->pluck('id');

        $hasHistory = $product->licenses()->exists()
            || Order::where('product_id', $product->id)->orWhereIn('plan_id', $planIds)->exists()
            || ($planIds->isNotEmpty() && Subscription::whereIn('plan_id', $planIds)->exists());

        if ($hasHistory) {
            $message = 'This product has orders, subscriptions, or licenses on record and cannot be deleted.';

            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxError($message);
            }

            return redirect()->route('admin.products.index')->with('error', $message);
        }

        SystemLogger::write('activity', 'Product deleted.', [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status,
        ], auth()->id(), request()->ip());

        $product->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.products.index'),
                'Product deleted.'
            );
        }

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }

    private function indexInertiaProps(EloquentCollection $products): array
    {
        return [
            'pageTitle' => 'Products',
            'routes' => [
                'create' => route('admin.products.create'),
            ],
            'products' => $products->values()->map(function (Product $product, int $index) {
                return [
                    'id' => $product->id,
                    'serial' => $index + 1,
                    'name' => (string) $product->name,
                    'slug' => (string) $product->slug,
                    'status' => (string) $product->status,
                    'status_label' => ucfirst((string) $product->status),
                    'usage_count' => (int) ($product->usage_count ?? 0),
                    'routes' => [
                        'show' => route('admin.products.show', $product),
                        'edit' => route('admin.products.edit', $product),
                        'destroy' => route('admin.products.destroy', $product),
                    ],
                ];
            })->all(),
        ];
    }

    private function formInertiaProps(?Product $product): array
    {
        $isEdit = $product !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Product' : 'Add Product',
            'is_edit' => $isEdit,
            'form' => [
                'action' => $isEdit
                    ? route('admin.products.update', $product)
                    : route('admin.products.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'name' => (string) old('name', (string) ($product?->name ?? '')),
                    'slug' => (string) old('slug', (string) ($product?->slug ?? '')),
                    'description' => (string) old('description', (string) ($product?->description ?? '')),
                    'status' => (string) old('status', (string) ($product?->status ?? 'active')),
                ],
            ],
            'routes' => [
                'index' => route('admin.products.index'),
            ],
        ];
    }
}
