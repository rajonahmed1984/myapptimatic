<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeWorkSession;
use App\Models\PaidHoliday;
use App\Models\PaymentMethod;
use App\Models\PayrollAuditLog;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        $selectedGeneratePeriod = now()->format('Y-m');
        $generatePeriods = collect(range(0, 36))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
            ->values();

        return Inertia::render('Admin/Hr/Payroll/Index', [
            'pageTitle' => 'Payroll',
            'summary' => $summary,
            'workLogDaysThisMonth' => $workLogDaysThisMonth,
            'attendanceMarkedToday' => $attendanceMarkedToday,
            'paidHolidaysThisMonth' => $paidHolidaysThisMonth,
            'selectedPeriodKey' => $selectedPeriodKey,
            'selectedStatus' => $selectedStatus,
            'selectedGeneratePeriod' => $selectedGeneratePeriod,
            'generatePeriods' => $generatePeriods->map(fn (Carbon $periodOption) => [
                'value' => $periodOption->format('Y-m'),
                'label' => $periodOption->format('M Y'),
            ])->values(),
            'periods' => $periods->through(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'period_key' => $period->period_key,
                'start_date' => $period->start_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'end_date' => $period->end_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'status' => $period->status,
                'is_draft' => $period->status === 'draft',
                'month_closed' => (bool) ($period->end_date?->lt(today())),
                'items_count' => (int) ($period->items_count ?? 0),
                'approved_items_count' => (int) ($period->approved_items_count ?? 0),
                'paid_items_count' => (int) ($period->paid_items_count ?? 0),
                'routes' => [
                    'show' => route('admin.hr.payroll.show', $period),
                    'export' => route('admin.hr.payroll.export', $period),
                    'edit' => route('admin.hr.payroll.edit', $period),
                    'destroy' => route('admin.hr.payroll.destroy', $period),
                    'finalize' => route('admin.hr.payroll.finalize', $period),
                ],
            ])->values(),
            'pagination' => [
                'previous_url' => $periods->previousPageUrl(),
                'next_url' => $periods->nextPageUrl(),
                'has_pages' => $periods->hasPages(),
            ],
            'routes' => [
                'index' => route('admin.hr.payroll.index'),
                'generate' => route('admin.hr.payroll.generate'),
            ],
        ]);
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

    public function edit(PayrollPeriod $payrollPeriod): InertiaResponse
    {
        if ($payrollPeriod->status !== 'draft') {
            abort(404);
        }

        return Inertia::render('Admin/Hr/Payroll/Edit', [
            'pageTitle' => 'Edit Payroll Period',
            'period' => [
                'id' => $payrollPeriod->id,
                'period_key' => $payrollPeriod->period_key,
                'start_date' => $payrollPeriod->start_date?->format(config('app.date_format', 'd-m-Y')),
                'end_date' => $payrollPeriod->end_date?->format(config('app.date_format', 'd-m-Y')),
            ],
            'routes' => [
                'index' => route('admin.hr.payroll.index'),
                'update' => route('admin.hr.payroll.update', $payrollPeriod),
            ],
        ]);
    }

    public function update(Request $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->status !== 'draft') {
            return back()->withErrors(['payroll' => 'Only draft payroll periods can be edited.']);
        }

        $data = $request->validate([
            'period_key' => ['required', 'regex:/^\d{4}-\d{2}$/', 'unique:payroll_periods,period_key,'.$payrollPeriod->id],
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

    public function show(PayrollPeriod $payrollPeriod): InertiaResponse
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

        $paymentMethods = PaymentMethod::dropdownOptions()
            ->map(fn ($method) => [
                'code' => $method->code,
                'name' => $method->name,
            ])
            ->values();

        return Inertia::render('Admin/Hr/Payroll/Show', [
            'pageTitle' => 'Payroll '.$payrollPeriod->period_key,
            'period' => [
                'id' => $payrollPeriod->id,
                'period_key' => $payrollPeriod->period_key,
                'status' => $payrollPeriod->status,
                'start_date' => $payrollPeriod->start_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'end_date' => $payrollPeriod->end_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'month_closed' => (bool) ($payrollPeriod->end_date?->lt(today())),
            ],
            'totals' => $totals->map(fn ($total) => [
                'currency' => $total->currency,
                'base_total' => number_format((float) $total->base_total, 2),
                'gross_total' => number_format((float) $total->gross_total, 2),
                'net_total' => number_format((float) $total->net_total, 2),
            ])->values(),
            'items' => $items->through(function (PayrollItem $item) use ($payrollPeriod, $workLogHoursByEmployee, $attendanceSummaryByEmployee, $workingDaysInPeriod) {
                $bonus = $this->sumAdjustment($item->bonuses);
                $penalty = $this->sumAdjustment($item->penalties);
                $advance = $this->sumAdjustment($item->advances);
                $deduction = $this->sumAdjustment($item->deductions);
                $deductionFirst = is_array($item->deductions) ? (array) ($item->deductions[0] ?? []) : [];
                $deductionReference = (string) ($deductionFirst['reference'] ?? '');
                $deductionNote = (string) ($deductionFirst['note'] ?? '');
                $attendance = $attendanceSummaryByEmployee[$item->employee_id] ?? null;
                $workedDays = (float) ($attendance['worked_days'] ?? 0);
                $totalWorkingDays = (int) ($attendance['working_days'] ?? ($workingDaysInPeriod ?? 0));
                $workLogHours = (float) ($workLogHoursByEmployee[$item->employee_id] ?? 0);
                $isHoursBased = $item->pay_type === 'hourly' || ($item->employee?->employment_type ?? null) === 'part_time';
                $isAttendanceBased = ($item->employee?->employment_type ?? null) === 'full_time';
                $hoursPerDay = (($item->employee?->employment_type ?? null) === 'part_time') ? 4 : 8;
                $expectedHours = max(0, (int) ($workingDaysInPeriod ?? 0)) * $hoursPerDay;
                $actualHours = $workLogHours > 0 ? $workLogHours : (float) ($item->timesheet_hours ?? 0);
                $estSubtotal = (float) ($item->base_pay ?? 0);

                if ($isHoursBased) {
                    $hoursRatio = $expectedHours > 0 ? min(1, max(0, $actualHours / $expectedHours)) : 0;
                    $estSubtotal = (float) ($item->base_pay ?? 0) * $hoursRatio;
                } elseif ($isAttendanceBased && $totalWorkingDays > 0) {
                    $attendanceRatio = min(1, max(0, $workedDays / $totalWorkingDays));
                    $estSubtotal = (float) ($item->base_pay ?? 0) * $attendanceRatio;
                }

                $estSubtotal = round($estSubtotal, 2, PHP_ROUND_HALF_UP);
                $overtimePay = (float) ($item->overtime_hours ?? 0) * (float) ($item->overtime_rate ?? 0);
                $computedGross = round($estSubtotal + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
                $computedNet = round($computedGross - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);
                $payableNet = round(max(0, $computedNet), 2, PHP_ROUND_HALF_UP);
                $paidAmount = round((float) ($item->paid_amount ?? 0), 2, PHP_ROUND_HALF_UP);
                $remainingAmount = round(max(0, $payableNet - $paidAmount), 2, PHP_ROUND_HALF_UP);
                $displayStatus = $payableNet <= 0 ? 'paid' : $item->status;

                return [
                    'id' => $item->id,
                    'employee_id' => $item->employee_id,
                    'employee_name' => $item->employee?->name ?? 'N/A',
                    'pay_type' => ucfirst((string) $item->pay_type),
                    'currency' => $item->currency,
                    'base_pay' => number_format((float) $item->base_pay, 2),
                    'hours_display' => $isHoursBased
                        ? number_format($actualHours, 2).' hrs ('.number_format((float) $expectedHours, 0).' hrs)'
                        : ($isAttendanceBased
                            ? (fmod($workedDays, 1.0) === 0.0 ? (string) ((int) $workedDays) : number_format($workedDays, 1)).' ('.$totalWorkingDays.')'
                            : '--'),
                    'overtime_hours' => number_format((float) $item->overtime_hours, 2),
                    'overtime_rate' => number_format((float) $item->overtime_rate, 2),
                    'bonus' => number_format($bonus, 2),
                    'penalty' => number_format($penalty, 2),
                    'advance' => number_format($advance, 2),
                    'est_subtotal' => number_format($estSubtotal, 2),
                    'computed_gross' => number_format($computedGross, 2),
                    'deduction' => number_format($deduction, 2),
                    'deduction_reference' => $deductionReference,
                    'deduction_note' => $deductionNote,
                    'computed_net' => number_format($computedNet, 2),
                    'display_status' => $displayStatus,
                    'paid_amount' => number_format($paidAmount, 2),
                    'paid_at' => $item->paid_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'payment_reference' => $item->payment_reference ?? '--',
                    'can_adjust' => in_array($item->status, ['draft', 'approved'], true),
                    'can_pay' => in_array($item->status, ['approved', 'partial'], true) && $remainingAmount > 0,
                    'payment_data' => [
                        'net' => number_format($payableNet, 2).' '.$item->currency,
                        'net_amount' => number_format($payableNet, 2, '.', ''),
                        'paid_amount' => number_format($paidAmount, 2, '.', ''),
                        'remaining_amount' => number_format($remainingAmount, 2, '.', ''),
                        'currency' => $item->currency,
                    ],
                    'routes' => [
                        'adjust' => route('admin.hr.payroll.items.adjustments', [$payrollPeriod, $item]),
                        'pay' => route('admin.hr.payroll.items.pay', [$payrollPeriod, $item]),
                    ],
                ];
            })->values(),
            'pagination' => [
                'previous_url' => $items->previousPageUrl(),
                'next_url' => $items->nextPageUrl(),
                'has_pages' => $items->hasPages(),
            ],
            'paymentMethods' => $paymentMethods,
            'today' => now()->format(config('app.date_format', 'd-m-Y')),
            'routes' => [
                'index' => route('admin.hr.payroll.index'),
                'export' => route('admin.hr.payroll.export', $payrollPeriod),
                'edit' => route('admin.hr.payroll.edit', $payrollPeriod),
                'destroy' => route('admin.hr.payroll.destroy', $payrollPeriod),
                'finalize' => route('admin.hr.payroll.finalize', $payrollPeriod),
            ],
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
