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
use Illuminate\Validation\ValidationException;
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

    public function create(Request $request): InertiaResponse
    {
        $products = Product::query()->orderBy('name')->get();
        $defaultCurrency = Setting::getValue('currency');

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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));
        $pricingRows = $this->extractPricingRows($request);

        $createdPlans = [];
        foreach ($pricingRows as $index => $row) {
            $slugSeed = $this->resolveRowSlugSeed(
                (string) $data['name'],
                $data['slug'] ?? null,
                (string) $row['interval'],
                $index === 0
            );

            $createdPlans[] = Plan::create([
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'slug' => $this->resolveSlug($data['name'], $slugSeed),
                'interval' => $row['interval'],
                'price' => $row['price'],
                'is_active' => $data['is_active'],
                'currency' => $data['currency'],
            ]);
        }

        /** @var Plan $plan */
        $plan = $createdPlans[0];

        SystemLogger::write('activity', 'Plan created.', [
            'plan_id' => $plan->id,
            'plan_count' => count($createdPlans),
            'product_id' => $plan->product_id,
            'name' => $plan->name,
            'intervals' => collect($createdPlans)->pluck('interval')->values()->all(),
            'is_active' => $plan->is_active,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.plans.index'),
                'Plan created.'
            );
        }

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan created.');
    }

    public function edit(Request $request, Plan $plan): InertiaResponse
    {
        $products = Product::query()->orderBy('name')->get();
        $defaultCurrency = Setting::getValue('currency');

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
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));
        $pricingRows = $this->extractPricingRows($request);

        $editablePlans = Plan::query()
            ->where('product_id', $plan->product_id)
            ->where('name', $plan->name)
            ->get();
        $editablePlanIds = $editablePlans->pluck('id')->push($plan->id)->unique()->values();

        $primaryRowIndex = 0;
        foreach ($pricingRows as $index => $row) {
            if (! empty($row['id']) && (int) $row['id'] === $plan->id) {
                $primaryRowIndex = $index;
                break;
            }
        }

        $updatedIds = [];
        foreach ($pricingRows as $index => $row) {
            $rowId = isset($row['id']) ? (int) $row['id'] : null;

            if ($rowId && ! $editablePlanIds->contains($rowId)) {
                throw ValidationException::withMessages([
                    'pricing_rows' => 'Invalid plan row selected.',
                ]);
            }

            $targetPlan = null;
            if ($rowId) {
                $targetPlan = Plan::query()->find($rowId);
            } elseif ($index === $primaryRowIndex) {
                $targetPlan = $plan;
            }

            $isPrimaryTarget = $targetPlan && $targetPlan->id === $plan->id;
            $slugSeed = $this->resolveRowSlugSeed(
                (string) $data['name'],
                $data['slug'] ?? null,
                (string) $row['interval'],
                $isPrimaryTarget
            );

            $payload = [
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'slug' => $this->resolveSlug($data['name'], $slugSeed, $targetPlan?->id),
                'interval' => $row['interval'],
                'price' => $row['price'],
                'is_active' => $data['is_active'],
                'currency' => $data['currency'],
            ];

            if ($targetPlan) {
                $targetPlan->update($payload);
                $updatedIds[] = $targetPlan->id;
                continue;
            }

            $newPlan = Plan::create($payload);
            $updatedIds[] = $newPlan->id;
        }

        SystemLogger::write('activity', 'Plan updated.', [
            'plan_id' => $plan->id,
            'product_id' => $plan->product_id,
            'name' => $plan->name,
            'updated_plan_ids' => $updatedIds,
            'is_active' => $plan->is_active,
        ], $request->user()?->id, $request->ip());

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.plans.edit', $plan),
                'Plan updated.'
            );
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
            return AjaxResponse::ajaxRedirect(
                route('admin.plans.index'),
                'Plan deleted.'
            );
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
        $defaultPricingRows = collect();

        if ($isEdit && $plan) {
            $groupPlans = Plan::query()
                ->where('product_id', $plan->product_id)
                ->where('name', $plan->name)
                ->orderByRaw("CASE `interval` WHEN 'monthly' THEN 1 WHEN 'yearly' THEN 2 ELSE 3 END")
                ->orderBy('id')
                ->get();

            $defaultPricingRows = ($groupPlans->isNotEmpty() ? $groupPlans : collect([$plan]))
                ->map(fn (Plan $rowPlan) => [
                    'id' => (string) $rowPlan->id,
                    'interval' => (string) $rowPlan->interval,
                    'price' => (string) $rowPlan->price,
                ]);
        }

        if ($defaultPricingRows->isEmpty()) {
            $defaultPricingRows = collect([[
                'id' => '',
                'interval' => (string) ($plan?->interval ?? 'monthly'),
                'price' => (string) ($plan?->price ?? ''),
            ]]);
        }

        $oldPricingRows = old('pricing_rows');
        $pricingRows = is_array($oldPricingRows)
            ? collect($oldPricingRows)->map(fn ($row) => [
                'id' => (string) ($row['id'] ?? ''),
                'interval' => (string) ($row['interval'] ?? 'monthly'),
                'price' => (string) ($row['price'] ?? ''),
            ])->values()->all()
            : $defaultPricingRows->values()->all();

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
                    'pricing_rows' => $pricingRows,
                    'is_active' => (bool) old('is_active', (bool) ($plan?->is_active ?? true)),
                ],
            ],
            'routes' => [
                'index' => route('admin.plans.index'),
            ],
        ];
    }

    private function extractPricingRows(Request $request): array
    {
        $rows = [];
        $errors = [];
        $rawRows = $request->input('pricing_rows', []);

        if (is_array($rawRows)) {
            foreach ($rawRows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = isset($row['id']) && (string) $row['id'] !== '' ? (int) $row['id'] : null;
                $interval = strtolower(trim((string) ($row['interval'] ?? '')));
                $priceRaw = $row['price'] ?? null;
                $priceText = trim((string) $priceRaw);
                $isEmptyRow = $interval === '' && $priceText === '';

                if ($isEmptyRow) {
                    continue;
                }

                if (! in_array($interval, ['monthly', 'yearly'], true)) {
                    $errors["pricing_rows.{$index}.interval"] = 'Interval must be monthly or yearly.';
                }

                if ($priceText === '' || ! is_numeric($priceText) || (float) $priceText < 0) {
                    $errors["pricing_rows.{$index}.price"] = 'Price must be a number equal to or greater than 0.';
                }

                $rows[] = [
                    'id' => $id,
                    'interval' => $interval === '' ? 'monthly' : $interval,
                    'price' => round((float) ($priceText === '' ? 0 : $priceText), 2),
                ];
            }
        }

        if ($rows === []) {
            $interval = strtolower(trim((string) $request->input('interval', '')));
            $priceRaw = $request->input('price');

            if ($interval !== '' || $priceRaw !== null) {
                if (! in_array($interval, ['monthly', 'yearly'], true)) {
                    $errors['interval'] = 'Interval must be monthly or yearly.';
                }
                if (! is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $errors['price'] = 'Price must be a number equal to or greater than 0.';
                }
                $rows[] = [
                    'id' => null,
                    'interval' => $interval === '' ? 'monthly' : $interval,
                    'price' => round((float) $priceRaw, 2),
                ];
            }
        }

        if ($rows === []) {
            $errors['pricing_rows'] = 'Add at least one interval and price.';
        }

        $intervals = collect($rows)->pluck('interval')->all();
        if (count($intervals) !== count(array_unique($intervals))) {
            $errors['pricing_rows'] = 'Duplicate intervals are not allowed in the same submission.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    private function resolveRowSlugSeed(string $name, ?string $baseSlugInput, string $interval, bool $isPrimaryRow): ?string
    {
        if ($isPrimaryRow) {
            return $baseSlugInput;
        }

        $seed = $baseSlugInput ?: $name;

        return (string) Str::slug($seed.'-'.$interval);
    }
}
