<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayout;
use App\Models\EmployeeWorkSession;
use App\Models\PayrollItem;
use App\Services\EmployeeWorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request, EmployeeWorkSummaryService $workSummaryService): View
    {
        $employee = $request->attributes->get('employee');
        $employee->loadMissing('activeCompensation');

        $items = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->with('period')
            ->orderByDesc('id')
            ->paginate(15);

        $paidAmountByItem = [];
        $advancePaidByItem = [];
        $monthlyPayouts = [];

        $periodBounds = $items->getCollection()
            ->filter(fn ($item) => $item->period?->start_date && $item->period?->end_date);

        if ($periodBounds->isNotEmpty()) {
            $rangeStart = $periodBounds->min(fn ($item) => $item->period->start_date->toDateString());
            $rangeEnd = $periodBounds->max(fn ($item) => $item->period->end_date->toDateString());

            $payouts = EmployeePayout::query()
                ->where('employee_id', $employee->id)
                ->whereDate('paid_at', '>=', $rangeStart)
                ->whereDate('paid_at', '<=', $rangeEnd)
                ->get(['amount', 'currency', 'metadata', 'paid_at']);

            foreach ($payouts as $payout) {
                if (! $payout->paid_at) {
                    continue;
                }

                $periodKey = $payout->paid_at->format('Y-m');
                $currency = (string) ($payout->currency ?? 'BDT');
                $isAdvance = data_get($payout->metadata, 'type') === 'advance';

                if (! isset($monthlyPayouts[$periodKey][$currency])) {
                    $monthlyPayouts[$periodKey][$currency] = [
                        'paid' => 0.0,
                        'advance' => 0.0,
                    ];
                }

                if ($isAdvance) {
                    $monthlyPayouts[$periodKey][$currency]['advance'] += (float) ($payout->amount ?? 0);
                } else {
                    $monthlyPayouts[$periodKey][$currency]['paid'] += (float) ($payout->amount ?? 0);
                }
            }
        }

        $estimatedSalaryByItem = [];
        $estimatedCurrency = $employee->activeCompensation?->currency ?? 'BDT';
        $workSessionEligible = $workSummaryService->isEligible($employee);

        foreach ($items as $item) {
            $periodKey = $item->period?->period_key;
            $rowCurrency = (string) ($item->currency ?? 'BDT');
            $paidAmountByItem[$item->id] = round((float) ($monthlyPayouts[$periodKey][$rowCurrency]['paid'] ?? 0), 2);
            $advancePaidByItem[$item->id] = round((float) ($monthlyPayouts[$periodKey][$rowCurrency]['advance'] ?? 0), 2);

            if (! $workSessionEligible || ! $item->period?->start_date || ! $item->period?->end_date) {
                $estimatedSalaryByItem[$item->id] = 0.0;
                continue;
            }

            $rows = EmployeeWorkSession::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', '>=', $item->period->start_date->toDateString())
                ->whereDate('work_date', '<=', $item->period->end_date->toDateString())
                ->selectRaw('work_date, SUM(active_seconds) as active_seconds')
                ->groupBy('work_date')
                ->get();

            $subtotal = 0.0;
            foreach ($rows as $row) {
                $subtotal += $workSummaryService->calculateAmount(
                    $employee,
                    Carbon::parse((string) $row->work_date),
                    (int) ($row->active_seconds ?? 0)
                );
            }

            $estimatedSalaryByItem[$item->id] = round($subtotal, 2);
        }

        return view('employee.payroll.index', compact(
            'items',
            'estimatedSalaryByItem',
            'estimatedCurrency',
            'workSessionEligible',
            'paidAmountByItem',
            'advancePaidByItem'
        ));
    }
}
