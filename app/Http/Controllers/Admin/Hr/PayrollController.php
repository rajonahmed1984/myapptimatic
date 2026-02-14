<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSession;
use App\Models\PaidHoliday;
use App\Models\PaymentMethod;
use App\Models\PayrollAuditLog;
use App\Models\PayrollPeriod;
use App\Models\PayrollItem;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'period_key' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['nullable', 'in:draft,finalized'],
        ]);

        $selectedPeriodKey = $filters['period_key'] ?? null;
        $selectedStatus = $filters['status'] ?? null;

        $periods = PayrollPeriod::query()
            ->withCount([
                'payrollItems as items_count',
                'payrollItems as approved_items_count' => fn ($q) => $q->whereIn('status', ['approved', 'partial']),
                'payrollItems as paid_items_count' => fn ($q) => $q->where('status', 'paid'),
            ])
            ->when($selectedPeriodKey, fn ($q) => $q->where('period_key', $selectedPeriodKey))
            ->when($selectedStatus, fn ($q) => $q->where('status', $selectedStatus))
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'draft_periods' => PayrollPeriod::query()->where('status', 'draft')->count(),
            'finalized_periods' => PayrollPeriod::query()->where('status', 'finalized')->count(),
            'approved_items_to_pay' => PayrollItem::query()->whereIn('status', ['approved', 'partial'])->count(),
            'paid_items' => PayrollItem::query()->where('status', 'paid')->count(),
        ];

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $workLogDaysThisMonth = EmployeeWorkSession::query()
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('employee_id, work_date')
            ->groupBy('employee_id', 'work_date')
            ->get()
            ->count();

        $attendanceMarkedToday = EmployeeAttendance::query()
            ->whereDate('date', today()->toDateString())
            ->count();

        $paidHolidaysThisMonth = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$monthStart, $monthEnd])
            ->count();

        return view('admin.hr.payroll.index', compact(
            'periods',
            'summary',
            'workLogDaysThisMonth',
            'attendanceMarkedToday',
            'paidHolidaysThisMonth',
            'selectedPeriodKey',
            'selectedStatus'
        ));
    }

    public function generate(Request $request, PayrollService $service): RedirectResponse
    {
        $data = $request->validate([
            'period_key' => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
        ]);

        $periodStart = Carbon::createFromFormat('Y-m', $data['period_key'])->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        if ($periodEnd->gte(today()->startOfDay())) {
            return back()->withErrors(['period_key' => 'Payroll can be generated only after the selected month is completed.']);
        }

        $service->generatePeriod($data['period_key']);

        return back()->with('status', 'Payroll generated for '.$data['period_key']);
    }

    public function edit(PayrollPeriod $payrollPeriod): View
    {
        if ($payrollPeriod->status !== 'draft') {
            abort(404);
        }

        return view('admin.hr.payroll.edit', [
            'period' => $payrollPeriod,
        ]);
    }

    public function update(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->status !== 'draft') {
            return back()->withErrors(['payroll' => 'Only draft payroll periods can be edited.']);
        }

        $data = $request->validate([
            'period_key' => ['required', 'regex:/^\d{4}-\d{2}$/', 'unique:payroll_periods,period_key,' . $payrollPeriod->id],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $payrollPeriod->update([
            'period_key' => $data['period_key'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ]);

        return redirect()
            ->route('admin.hr.payroll.index')
            ->with('status', 'Payroll period updated.');
    }

    public function destroy(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->status !== 'draft') {
            return back()->withErrors(['payroll' => 'Only draft payroll periods can be deleted.']);
        }

        $label = (string) $payrollPeriod->period_key;
        $payrollPeriod->delete();

        return back()->with('status', 'Payroll period deleted: '.$label);
    }

    public function finalize(PayrollPeriod $payrollPeriod, PayrollService $service): RedirectResponse
    {
        if ($payrollPeriod->end_date && $payrollPeriod->end_date->gte(today()->startOfDay())) {
            return back()->withErrors(['payroll' => 'Payroll period cannot be finalized before month end.']);
        }

        $service->finalizePeriod($payrollPeriod);

        return back()->with('status', 'Payroll period finalized.');
    }

    public function markPaid(Request $request, PayrollPeriod $payrollPeriod, PayrollItem $payrollItem): RedirectResponse
    {
        if ($payrollItem->payroll_period_id !== $payrollPeriod->id) {
            abort(404);
        }

        if (! in_array($payrollItem->status, ['approved', 'partial'], true)) {
            return back()->withErrors(['payroll' => 'Only approved/partial payroll items can be paid.']);
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentMethod::allowedCodes())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $methodLabel = ucfirst((string) $data['payment_method']);
        $rawReference = isset($data['payment_reference']) ? trim((string) $data['payment_reference']) : '';
        $composedReference = $rawReference !== '' ? ($methodLabel.' - '.$rawReference) : $methodLabel;
        $paymentAmount = round((float) $data['amount'], 2, PHP_ROUND_HALF_UP);
        $paidAt = Carbon::parse((string) $data['paid_at'])->startOfDay();

        $result = DB::transaction(function () use ($request, $payrollPeriod, $payrollItem, $paymentAmount, $paidAt, $composedReference) {
            /** @var PayrollItem $item */
            $item = PayrollItem::query()->lockForUpdate()->findOrFail($payrollItem->id);

            if (! in_array($item->status, ['approved', 'partial'], true)) {
                return [
                    'ok' => false,
                    'message' => 'Only approved/partial payroll items can receive payments.',
                ];
            }

            $netPayable = $this->payableNetAmount($payrollPeriod, $item);
            $alreadyPaid = round((float) ($item->paid_amount ?? 0), 2, PHP_ROUND_HALF_UP);
            $remainingBefore = round(max(0, $netPayable - $alreadyPaid), 2, PHP_ROUND_HALF_UP);

            if ($remainingBefore <= 0) {
                return [
                    'ok' => false,
                    'message' => 'This payroll item is already fully paid.',
                ];
            }

            if ($paymentAmount > $remainingBefore) {
                return [
                    'ok' => false,
                    'message' => 'Amount exceeds remaining payable amount ('.number_format($remainingBefore, 2).').',
                ];
            }

            $newPaidAmount = round($alreadyPaid + $paymentAmount, 2, PHP_ROUND_HALF_UP);
            $remainingAfter = round(max(0, $netPayable - $newPaidAmount), 2, PHP_ROUND_HALF_UP);
            $isFullyPaid = $remainingAfter <= 0.009;
            $oldStatus = (string) $item->status;

            $proofPath = null;
            $proofName = null;
            $proofMime = null;
            if ($request->hasFile('payment_proof')) {
                $file = $request->file('payment_proof');
                $proofPath = $file?->store('payroll-payment-proofs', 'public');
                $proofName = $file?->getClientOriginalName();
                $proofMime = $file?->getClientMimeType();
            }

            $item->update([
                'status' => $isFullyPaid ? 'paid' : 'partial',
                'paid_amount' => $newPaidAmount,
                'paid_at' => $isFullyPaid ? $paidAt : null,
                'payment_reference' => $composedReference,
            ]);

            PayrollAuditLog::create([
                'payroll_item_id' => $item->id,
                'event' => $isFullyPaid ? 'payment_completed' : 'payment_partial',
                'old_status' => $oldStatus,
                'new_status' => $isFullyPaid ? 'paid' : 'partial',
                'user_id' => auth()->id(),
                'meta' => [
                    'amount' => $paymentAmount,
                    'net_payable' => $netPayable,
                    'paid_before' => $alreadyPaid,
                    'paid_after' => $newPaidAmount,
                    'remaining_before' => $remainingBefore,
                    'remaining_after' => $remainingAfter,
                    'paid_at' => $paidAt->toDateString(),
                    'reference' => $composedReference,
                    'proof_path' => $proofPath,
                    'proof_name' => $proofName,
                    'proof_mime' => $proofMime,
                ],
            ]);

            return [
                'ok' => true,
                'remaining_after' => $remainingAfter,
                'is_fully_paid' => $isFullyPaid,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['payroll' => $result['message'] ?? 'Payment failed.']);
        }

        if (($result['is_fully_paid'] ?? false) === true) {
            return back()->with('status', 'Payroll fully paid for '.$payrollItem->employee?->name.'.');
        }

        return back()->with('status', 'Partial payment saved. Remaining: '.number_format((float) ($result['remaining_after'] ?? 0), 2));
    }

    public function updateAdjustments(Request $request, PayrollPeriod $payrollPeriod, PayrollItem $payrollItem): RedirectResponse
    {
        if ($payrollItem->payroll_period_id !== $payrollPeriod->id) {
            abort(404);
        }

        $this->authorize('update', $payrollItem);

        if ($payrollItem->status === 'paid') {
            return back()->withErrors(['payroll' => 'Paid payroll items cannot be adjusted.']);
        }

        $data = $request->validate([
            'overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'bonuses' => ['nullable', 'numeric', 'min:0'],
            'penalties' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'deduction_reference' => ['nullable', 'string', 'max:120'],
            'deduction_note' => ['nullable', 'string', 'max:500'],
        ]);

        $newOvertimeHours = (float) ($data['overtime_hours'] ?? 0);
        $newOvertimeRate = (float) ($data['overtime_rate'] ?? $payrollItem->overtime_rate ?? 0);
        $newBonus = (float) ($data['bonuses'] ?? 0);
        $newPenalty = (float) ($data['penalties'] ?? 0);
        $newDeduction = (float) ($data['deductions'] ?? 0);
        $newDeductionReference = trim((string) ($data['deduction_reference'] ?? ''));
        $newDeductionNote = trim((string) ($data['deduction_note'] ?? ''));

        $currentBonus = $this->sumAdjustment($payrollItem->bonuses);
        $currentPenalty = $this->sumAdjustment($payrollItem->penalties);
        $currentAdvance = $this->sumAdjustment($payrollItem->advances);
        $allowOvertime = (bool) ($payrollItem->overtime_enabled ?? false);
        $currentOvertimePay = $allowOvertime
            ? ((float) ($payrollItem->overtime_hours ?? 0) * (float) ($payrollItem->overtime_rate ?? 0))
            : 0.0;
        $newOvertimePay = $allowOvertime
            ? ($newOvertimeHours * $newOvertimeRate)
            : 0.0;

        // Reconstruct base gross so repeated edits don't stack previous bonus/overtime again.
        $baseGross = max(0, (float) $payrollItem->gross_pay - $currentBonus - $currentOvertimePay);
        $gross = round($baseGross + $newBonus + $newOvertimePay, 2, PHP_ROUND_HALF_UP);
        $net = round($gross - $newPenalty - $currentAdvance - $newDeduction, 2, PHP_ROUND_HALF_UP);

        $deductionsPayload = $newDeduction > 0
            ? [[
                'amount' => $newDeduction,
                'reference' => $newDeductionReference !== '' ? $newDeductionReference : null,
                'note' => $newDeductionNote !== '' ? $newDeductionNote : null,
            ]]
            : [];
        $deductionsValue = $this->usesJsonDeductionsColumn() ? $deductionsPayload : $newDeduction;

        $payrollItem->update([
            'overtime_hours' => $allowOvertime ? $newOvertimeHours : 0,
            'overtime_rate' => $allowOvertime ? $newOvertimeRate : 0,
            'bonuses' => $newBonus,
            'penalties' => $newPenalty,
            'deductions' => $deductionsValue,
            'gross_pay' => $gross,
            'net_pay' => $net,
        ]);

        return back()->with('status', 'Payroll adjustments updated for '.$payrollItem->employee?->name.'.');
    }

    public function show(PayrollPeriod $payrollPeriod): View
    {
        $items = PayrollItem::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->with('employee')
            ->orderBy('employee_id')
            ->paginate(50)
            ->withQueryString();

        $allItems = PayrollItem::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->with('employee:id,employment_type')
            ->get([
                'id',
                'employee_id',
                'currency',
                'pay_type',
                'base_pay',
                'timesheet_hours',
                'overtime_hours',
                'overtime_rate',
                'bonuses',
                'penalties',
                'advances',
                'deductions',
            ]);

        $employeeIds = $allItems
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();

        $workLogHoursByEmployee = [];
        $absentDaysByEmployee = [];
        $attendanceSummaryByEmployee = [];
        $workingDaysInPeriod = 0;
        if ($employeeIds->isNotEmpty()) {
            $absentDaysByEmployee = EmployeeAttendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('date', '>=', $payrollPeriod->start_date?->toDateString())
                ->whereDate('date', '<=', $payrollPeriod->end_date?->toDateString())
                ->where('status', 'absent')
                ->selectRaw('employee_id, COUNT(*) as total_absent')
                ->groupBy('employee_id')
                ->pluck('total_absent', 'employee_id')
                ->map(fn ($count) => (int) $count)
                ->all();

            $paidHolidayDates = PaidHoliday::query()
                ->where('is_paid', true)
                ->whereBetween('holiday_date', [
                    $payrollPeriod->start_date?->toDateString(),
                    $payrollPeriod->end_date?->toDateString(),
                ])
                ->pluck('holiday_date')
                ->map(fn ($d) => Carbon::parse((string) $d)->toDateString())
                ->all();
            $paidHolidayMap = array_fill_keys($paidHolidayDates, true);

            $cursor = $payrollPeriod->start_date?->copy()->startOfDay();
            $periodEnd = $payrollPeriod->end_date?->copy()->startOfDay();
            while ($cursor && $periodEnd && $cursor->lessThanOrEqualTo($periodEnd)) {
                if (! isset($paidHolidayMap[$cursor->toDateString()])) {
                    $workingDaysInPeriod++;
                }
                $cursor->addDay();
            }

            $attendanceRows = EmployeeAttendance::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('date', '>=', $payrollPeriod->start_date?->toDateString())
                ->whereDate('date', '<=', $payrollPeriod->end_date?->toDateString())
                ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('date', $paidHolidayDates))
                ->get(['employee_id', 'status']);

            foreach ($employeeIds as $employeeId) {
                $rows = $attendanceRows->where('employee_id', $employeeId);
                $presentDays = (float) $rows->where('status', 'present')->count();
                $halfDays = (float) $rows->where('status', 'half_day')->count() * 0.5;
                $workedDays = round($presentDays + $halfDays, 1);

                $attendanceSummaryByEmployee[$employeeId] = [
                    'worked_days' => $workedDays,
                    'working_days' => $workingDaysInPeriod,
                ];
            }

            $employees = Employee::query()
                ->whereIn('id', $employeeIds)
                ->get()
                ->keyBy('id');

            $workRows = EmployeeWorkSession::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('work_date', '>=', $payrollPeriod->start_date?->toDateString())
                ->whereDate('work_date', '<=', $payrollPeriod->end_date?->toDateString())
                ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('work_date', $paidHolidayDates))
                ->selectRaw('employee_id, work_date, SUM(active_seconds) as active_seconds')
                ->groupBy('employee_id', 'work_date')
                ->orderBy('work_date')
                ->get()
                ->groupBy('employee_id');

            foreach ($employeeIds as $employeeId) {
                $employee = $employees->get($employeeId);
                $employeeWorkRows = $workRows->get($employeeId) ?? collect();
                $totalSeconds = (int) $employeeWorkRows->sum(fn ($row) => (int) ($row->active_seconds ?? 0));
                $workLogHoursByEmployee[$employeeId] = round($totalSeconds / 3600, 2, PHP_ROUND_HALF_UP);
            }
        }

        $computedTotalsByCurrency = [];
        foreach ($allItems as $item) {
            $employmentType = $item->employee?->employment_type;
            $isHoursBased = $item->pay_type === 'hourly' || $employmentType === 'part_time';
            $isAttendanceBased = $employmentType === 'full_time';
            $hoursPerDay = $employmentType === 'part_time' ? 4 : 8;

            $actualHours = (float) ($workLogHoursByEmployee[$item->employee_id] ?? 0);
            if ($actualHours <= 0) {
                $actualHours = (float) ($item->timesheet_hours ?? 0);
            }

            $attendance = $attendanceSummaryByEmployee[$item->employee_id] ?? null;
            $workedDays = (float) ($attendance['worked_days'] ?? 0);
            $totalWorkingDays = (int) ($attendance['working_days'] ?? ($workingDaysInPeriod ?? 0));
            $expectedHours = max(0, $totalWorkingDays) * $hoursPerDay;

            $basePay = (float) ($item->base_pay ?? 0);
            $estSubtotal = $basePay;
            if ($isHoursBased) {
                $hoursRatio = $expectedHours > 0 ? min(1, max(0, $actualHours / $expectedHours)) : 0;
                $estSubtotal = $basePay * $hoursRatio;
            } elseif ($isAttendanceBased) {
                $attendanceRatio = $totalWorkingDays > 0 ? min(1, max(0, $workedDays / $totalWorkingDays)) : 0;
                $estSubtotal = $basePay * $attendanceRatio;
            }
            $estSubtotal = round($estSubtotal, 2, PHP_ROUND_HALF_UP);

            $bonus = $this->sumAdjustment($item->bonuses);
            $penalty = $this->sumAdjustment($item->penalties);
            $advance = $this->sumAdjustment($item->advances);
            $deduction = $this->sumAdjustment($item->deductions);
            $overtimePay = (float) ($item->overtime_hours ?? 0) * (float) ($item->overtime_rate ?? 0);
            $computedGross = round($estSubtotal + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
            $computedNet = round($computedGross - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);

            $currency = (string) ($item->currency ?? 'BDT');
            if (! isset($computedTotalsByCurrency[$currency])) {
                $computedTotalsByCurrency[$currency] = [
                    'currency' => $currency,
                    'base_total' => 0.0,
                    'gross_total' => 0.0,
                    'net_total' => 0.0,
                ];
            }

            $computedTotalsByCurrency[$currency]['base_total'] += $basePay;
            $computedTotalsByCurrency[$currency]['gross_total'] += $computedGross;
            $computedTotalsByCurrency[$currency]['net_total'] += $computedNet;
        }

        $totals = collect($computedTotalsByCurrency)
            ->map(function (array $row) {
                return (object) [
                    'currency' => $row['currency'],
                    'base_total' => round((float) $row['base_total'], 2, PHP_ROUND_HALF_UP),
                    'gross_total' => round((float) $row['gross_total'], 2, PHP_ROUND_HALF_UP),
                    'net_total' => round((float) $row['net_total'], 2, PHP_ROUND_HALF_UP),
                ];
            })
            ->values();

        return view('admin.hr.payroll.show', [
            'period' => $payrollPeriod,
            'items' => $items,
            'totals' => $totals,
            'workLogHoursByEmployee' => $workLogHoursByEmployee,
            'absentDaysByEmployee' => $absentDaysByEmployee,
            'attendanceSummaryByEmployee' => $attendanceSummaryByEmployee,
            'workingDaysInPeriod' => $workingDaysInPeriod,
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

    private function payableNetAmount(PayrollPeriod $period, PayrollItem $item): float
    {
        $item->loadMissing('employee:id,employment_type');

        $startDate = $period->start_date?->toDateString();
        $endDate = $period->end_date?->toDateString();
        if (! $startDate || ! $endDate) {
            return round(max(0, (float) ($item->net_pay ?? 0)), 2, PHP_ROUND_HALF_UP);
        }

        $paidHolidayDates = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse((string) $d)->toDateString())
            ->all();
        $paidHolidayMap = array_fill_keys($paidHolidayDates, true);

        $workingDaysInPeriod = 0;
        $cursor = $period->start_date?->copy()->startOfDay();
        $periodEnd = $period->end_date?->copy()->startOfDay();
        while ($cursor && $periodEnd && $cursor->lessThanOrEqualTo($periodEnd)) {
            if (! isset($paidHolidayMap[$cursor->toDateString()])) {
                $workingDaysInPeriod++;
            }
            $cursor->addDay();
        }

        $employmentType = $item->employee?->employment_type;
        $isHoursBased = $item->pay_type === 'hourly' || $employmentType === 'part_time';
        $isAttendanceBased = $employmentType === 'full_time';
        $hoursPerDay = $employmentType === 'part_time' ? 4 : 8;

        $totalWorkSeconds = (int) EmployeeWorkSession::query()
            ->where('employee_id', $item->employee_id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('work_date', $paidHolidayDates))
            ->sum('active_seconds');
        $workLogHours = round($totalWorkSeconds / 3600, 2, PHP_ROUND_HALF_UP);

        $presentDays = (float) EmployeeAttendance::query()
            ->where('employee_id', $item->employee_id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('date', $paidHolidayDates))
            ->where('status', 'present')
            ->count();
        $halfDays = (float) EmployeeAttendance::query()
            ->where('employee_id', $item->employee_id)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->when(! empty($paidHolidayDates), fn ($q) => $q->whereNotIn('date', $paidHolidayDates))
            ->where('status', 'half_day')
            ->count() * 0.5;
        $workedDays = round($presentDays + $halfDays, 1);

        $actualHours = $workLogHours > 0
            ? $workLogHours
            : round((float) ($item->timesheet_hours ?? 0), 2, PHP_ROUND_HALF_UP);
        $expectedHours = max(0, $workingDaysInPeriod) * $hoursPerDay;
        $estSubtotal = (float) ($item->base_pay ?? 0);
        if ($isHoursBased) {
            $hoursRatio = $expectedHours > 0 ? min(1, max(0, $actualHours / $expectedHours)) : 0;
            $estSubtotal = (float) ($item->base_pay ?? 0) * $hoursRatio;
        } elseif ($isAttendanceBased && $workingDaysInPeriod > 0) {
            $attendanceRatio = min(1, max(0, $workedDays / $workingDaysInPeriod));
            $estSubtotal = (float) ($item->base_pay ?? 0) * $attendanceRatio;
        }
        $estSubtotal = round($estSubtotal, 2, PHP_ROUND_HALF_UP);

        $bonus = $this->sumAdjustment($item->bonuses);
        $penalty = $this->sumAdjustment($item->penalties);
        $advance = $this->sumAdjustment($item->advances);
        $deduction = $this->sumAdjustment($item->deductions);
        $overtimePay = (float) ($item->overtime_hours ?? 0) * (float) ($item->overtime_rate ?? 0);
        $computedGross = round($estSubtotal + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
        $computedNet = round($computedGross - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);

        return round(max(0, $computedNet), 2, PHP_ROUND_HALF_UP);
    }

    private function usesJsonDeductionsColumn(): bool
    {
        static $result = null;
        if ($result !== null) {
            return $result;
        }

        try {
            $columnType = Schema::getColumnType('payroll_items', 'deductions');
            $result = in_array(strtolower((string) $columnType), ['json', 'jsonb'], true);
        } catch (\Throwable $e) {
            $result = false;
        }

        return $result;
    }
}
