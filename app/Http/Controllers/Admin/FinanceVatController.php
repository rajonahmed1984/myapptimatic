<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class FinanceVatController extends Controller
{
    public function index(
        Request $request,
    ): InertiaResponse {
        $settings = TaxSetting::current();
        $rates = TaxRate::query()->orderByDesc('effective_from')->orderBy('name')->get();
        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $effectiveYear = (int) $request->query('effective_year', (int) now()->year);
        $vatAnalytics = $this->buildVatAnalytics($currencyCode, $effectiveYear);

        return Inertia::render(
            'Admin/Finance/Vat/Index',
            $this->indexInertiaProps($settings, $rates, $vatAnalytics, $currencyCode)
        );
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'tax_mode_default' => ['required', Rule::in(['inclusive', 'exclusive'])],
            'default_tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'invoice_tax_note_template' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = TaxSetting::current();
        $settings->update([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'tax_mode_default' => $data['tax_mode_default'],
            'default_tax_rate_id' => $data['default_tax_rate_id'] ?? null,
            'invoice_tax_label' => 'VAT',
            'invoice_tax_note_template' => $data['invoice_tax_note_template'] ?? null,
        ]);

        return back()->with('status', 'VAT settings updated.');
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

        return back()->with('status', 'VAT rate created.');
    }

    public function editRate(TaxRate $rate): InertiaResponse
    {
        return Inertia::render(
            'Admin/Finance/Vat/EditRate',
            $this->editRateInertiaProps($rate)
        );
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

        return redirect()->route('admin.finance.vat.index')
            ->with('status', 'VAT rate updated.');
    }

    public function destroyRate(TaxRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('status', 'VAT rate deleted.');
    }

    private function indexInertiaProps(
        TaxSetting $settings,
        Collection $rates,
        array $vatAnalytics,
        string $currencyCode
    ): array
    {
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');
        $defaultRateId = old('default_tax_rate_id', $settings->default_tax_rate_id);
        $oldEnabled = old('enabled');

        // The rate a new invoice issued today would get (same order InvoiceVatService uses).
        $today = Carbon::today();
        $defaultRate = $settings->defaultRate;
        $todayRate = $defaultRate
            && $defaultRate->is_active
            && $defaultRate->effective_from?->lessThanOrEqualTo($today)
            && (! $defaultRate->effective_to || $defaultRate->effective_to->greaterThanOrEqualTo($today))
                ? $defaultRate
                : TaxRate::query()->activeForDate($today)->orderByDesc('effective_from')->first();
        $currentRatePercent = $todayRate ? (float) $todayRate->rate_percent : null;

        return [
            'pageTitle' => 'VAT Settings',
            'heading' => 'VAT settings',
            'subheading' => 'Configure VAT mode, default rates, and invoice notes.',
            'routes' => [
                'index' => route('admin.finance.vat.index'),
                'reports' => route('admin.finance.reports.index'),
                'settings_update' => route('admin.finance.vat.update'),
                'rate_store' => route('admin.finance.vat.rates.store'),
            ],
            'settings_form' => [
                'enabled' => $oldEnabled !== null
                    ? (bool) $oldEnabled
                    : (bool) $settings->enabled,
                'tax_mode_default' => (string) old('tax_mode_default', (string) $settings->tax_mode_default),
                'default_tax_rate_id' => $defaultRateId !== null
                    ? (string) $defaultRateId
                    : '',
                'invoice_tax_label' => 'VAT',
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
                'enabled' => (bool) $settings->enabled,
                'mode' => ucfirst((string) $settings->tax_mode_default),
                'mode_key' => (string) $settings->tax_mode_default,
                'default_rate_name' => (string) ($settings->defaultRate?->name ?? 'None'),
                'current_rate_percent' => $currentRatePercent,
                'current_rate_id' => $todayRate?->id,
                'invoice_label' => 'VAT',
            ],
            'currency_code' => $currencyCode,
            'vat_analytics' => $vatAnalytics,
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
                    'effective_to_display' => $rate->effective_to?->format($globalDateFormat) ?? 'No end date',
                    'is_active' => (bool) $rate->is_active,
                    'status_label' => $rate->is_active ? 'Active' : 'Inactive',
                    'confirm_name' => (string) $rate->name,
                    'routes' => [
                        'edit' => route('admin.finance.vat.rates.edit', $rate),
                        'destroy' => route('admin.finance.vat.rates.destroy', $rate),
                    ],
                ];
            })->all(),
        ];
    }

    private function buildVatAnalytics(string $currencyCode, int $effectiveYear): array
    {
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');

        // Keep aggregation database-agnostic (sqlite/mysql) by grouping in PHP.
        // Cancelled invoices were never a supply, so their VAT is not payable.
        $invoiceRows = Invoice::query()
            ->where('currency', $currencyCode)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('issue_date')
            ->whereNotNull('tax_amount')
            ->get(['issue_date', 'tax_amount']);

        $fiscalYearBuckets = [];
        foreach ($invoiceRows as $invoice) {
            $issueDate = Carbon::parse($invoice->issue_date);
            $fiscalYear = $issueDate->month >= 7 ? $issueDate->year + 1 : $issueDate->year;
            if (! isset($fiscalYearBuckets[$fiscalYear])) {
                $fiscalYearBuckets[$fiscalYear] = [
                    'fiscal_year' => $fiscalYear,
                    'vat_total' => 0.0,
                    'invoice_count' => 0,
                ];
            }

            $fiscalYearBuckets[$fiscalYear]['vat_total'] += (float) $invoice->tax_amount;
            $fiscalYearBuckets[$fiscalYear]['invoice_count']++;
        }

        $availableYears = collect(array_keys($fiscalYearBuckets))
            ->map(fn ($year) => (int) $year)
            ->sort()
            ->values();

        $fallbackYear = $availableYears->max() ?: (int) now()->year;
        if (! $availableYears->contains($effectiveYear)) {
            $effectiveYear = $fallbackYear;
        }

        $periodStart = Carbon::create($effectiveYear - 1, 7, 1)->startOfDay();
        $periodEnd = Carbon::create($effectiveYear, 6, 30)->endOfDay();

        $monthlySource = [];
        foreach ($invoiceRows as $invoice) {
            $issueDate = Carbon::parse($invoice->issue_date);
            if (! $issueDate->between($periodStart, $periodEnd)) {
                continue;
            }

            $key = $issueDate->format('Y-m');
            if (! isset($monthlySource[$key])) {
                $monthlySource[$key] = [
                    'vat_total' => 0.0,
                    'invoice_count' => 0,
                ];
            }
            $monthlySource[$key]['vat_total'] += (float) $invoice->tax_amount;
            $monthlySource[$key]['invoice_count']++;
        }

        $monthlyRows = collect(range(0, 11))
            ->map(function (int $offset) use ($monthlySource, $periodStart, $globalDateFormat) {
                $periodDate = (clone $periodStart)->addMonths($offset);
                $key = $periodDate->format('Y-m');
                $row = $monthlySource[$key] ?? null;

                return [
                    'month_key' => $key,
                    'month_label' => $periodDate->format('M Y'),
                    'month_short' => $periodDate->format('M'),
                    'period_start' => $periodDate->format($globalDateFormat),
                    'vat_total' => (float) ($row['vat_total'] ?? 0),
                    'invoice_count' => (int) ($row['invoice_count'] ?? 0),
                ];
            })
            ->values();

        $monthlyWithChange = $monthlyRows->map(function (array $row, int $index) use ($monthlyRows) {
            $previous = $index > 0 ? (float) ($monthlyRows[$index - 1]['vat_total'] ?? 0) : null;
            $current = (float) $row['vat_total'];
            $changePercent = null;

            if ($previous !== null && $previous > 0) {
                $changePercent = (($current - $previous) / $previous) * 100;
            } elseif ($previous !== null && $previous == 0.0 && $current > 0) {
                $changePercent = 100.0;
            }

            $row['change_percent'] = $changePercent;
            $row['change_direction'] = $changePercent === null ? 'na' : ($changePercent >= 0 ? 'up' : 'down');

            return $row;
        })->values();

        $yearlyRows = collect($fiscalYearBuckets)
            ->sortKeys()
            ->values()
            ->map(function (array $row) {
                return [
                    'year' => (int) ($row['fiscal_year'] ?? 0),
                    'vat_total' => (float) ($row['vat_total'] ?? 0),
                    'invoice_count' => (int) ($row['invoice_count'] ?? 0),
                ];
            })
            ->values();

        $yearlyWithChange = $yearlyRows->map(function (array $row, int $index) use ($yearlyRows) {
            $previous = $index > 0 ? (float) ($yearlyRows[$index - 1]['vat_total'] ?? 0) : null;
            $current = (float) $row['vat_total'];
            $changePercent = null;

            if ($previous !== null && $previous > 0) {
                $changePercent = (($current - $previous) / $previous) * 100;
            } elseif ($previous !== null && $previous == 0.0 && $current > 0) {
                $changePercent = 100.0;
            }

            $row['change_percent'] = $changePercent;
            $row['change_direction'] = $changePercent === null ? 'na' : ($changePercent >= 0 ? 'up' : 'down');

            return $row;
        })->values();

        // Bangladesh fiscal year runs 1 July – 30 June and is written "2026-27";
        // the numeric value stays the ending year so existing links keep working.
        $fiscalLabel = fn (int $year): string => sprintf('%d-%02d', $year - 1, $year % 100);
        $fiscalRange = fn (int $year): string => sprintf('Jul %d – Jun %d', $year - 1, $year);

        // Always the real current month, whichever fiscal year is selected.
        $currentMonthKey = now()->format('Y-m');
        $thisMonthTotal = (float) $invoiceRows
            ->filter(fn ($invoice) => Carbon::parse($invoice->issue_date)->format('Y-m') === $currentMonthKey)
            ->sum(fn ($invoice) => (float) $invoice->tax_amount);
        $thisYearTotal = (float) ($yearlyWithChange->firstWhere('year', $effectiveYear)['vat_total'] ?? 0);
        $allTimeTotal = (float) $yearlyWithChange->sum('vat_total');

        $yearOptionsSource = $availableYears->isNotEmpty()
            ? $availableYears
            : collect([$effectiveYear]);

        return [
            'effective_year' => $effectiveYear,
            'effective_year_options' => $yearOptionsSource
                ->sortDesc()
                ->values()
                ->map(function (int $year) use ($effectiveYear, $fiscalLabel, $fiscalRange) {
                    return [
                        'value' => (string) $year,
                        'label' => sprintf('FY %s (%s)', $fiscalLabel($year), $fiscalRange($year)),
                        'selected' => $year === $effectiveYear,
                    ];
                })
                ->all(),
            'fiscal_year_label' => 'FY '.$fiscalLabel($effectiveYear),
            'fiscal_year_range' => $fiscalRange($effectiveYear),
            'current_month_label' => now()->format('M Y'),
            'period_start_display' => $periodStart->format($globalDateFormat),
            'period_end_display' => $periodEnd->format($globalDateFormat),
            'period_label' => sprintf(
                'FY %s (%s to %s)',
                $fiscalLabel($effectiveYear),
                $periodStart->format($globalDateFormat),
                $periodEnd->format($globalDateFormat)
            ),
            'summary' => [
                'this_month_total' => $thisMonthTotal,
                'this_year_total' => $thisYearTotal,
                'all_time_total' => $allTimeTotal,
            ],
            'trend' => [
                'labels' => $monthlyWithChange->pluck('month_short')->values()->all(),
                'series' => $monthlyWithChange->pluck('vat_total')->values()->all(),
            ],
            // Jul → Jun, the order the fiscal year is read in.
            'monthly_rows' => $monthlyWithChange->values()->all(),
            'yearly_rows' => $yearlyWithChange
                ->sortByDesc('year')
                ->map(function (array $row) use ($fiscalLabel, $fiscalRange) {
                    $row['label'] = 'FY '.$fiscalLabel($row['year']);
                    $row['range'] = $fiscalRange($row['year']);

                    return $row;
                })
                ->values()
                ->all(),
        ];
    }

    private function editRateInertiaProps(TaxRate $rate): array
    {
        return [
            'pageTitle' => 'Edit VAT Rate',
            'rate' => [
                'id' => $rate->id,
                'name' => (string) old('name', (string) $rate->name),
                'rate_percent' => (string) old('rate_percent', (string) $rate->rate_percent),
                'effective_from' => (string) old('effective_from', (string) $rate->effective_from?->toDateString()),
                'effective_to' => (string) old('effective_to', (string) ($rate->effective_to?->toDateString() ?? '')),
                'is_active' => (bool) old('is_active', (bool) $rate->is_active),
            ],
            'routes' => [
                'index' => route('admin.finance.vat.index'),
                'update' => route('admin.finance.vat.rates.update', $rate),
            ],
        ];
    }
}
