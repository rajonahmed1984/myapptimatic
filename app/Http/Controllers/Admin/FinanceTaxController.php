<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class FinanceTaxController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $settings = TaxSetting::current();
        $rates = TaxRate::query()->orderByDesc('effective_from')->orderBy('name')->get();

        $payload = [
            'settings' => $settings,
            'rates' => $rates,
        ];

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_FINANCE_TAX_INDEX,
            'admin.finance.tax.index',
            $payload,
            'Admin/Finance/Tax/Index',
            $this->indexInertiaProps($settings, $rates)
        );
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

    private function indexInertiaProps(TaxSetting $settings, Collection $rates): array
    {
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');
        $defaultRateId = old('default_tax_rate_id', $settings->default_tax_rate_id);
        $oldEnabled = old('enabled');

        return [
            'pageTitle' => 'Tax Settings',
            'heading' => 'Tax settings',
            'subheading' => 'Configure tax mode, default rates, and invoice notes.',
            'routes' => [
                'reports' => route('admin.finance.reports.index'),
                'settings_update' => route('admin.finance.tax.update'),
                'rate_store' => route('admin.finance.tax.rates.store'),
            ],
            'settings_form' => [
                'enabled' => $oldEnabled !== null
                    ? (bool) $oldEnabled
                    : (bool) $settings->enabled,
                'tax_mode_default' => (string) old('tax_mode_default', (string) $settings->tax_mode_default),
                'default_tax_rate_id' => $defaultRateId !== null
                    ? (string) $defaultRateId
                    : '',
                'invoice_tax_label' => (string) old('invoice_tax_label', (string) $settings->invoice_tax_label),
                'invoice_tax_note_template' => (string) old('invoice_tax_note_template', (string) ($settings->invoice_tax_note_template ?? '')),
            ],
            'rate_form' => [
                'name' => (string) old('name', ''),
                'rate_percent' => (string) old('rate_percent', ''),
                'effective_from' => (string) old('effective_from', ''),
                'effective_to' => (string) old('effective_to', ''),
                'is_active' => (bool) old('is_active', true),
            ],
            'quick_reference' => [
                'mode' => ucfirst((string) $settings->tax_mode_default),
                'default_rate_name' => (string) ($settings->defaultRate?->name ?? 'None'),
                'invoice_label' => (string) $settings->invoice_tax_label,
            ],
            'rate_options' => $rates->values()->map(function (TaxRate $rate) use ($defaultRateId) {
                $formatted = rtrim(rtrim(number_format((float) $rate->rate_percent, 2, '.', ''), '0'), '.');
                $selectedId = $defaultRateId !== null ? (string) $defaultRateId : '';

                return [
                    'id' => (string) $rate->id,
                    'label' => (string) ($rate->name.' ('.$formatted.'%)'),
                    'selected' => $selectedId === (string) $rate->id,
                ];
            })->all(),
            'rates' => $rates->values()->map(function (TaxRate $rate) use ($globalDateFormat) {
                $ratePercent = rtrim(rtrim(number_format((float) $rate->rate_percent, 2, '.', ''), '0'), '.');

                return [
                    'id' => $rate->id,
                    'name' => (string) $rate->name,
                    'rate_percent_display' => $ratePercent.'%',
                    'effective_from_display' => $rate->effective_from?->format($globalDateFormat) ?? '--',
                    'effective_to_display' => $rate->effective_to?->format($globalDateFormat) ?? 'Open',
                    'is_active' => (bool) $rate->is_active,
                    'status_label' => $rate->is_active ? 'Active' : 'Inactive',
                    'confirm_name' => (string) $rate->name,
                    'routes' => [
                        'edit' => route('admin.finance.tax.rates.edit', $rate),
                        'destroy' => route('admin.finance.tax.rates.destroy', $rate),
                    ],
                ];
            })->all(),
        ];
    }
}
