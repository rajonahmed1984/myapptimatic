<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
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
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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
            'interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ]);

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
}
