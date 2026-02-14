<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeWorkSession;
use App\Models\PayrollPeriod;
use App\Models\PayrollItem;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\EmployeeWorkSummaryService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('payrollItems as items_count')
            ->orderByDesc('start_date')
            ->paginate(20);

        return view('admin.hr.payroll.index', compact('periods'));
    }

    public function generate(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'period_key' => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
        ]);

        $service->generatePeriod($data['period_key']);

        return back()->with('status', 'Payroll generated for '.$data['period_key']);
    }

    public function finalize(PayrollPeriod $payrollPeriod, PayrollService $service): RedirectResponse
    {
        $service->finalizePeriod($payrollPeriod);

        return back()->with('status', 'Payroll period finalized.');
    }

    public function updateAdjustments(Request $request, PayrollPeriod $payrollPeriod, PayrollItem $payrollItem): RedirectResponse
    {
        if ($payrollItem->payroll_period_id !== $payrollPeriod->id) {
            abort(404);
        }

        $this->authorize('update', $payrollItem);

        if ($payrollPeriod->status !== 'draft') {
            return back()->withErrors(['payroll' => 'Only draft payroll periods can be adjusted.']);
        }

        $data = $request->validate([
            'bonuses' => ['nullable', 'numeric', 'min:0'],
            'penalties' => ['nullable', 'numeric', 'min:0'],
        ]);

        $newBonus = (float) ($data['bonuses'] ?? 0);
        $newPenalty = (float) ($data['penalties'] ?? 0);

        $currentBonus = $this->sumAdjustment($payrollItem->bonuses);
        $currentPenalty = $this->sumAdjustment($payrollItem->penalties);
        $currentAdvance = $this->sumAdjustment($payrollItem->advances);
        $currentDeduction = $this->sumAdjustment($payrollItem->deductions);
        $currentOvertimePay = (bool) ($payrollItem->overtime_enabled ?? false)
            ? ((float) ($payrollItem->overtime_hours ?? 0) * (float) ($payrollItem->overtime_rate ?? 0))
            : 0.0;

        // Reconstruct base gross so repeated edits don't stack previous bonus/overtime again.
        $baseGross = max(0, (float) $payrollItem->gross_pay - $currentBonus - $currentOvertimePay);
        $gross = round($baseGross + $newBonus + $currentOvertimePay, 2, PHP_ROUND_HALF_UP);
        $net = round($gross - $newPenalty - $currentAdvance - $currentDeduction, 2, PHP_ROUND_HALF_UP);

        $payrollItem->update([
            'bonuses' => $newBonus,
            'penalties' => $newPenalty,
            'gross_pay' => $gross,
            'net_pay' => $net,
        ]);

        return back()->with('status', 'Payroll adjustments updated for '.$payrollItem->employee?->name.'.');
    }

    public function show(PayrollPeriod $payrollPeriod, EmployeeWorkSummaryService $workSummaryService): View
    {
        $items = PayrollItem::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->with('employee')
            ->orderBy('employee_id')
            ->paginate(50)
            ->withQueryString();

        $totals = PayrollItem::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->selectRaw('currency, SUM(base_pay) as base_total, SUM(gross_pay) as gross_total, SUM(net_pay) as net_total')
            ->groupBy('currency')
            ->get();

        $employeeIds = $items->getCollection()
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();

        $workLogSubtotalByEmployee = [];
        if ($employeeIds->isNotEmpty()) {
            $employees = Employee::query()
                ->whereIn('id', $employeeIds)
                ->with('activeCompensation')
                ->get()
                ->keyBy('id');

            $workRows = EmployeeWorkSession::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('work_date', '>=', $payrollPeriod->start_date?->toDateString())
                ->whereDate('work_date', '<=', $payrollPeriod->end_date?->toDateString())
                ->selectRaw('employee_id, work_date, SUM(active_seconds) as active_seconds')
                ->groupBy('employee_id', 'work_date')
                ->orderBy('work_date')
                ->get()
                ->groupBy('employee_id');

            foreach ($employeeIds as $employeeId) {
                $employee = $employees->get($employeeId);
                if (! $employee || ! $workSummaryService->isEligible($employee)) {
                    $workLogSubtotalByEmployee[$employeeId] = [
                        'eligible' => false,
                        'amount' => 0.0,
                        'currency' => $employee?->activeCompensation?->currency ?? 'BDT',
                    ];
                    continue;
                }

                $subtotal = 0.0;
                foreach (($workRows->get($employeeId) ?? collect()) as $row) {
                    $subtotal += $workSummaryService->calculateAmount(
                        $employee,
                        Carbon::parse((string) $row->work_date),
                        (int) ($row->active_seconds ?? 0)
                    );
                }

                $workLogSubtotalByEmployee[$employeeId] = [
                    'eligible' => true,
                    'amount' => round($subtotal, 2),
                    'currency' => $employee->activeCompensation?->currency ?? 'BDT',
                ];
            }
        }

        return view('admin.hr.payroll.show', [
            'period' => $payrollPeriod,
            'items' => $items,
            'totals' => $totals,
            'workLogSubtotalByEmployee' => $workLogSubtotalByEmployee,
        ]);
    }

    public function export(PayrollPeriod $payrollPeriod): StreamedResponse
    {
        $filename = 'payroll-'.$payrollPeriod->period_key.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($payrollPeriod) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee',
                'Status',
                'Pay Type',
                'Currency',
                'Base Pay',
                'Timesheet Hours',
                'Overtime Hours',
                'Overtime Rate',
                'Bonuses',
                'Penalties',
                'Advances',
                'Deductions',
                'Gross',
                'Net',
                'Payment Reference',
                'Paid At',
            ]);

            $payrollPeriod->payrollItems()
                ->with('employee')
                ->chunk(200, function ($items) use ($handle) {
                    foreach ($items as $item) {
                        fputcsv($handle, [
                            $item->employee?->name ?? 'N/A',
                            $item->status,
                            $item->pay_type,
                            $item->currency,
                            $item->base_pay,
                            $item->timesheet_hours,
                            $item->overtime_hours,
                            $item->overtime_rate,
                            $item->bonuses ? json_encode($item->bonuses) : '',
                            $item->penalties ? json_encode($item->penalties) : '',
                            $item->advances ? json_encode($item->advances) : '',
                            $item->deductions ? json_encode($item->deductions) : '',
                            $item->gross_pay,
                            $item->net_pay,
                            $item->payment_reference,
                            optional($item->paid_at)->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    private function sumAdjustment($value): float
    {
        if (is_array($value)) {
            return (float) array_reduce($value, function ($carry, $row) {
                return $carry + (float) ($row['amount'] ?? $row ?? 0);
            }, 0.0);
        }

        return (float) ($value ?? 0);
    }
}
