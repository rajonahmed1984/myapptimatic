<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayrollController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $employee = $request->attributes->get('employee');

        $items = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->with('period')
            ->orderByDesc('id')
            ->paginate(15);

        $paidAmountByItem = [];
        $bonusByItem = [];
        $penaltyByItem = [];
        $advancePaidByItem = [];
        $deductionByItem = [];
        $netPayableByItem = [];
        $remainingByItem = [];

        foreach ($items as $item) {
            $paidAmount = round((float) ($item->paid_amount ?? 0), 2);
            $bonus = round($this->sumAdjustment($item->bonuses), 2);
            $penalty = round($this->sumAdjustment($item->penalties), 2);
            $advancePaidByItem[$item->id] = round($this->sumAdjustment($item->advances), 2);
            $deduction = round($this->sumAdjustment($item->deductions), 2);
            $netPayable = round(max(0, (float) ($item->net_pay ?? 0)), 2);

            $paidAmountByItem[$item->id] = $paidAmount;
            $bonusByItem[$item->id] = $bonus;
            $penaltyByItem[$item->id] = $penalty;
            $deductionByItem[$item->id] = $deduction;
            $netPayableByItem[$item->id] = $netPayable;
            $remainingByItem[$item->id] = round(max(0, $netPayable - $paidAmount), 2);
        }

        return Inertia::render('Employee/Payroll/Index', [
            'items' => $items->getCollection()->map(function (PayrollItem $item) use (
                $paidAmountByItem,
                $bonusByItem,
                $penaltyByItem,
                $advancePaidByItem,
                $deductionByItem,
                $netPayableByItem,
                $remainingByItem
            ) {
                $dateFormat = config('app.date_format', 'd-m-Y');

                return [
                    'id' => $item->id,
                    'period_key' => $item->period?->period_key ?? '--',
                    'gross_pay' => (float) ($item->gross_pay ?? 0),
                    'bonus' => (float) ($bonusByItem[$item->id] ?? 0),
                    'penalty' => (float) ($penaltyByItem[$item->id] ?? 0),
                    'advance' => (float) ($advancePaidByItem[$item->id] ?? 0),
                    'deduction' => (float) ($deductionByItem[$item->id] ?? 0),
                    'net_payable' => (float) ($netPayableByItem[$item->id] ?? 0),
                    'paid' => (float) ($paidAmountByItem[$item->id] ?? 0),
                    'remaining' => (float) ($remainingByItem[$item->id] ?? 0),
                    'status_label' => ucfirst((string) $item->status),
                    'paid_at_display' => $item->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'currency' => $item->currency,
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
                'prev_page_url' => $items->previousPageUrl(),
                'next_page_url' => $items->nextPageUrl(),
            ],
        ]);
    }

    private function sumAdjustment(mixed $value): float
    {
        if (is_array($value)) {
            return (float) array_sum(array_map(
                fn ($row) => (float) (is_array($row) ? ($row['amount'] ?? 0) : $row),
                $value
            ));
        }

        return (float) ($value ?? 0);
    }
}
