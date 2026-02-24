<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PlanController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $plans = Plan::query()->with('product')->latest()->get();
        $defaultCurrency = Setting::getValue('currency');

        return Inertia::render(
            'Admin/Plans/Index',
            $this->indexInertiaProps($plans, (string) $defaultCurrency)
        );
    }

    public function create(Request $request): View|InertiaResponse
    {
        $products = Product::query()->orderBy('name')->get();
        $defaultCurrency = Setting::getValue('currency');

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.plans.partials.form', compact('products', 'defaultCurrency'));
        }

        return Inertia::render(
            'Admin/Plans/Form',
            $this->formInertiaProps(
                null,
                $products,
                (string) $defaultCurrency
            )
        );
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null);
        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));

        $plan = Plan::create($data);

        SystemLogger::write('activity', 'Plan created.', [
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
            'name' => $plan->name,
            'interval' => $plan->interval,
            'price' => $plan->price,
            'is_active' => $plan->is_active,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Plan created.', $this->patches());
        }

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan created.');
    }

    public function edit(Request $request, Plan $plan): View|InertiaResponse
    {
        $products = Product::query()->orderBy('name')->get();
        $defaultCurrency = Setting::getValue('currency');

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.plans.partials.form', compact('plan', 'products', 'defaultCurrency'));
        }

        return Inertia::render(
            'Admin/Plans/Form',
            $this->formInertiaProps(
                $plan,
                $products,
                (string) $defaultCurrency
            )
        );
    }

    public function update(Request $request, Plan $plan): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null, $plan->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));

        $plan->update($data);

        SystemLogger::write('activity', 'Plan updated.', [
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
            'name' => $plan->name,
            'interval' => $plan->interval,
            'price' => $plan->price,
            'is_active' => $plan->is_active,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Plan updated.', $this->patches());
        }

        return redirect()->route('admin.plans.edit', $plan)
            ->with('status', 'Plan updated.');
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse|JsonResponse
    {
        SystemLogger::write('activity', 'Plan deleted.', [
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
            'name' => $plan->name,
            'interval' => $plan->interval,
            'price' => $plan->price,
            'is_active' => $plan->is_active,
        ], auth()->id(), request()->ip());

        $plan->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Plan deleted.', $this->patches(), closeModal: false);
        }

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan deleted.');
    }

    private function resolveSlug(string $name, ?string $input = null, ?int $ignoreId = null): string
    {
        $base = Str::slug($input ?: $name);
        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $counter = 1;

        while (
            Plan::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function patches(): array
    {
        return [
            [
                'action' => 'replace',
                'selector' => '#plansTableWrap',
                'html' => view('admin.plans.partials.table', [
                    'plans' => Plan::query()->with('product')->latest()->get(),
                    'defaultCurrency' => Setting::getValue('currency'),
                ])->render(),
            ],
        ];
    }

    private function indexInertiaProps(EloquentCollection $plans, string $defaultCurrency): array
    {
        return [
            'pageTitle' => 'Plans',
            'default_currency' => $defaultCurrency,
            'routes' => [
                'create' => route('admin.plans.create'),
            ],
            'plans' => $plans->values()->map(function (Plan $plan, int $index) use ($defaultCurrency) {
                $product = $plan->product;

                return [
                    'id' => $plan->id,
                    'serial' => $index + 1,
                    'name' => (string) $plan->name,
                    'slug_path' => ($plan->slug && $product?->slug)
                        ? (string) ($product->slug.'/plans/'.$plan->slug)
                        : '--',
                    'product_name' => (string) ($product?->name ?? '--'),
                    'price_display' => trim($defaultCurrency.' '.(string) $plan->price),
                    'interval_label' => ucfirst((string) $plan->interval),
                    'status' => $plan->is_active ? 'active' : 'inactive',
                    'status_label' => $plan->is_active ? 'Active' : 'Inactive',
                    'routes' => [
                        'edit' => route('admin.plans.edit', $plan),
                        'destroy' => route('admin.plans.destroy', $plan),
                    ],
                ];
            })->all(),
        ];
    }

    private function formInertiaProps(?Plan $plan, EloquentCollection $products, string $defaultCurrency): array
    {
        $isEdit = $plan !== null;

        return [
            'pageTitle' => $isEdit ? 'Edit Plan' : 'Add Plan',
            'default_currency' => $defaultCurrency,
            'is_edit' => $isEdit,
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => (string) $product->name,
            ])->values()->all(),
            'form' => [
                'action' => $isEdit
                    ? route('admin.plans.update', $plan)
                    : route('admin.plans.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'product_id' => (string) old('product_id', (string) ($plan?->product_id ?? '')),
                    'name' => (string) old('name', (string) ($plan?->name ?? '')),
                    'slug' => (string) old('slug', (string) ($plan?->slug ?? '')),
                    'interval' => (string) old('interval', (string) ($plan?->interval ?? 'monthly')),
                    'price' => (string) old('price', (string) ($plan?->price ?? '')),
                    'is_active' => (bool) old('is_active', (bool) ($plan?->is_active ?? true)),
                ],
            ],
            'routes' => [
                'index' => route('admin.plans.index'),
            ],
        ];
    }
}
