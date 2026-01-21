<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use Carbon\Carbon;

class InvoiceTaxService
{
    public function calculateTotals(float $subtotal, float $lateFee, Carbon $issueDate, ?Invoice $invoice = null): array
    {
        $settings = TaxSetting::current();
        [$mode, $ratePercent] = $this->resolveTaxConfig($settings, $issueDate, $invoice);

        $baseTotal = $subtotal + $lateFee;
        if (! $mode || ! $ratePercent || $ratePercent <= 0) {
            return [
                'tax_rate_percent' => null,
                'tax_mode' => null,
                'tax_amount' => null,
                'total' => round($baseTotal, 2),
            ];
        }

        $ratePercent = (float) $ratePercent;
        $taxAmount = 0.0;
        $total = $baseTotal;

        if ($mode === 'inclusive') {
            $taxAmount = $subtotal * ($ratePercent / (100 + $ratePercent));
            $total = $baseTotal;
        } else {
            $taxAmount = $subtotal * ($ratePercent / 100);
            $total = $subtotal + $taxAmount + $lateFee;
        }

        return [
            'tax_rate_percent' => round($ratePercent, 2),
            'tax_mode' => $mode,
            'tax_amount' => round($taxAmount, 2),
            'total' => round($total, 2),
        ];
    }

    public function applyToInvoice(Invoice $invoice): Invoice
    {
        $issueDate = $invoice->issue_date
            ? Carbon::parse($invoice->issue_date)
            : Carbon::today();

        $data = $this->calculateTotals(
            (float) $invoice->subtotal,
            (float) ($invoice->late_fee ?? 0),
            $issueDate,
            $invoice
        );

        $invoice->update($data);

        return $invoice;
    }

    private function resolveTaxConfig(TaxSetting $settings, Carbon $issueDate, ?Invoice $invoice = null): array
    {
        $mode = null;
        $ratePercent = null;

        if ($invoice && ($invoice->tax_rate_percent !== null || $invoice->tax_mode !== null)) {
            $mode = $invoice->tax_mode ?: $settings->tax_mode_default;
            $ratePercent = $invoice->tax_rate_percent;
        }

        if ($ratePercent === null) {
            if (! $settings->enabled) {
                return [null, null];
            }

            $ratePercent = $this->resolveRatePercent($settings, $issueDate);
            $mode = $mode ?: $settings->tax_mode_default;
        }

        if (! $ratePercent) {
            return [null, null];
        }

        $mode = in_array($mode, ['inclusive', 'exclusive'], true) ? $mode : 'exclusive';

        return [$mode, (float) $ratePercent];
    }

    private function resolveRatePercent(TaxSetting $settings, Carbon $issueDate): ?float
    {
        if ($settings->default_tax_rate_id) {
            $default = TaxRate::query()->find($settings->default_tax_rate_id);
            if ($default && $default->is_active) {
                $inRange = $default->effective_from
                    && $default->effective_from->lessThanOrEqualTo($issueDate)
                    && (! $default->effective_to || $default->effective_to->greaterThanOrEqualTo($issueDate));
                if ($inRange) {
                    return (float) $default->rate_percent;
                }
            }
        }

        $rate = TaxRate::query()
            ->activeForDate($issueDate)
            ->orderByDesc('effective_from')
            ->first();

        return $rate?->rate_percent !== null ? (float) $rate->rate_percent : null;
    }
}
