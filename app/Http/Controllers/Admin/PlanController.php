<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->with('product')->latest()->get(),
            'defaultCurrency' => Setting::getValue('currency'),
        ]);
    }

    public function create()
    {
        return view('admin.plans.create', [
            'products' => Product::query()->orderBy('name')->get(),
            'defaultCurrency' => Setting::getValue('currency'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null);
        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));

        Plan::create($data);

        return redirect()->route('admin.plans.index')
            ->with('status', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', [
            'plan' => $plan,
            'products' => Product::query()->orderBy('name')->get(),
            'defaultCurrency' => Setting::getValue('currency'),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $this->resolveSlug($data['name'], $data['slug'] ?? null, $plan->id);
        $data['is_active'] = $request->boolean('is_active');
        $data['currency'] = strtoupper((string) Setting::getValue('currency'));

        $plan->update($data);

        return redirect()->route('admin.plans.edit', $plan)
            ->with('status', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();

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
}
