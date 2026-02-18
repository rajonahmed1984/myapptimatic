<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
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

        return view('employee.payroll.index', compact(
            'items',
            'paidAmountByItem',
            'bonusByItem',
            'penaltyByItem',
            'advancePaidByItem',
            'deductionByItem',
            'netPayableByItem',
            'remainingByItem'
        ));
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
