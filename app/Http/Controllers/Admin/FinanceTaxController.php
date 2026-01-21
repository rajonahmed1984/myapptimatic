<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinanceTaxController extends Controller
{
    public function index(): View
    {
        $settings = TaxSetting::current();
        $rates = TaxRate::query()->orderByDesc('effective_from')->orderBy('name')->get();

        return view('admin.finance.tax.index', [
            'settings' => $settings,
            'rates' => $rates,
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'tax_mode_default' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'default_tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'invoice_tax_label' => ['required', 'string', 'max:100'],
            'invoice_tax_note_template' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = TaxSetting::current();
        $settings->update([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'tax_mode_default' => $data['tax_mode_default'],
            'default_tax_rate_id' => $data['default_tax_rate_id'] ?? null,
            'invoice_tax_label' => $data['invoice_tax_label'],
            'invoice_tax_note_template' => $data['invoice_tax_note_template'] ?? null,
        ]);

        return back()->with('status', 'Tax settings updated.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        TaxRate::create([
            'name' => $data['name'],
            'rate_percent' => $data['rate_percent'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Tax rate created.');
    }

    public function editRate(TaxRate $rate): View
    {
        return view('admin.finance.tax.edit-rate', compact('rate'));
    }

    public function updateRate(Request $request, TaxRate $rate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rate->update([
            'name' => $data['name'],
            'rate_percent' => $data['rate_percent'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('admin.finance.tax.index')
            ->with('status', 'Tax rate updated.');
    }

    public function destroyRate(TaxRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('status', 'Tax rate deleted.');
    }
}
