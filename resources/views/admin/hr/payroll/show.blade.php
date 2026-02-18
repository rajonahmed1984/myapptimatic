@extends('layouts.admin')

@section('title', 'Payroll '.$period->period_key)
@section('page-title', 'Payroll')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Payroll {{ $period->period_key }}</div>
            <div class="text-sm text-slate-500">
                {{ $period->start_date?->format($globalDateFormat) }} - {{ $period->end_date?->format($globalDateFormat) }}
                | {{ ucfirst($period->status) }}
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.hr.payroll.export', $period) }}" class="text-sm text-slate-700 hover:underline">Export CSV</a>
            @if($period->status === 'draft')
                <a href="{{ route('admin.hr.payroll.edit', $period) }}" class="text-sm text-slate-700 hover:underline">Edit Period</a>
                <form
                    method="POST"
                    action="{{ route('admin.hr.payroll.destroy', $period) }}"
                    data-ajax-form="true"
                    data-delete-confirm
                    data-confirm-name="{{ $period->period_key }}"
                    data-confirm-title="Delete payroll period {{ $period->period_key }}?"
                    data-confirm-description="This will permanently delete this payroll period and its payroll items."
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-rose-700 hover:underline">Delete</button>
                </form>
            @endif
            @if($period->status === 'draft' && $period->end_date?->lt(today()))
                <form method="POST" action="{{ route('admin.hr.payroll.finalize', $period) }}" data-ajax-form="true">
                    @csrf
                    <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Finalize</button>
                </form>
            @elseif($period->status === 'draft')
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Month not closed</span>
            @endif
            <a href="{{ route('admin.hr.payroll.index') }}" class="text-sm text-slate-600 hover:text-slate-800">Back</a>
        </div>
    </div>

    @if($totals->isNotEmpty())
        <div class="mb-6 space-y-3">
            @foreach($totals as $total)
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Totals ({{ $total->currency }})</div>
                        <div><span class="text-slate-500">Base:</span> {{ number_format((float) $total->base_total, 2) }}</div>
                        <div><span class="text-slate-500">Gross:</span> {{ number_format((float) $total->gross_total, 2) }}</div>
                        <div><span class="text-slate-500">Net:</span> {{ number_format((float) $total->net_total, 2) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full whitespace-nowrap text-sm text-slate-700">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Employee ID</th>
                        <th class="py-2 px-3">Employee</th>
                        <th class="py-2 px-3">Pay Type</th>
                        <th class="py-2 px-3">Currency</th>
                        <th class="py-2 px-3">Base</th>
                        <th class="py-2 px-3">Hours / Attendance</th>
                        <th class="py-2 px-3">Overtime</th>
                        <th class="py-2 px-3">Bonus</th>
                        <th class="py-2 px-3">Penalty</th>
                        <th class="py-2 px-3">Advance</th>
                        <th class="py-2 px-3">Est. Subtotal</th>
                        <th class="py-2 px-3">Gross</th>
                        <th class="py-2 px-3">Deduction</th>
                        <th class="py-2 px-3">Net</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">Paid</th>
                        <th class="py-2 px-3">Paid At</th>
                        <th class="py-2 px-3">Payment methods</th>
                        <th class="py-2 px-3 text-right">Payment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $bonus = is_array($item->bonuses) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->bonuses)) : (float) ($item->bonuses ?? 0);
                            $penalty = is_array($item->penalties) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->penalties)) : (float) ($item->penalties ?? 0);
                            $advance = is_array($item->advances) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->advances)) : (float) ($item->advances ?? 0);
                            $deduction = is_array($item->deductions) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->deductions)) : (float) ($item->deductions ?? 0);
                            $deductionFirst = is_array($item->deductions) ? (array) ($item->deductions[0] ?? []) : [];
                            $deductionReference = (string) ($deductionFirst['reference'] ?? '');
                            $deductionNote = (string) ($deductionFirst['note'] ?? '');
                            $attendance = $attendanceSummaryByEmployee[$item->employee_id] ?? null;
                            $workedDays = (float) ($attendance['worked_days'] ?? 0);
                            $totalWorkingDays = (int) ($attendance['working_days'] ?? ($workingDaysInPeriod ?? 0));
                            $workedDisplay = fmod($workedDays, 1.0) === 0.0
                                ? (string) ((int) $workedDays)
                                : number_format($workedDays, 1);
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
                            // Standard payroll formula:
                            // Gross = Base/EstSubtotal + Overtime + Bonus
                            // Net(Payable) = Gross - Penalty - Advance - Deduction
                            $computedGross = round($estSubtotal + $overtimePay + $bonus, 2, PHP_ROUND_HALF_UP);
                            $computedNet = round($computedGross - $penalty - $advance - $deduction, 2, PHP_ROUND_HALF_UP);
                            $payableNet = round(max(0, $computedNet), 2, PHP_ROUND_HALF_UP);
                            $paidAmount = round((float) ($item->paid_amount ?? 0), 2, PHP_ROUND_HALF_UP);
                            $remainingAmount = round(max(0, $payableNet - $paidAmount), 2, PHP_ROUND_HALF_UP);
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3">{{ $item->employee_id }}</td>
                            <td class="py-2 px-3">{{ $item->employee?->name ?? 'N/A' }}</td>
                            <td class="py-2 px-3">{{ ucfirst($item->pay_type) }}</td>
                            <td class="py-2 px-3">{{ $item->currency }}</td>
                            <td class="py-2 px-3">{{ number_format((float) $item->base_pay, 2) }}</td>
                            <td class="py-2 px-3">
                                @if($isHoursBased)
                                    {{ number_format($actualHours, 2) }} hrs ({{ number_format((float) $expectedHours, 0) }} hrs)
                                @elseif($isAttendanceBased)
                                    <span class="font-semibold text-slate-800">{{ $workedDisplay }}</span> ({{ $totalWorkingDays }})
                                @else
                                    --
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                @if(in_array($item->status, ['draft', 'approved'], true))
                                    <button
                                        type="button"
                                        class="text-left hover:text-teal-700"
                                        data-adjust-open
                                        data-adjust-action="{{ route('admin.hr.payroll.items.adjustments', [$period, $item]) }}"
                                        data-adjust-employee="{{ $item->employee?->name ?? 'N/A' }}"
                                        data-adjust-overtime-hours="{{ number_format((float) ($item->overtime_hours ?? 0), 2, '.', '') }}"
                                        data-adjust-overtime-rate="{{ number_format((float) ($item->overtime_rate ?? 0), 2, '.', '') }}"
                                        data-adjust-bonus="{{ number_format($bonus, 2, '.', '') }}"
                                        data-adjust-penalty="{{ number_format($penalty, 2, '.', '') }}"
                                        data-adjust-deduction="{{ number_format($deduction, 2, '.', '') }}"
                                        data-adjust-deduction-reference="{{ $deductionReference }}"
                                        data-adjust-deduction-note="{{ $deductionNote }}"
                                    >
                                        {{ number_format((float) $item->overtime_hours, 2) }}
                                        @if($item->overtime_rate)
                                            <div class="text-[11px] text-slate-500">&#64; {{ number_format((float) $item->overtime_rate, 2) }}</div>
                                        @endif
                                    </button>
                                @else
                                    {{ number_format((float) $item->overtime_hours, 2) }}
                                    @if($item->overtime_rate)
                                        <div class="text-[11px] text-slate-500">&#64; {{ number_format((float) $item->overtime_rate, 2) }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                @if(in_array($item->status, ['draft', 'approved'], true))
                                    <button
                                        type="button"
                                        class="hover:text-teal-700"
                                        data-adjust-open
                                        data-adjust-action="{{ route('admin.hr.payroll.items.adjustments', [$period, $item]) }}"
                                        data-adjust-employee="{{ $item->employee?->name ?? 'N/A' }}"
                                        data-adjust-overtime-hours="{{ number_format((float) ($item->overtime_hours ?? 0), 2, '.', '') }}"
                                        data-adjust-overtime-rate="{{ number_format((float) ($item->overtime_rate ?? 0), 2, '.', '') }}"
                                        data-adjust-bonus="{{ number_format($bonus, 2, '.', '') }}"
                                        data-adjust-penalty="{{ number_format($penalty, 2, '.', '') }}"
                                        data-adjust-deduction="{{ number_format($deduction, 2, '.', '') }}"
                                        data-adjust-deduction-reference="{{ $deductionReference }}"
                                        data-adjust-deduction-note="{{ $deductionNote }}"
                                    >
                                        {{ number_format($bonus, 2) }}
                                    </button>
                                @else
                                    {{ number_format($bonus, 2) }}
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                @if(in_array($item->status, ['draft', 'approved'], true))
                                    <button
                                        type="button"
                                        class="hover:text-teal-700"
                                        data-adjust-open
                                        data-adjust-action="{{ route('admin.hr.payroll.items.adjustments', [$period, $item]) }}"
                                        data-adjust-employee="{{ $item->employee?->name ?? 'N/A' }}"
                                        data-adjust-overtime-hours="{{ number_format((float) ($item->overtime_hours ?? 0), 2, '.', '') }}"
                                        data-adjust-overtime-rate="{{ number_format((float) ($item->overtime_rate ?? 0), 2, '.', '') }}"
                                        data-adjust-bonus="{{ number_format($bonus, 2, '.', '') }}"
                                        data-adjust-penalty="{{ number_format($penalty, 2, '.', '') }}"
                                        data-adjust-deduction="{{ number_format($deduction, 2, '.', '') }}"
                                        data-adjust-deduction-reference="{{ $deductionReference }}"
                                        data-adjust-deduction-note="{{ $deductionNote }}"
                                    >
                                        {{ number_format($penalty, 2) }}
                                    </button>
                                @else
                                    {{ number_format($penalty, 2) }}
                                @endif
                            </td>
                            <td class="py-2 px-3">{{ number_format($advance, 2) }}</td>
                            <td class="py-2 px-3">
                                {{ number_format($estSubtotal, 2) }} {{ $item->currency }}
                            </td>
                            <td class="py-2 px-3">{{ number_format($computedGross, 2) }}</td>
                            <td class="py-2 px-3">
                                @if(in_array($item->status, ['draft', 'approved'], true))
                                    <button
                                        type="button"
                                        class="text-left hover:text-teal-700"
                                        data-adjust-open
                                        data-adjust-action="{{ route('admin.hr.payroll.items.adjustments', [$period, $item]) }}"
                                        data-adjust-employee="{{ $item->employee?->name ?? 'N/A' }}"
                                        data-adjust-overtime-hours="{{ number_format((float) ($item->overtime_hours ?? 0), 2, '.', '') }}"
                                        data-adjust-overtime-rate="{{ number_format((float) ($item->overtime_rate ?? 0), 2, '.', '') }}"
                                        data-adjust-bonus="{{ number_format($bonus, 2, '.', '') }}"
                                        data-adjust-penalty="{{ number_format($penalty, 2, '.', '') }}"
                                        data-adjust-deduction="{{ number_format($deduction, 2, '.', '') }}"
                                        data-adjust-deduction-reference="{{ $deductionReference }}"
                                        data-adjust-deduction-note="{{ $deductionNote }}"
                                    >
                                        {{ number_format($deduction, 2) }}
                                        @if($deductionReference !== '')
                                            <div class="text-[11px] text-slate-500">{{ $deductionReference }}</div>
                                        @endif
                                    </button>
                                @else
                                    {{ number_format($deduction, 2) }}
                                    @if($deductionReference !== '')
                                        <div class="text-[11px] text-slate-500">{{ $deductionReference }}</div>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2 px-3">{{ number_format($computedNet, 2) }}</td>
                            <td class="py-2 px-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{
                                    $item->status === 'paid' ? 'bg-emerald-100 text-emerald-700' :
                                    ($item->status === 'partial' ? 'bg-orange-100 text-orange-700' :
                                    ($item->status === 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'))
                                }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="py-2 px-3">{{ number_format($paidAmount, 2) }}</td>
                            <td class="py-2 px-3">{{ $item->paid_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                            <td class="py-2 px-3">{{ $item->payment_reference ?? '--' }}</td>
                            <td class="py-2 px-3 text-right">
                                @if(in_array($item->status, ['approved', 'partial'], true) && $remainingAmount > 0)
                                    <button
                                        type="button"
                                        class="rounded-full border border-emerald-300 px-3 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
                                        data-payment-open
                                        data-payment-action="{{ route('admin.hr.payroll.items.pay', [$period, $item]) }}"
                                        data-payment-employee="{{ $item->employee?->name ?? 'N/A' }}"
                                        data-payment-net="{{ number_format($payableNet, 2) }} {{ $item->currency }}"
                                        data-payment-net-amount="{{ number_format($payableNet, 2, '.', '') }}"
                                        data-payment-paid-amount="{{ number_format($paidAmount, 2, '.', '') }}"
                                        data-payment-remaining-amount="{{ number_format($remainingAmount, 2, '.', '') }}"
                                        data-payment-currency="{{ $item->currency }}"
                                    >
                                        Payment
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">--</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="19" class="py-3 px-3 text-center text-slate-500">No payroll items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>

    <div id="payrollAdjustModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-adjust-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="section-label">Payroll Adjustments</div>
                    <div id="adjustModalEmployee" class="text-lg font-semibold text-slate-900">Employee</div>
                    <div class="text-sm text-slate-500">Overtime, bonus, and penalty update</div>
                </div>
                <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" data-adjust-close>Close</button>
            </div>

            <form id="adjustModalForm" method="POST" action="" class="mt-5 grid gap-4" data-ajax-form="true">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="adjustOvertimeHours" class="text-xs uppercase tracking-[0.2em] text-slate-500">Overtime Hours</label>
                        <input id="adjustOvertimeHours" name="overtime_hours" type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="adjustOvertimeRate" class="text-xs uppercase tracking-[0.2em] text-slate-500">Overtime Rate</label>
                        <input id="adjustOvertimeRate" name="overtime_rate" type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="adjustBonus" class="text-xs uppercase tracking-[0.2em] text-slate-500">Bonus</label>
                        <input id="adjustBonus" name="bonuses" type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="adjustPenalty" class="text-xs uppercase tracking-[0.2em] text-slate-500">Penalty</label>
                        <input id="adjustPenalty" name="penalties" type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="adjustDeduction" class="text-xs uppercase tracking-[0.2em] text-slate-500">Deduction</label>
                        <input id="adjustDeduction" name="deductions" type="number" step="0.01" min="0" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <div class="mt-1 text-[11px] text-slate-500">Deduction is subtracted from net payable.</div>
                    </div>
                    <div>
                        <label for="adjustDeductionReference" class="text-xs uppercase tracking-[0.2em] text-slate-500">Deduction Reference</label>
                        <input id="adjustDeductionReference" name="deduction_reference" type="text" maxlength="120" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Late / Loan / Fine ref">
                    </div>
                    <div class="md:col-span-2">
                        <label for="adjustDeductionNote" class="text-xs uppercase tracking-[0.2em] text-slate-500">Deduction Reason</label>
                        <textarea id="adjustDeductionNote" name="deduction_note" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Why this deduction was applied"></textarea>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" data-adjust-close>Cancel</button>
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Adjustments</button>
                </div>
            </form>
        </div>
    </div>

    <div id="payrollPaymentModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-payment-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="section-label">Payroll Payment</div>
                    <div id="paymentModalEmployee" class="text-lg font-semibold text-slate-900">Employee</div>
                    <div id="paymentModalAmount" class="text-sm text-slate-500">Net amount</div>
                </div>
                <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" data-payment-close>Close</button>
            </div>

            <form id="paymentModalForm" method="POST" action="" enctype="multipart/form-data" class="mt-5 grid gap-4" data-ajax-form="true">
                @csrf
                <div>
                    <label for="paymentAmount" class="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                    <input id="paymentAmount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                    <div id="paymentAmountHint" class="mt-1 text-[11px] text-slate-500">Remaining payable amount</div>
                </div>
                <div>
                    <label for="paymentMethod" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                    @php
                        $paymentMethods = \App\Models\PaymentMethod::dropdownOptions();
                    @endphp
                    <select id="paymentMethod" name="payment_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        <option value="">Select</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="paymentReference" class="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                    <input id="paymentReference" name="payment_reference" value="" placeholder="Txn / note" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="paymentProof" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Proof</label>
                    <input id="paymentProof" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <div class="mt-1 text-[11px] text-slate-500">Accepted: JPG, PNG, WEBP, PDF (max 5MB)</div>
                </div>
                <div>
                    <label for="paidAt" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Date</label>
                    <input id="paidAt" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" data-payment-close>Cancel</button>
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const setupAdjustModal = () => {
                const root = document.getElementById('appContent');
                const modal = document.getElementById('payrollAdjustModal');
                const form = document.getElementById('adjustModalForm');
                const employeeEl = document.getElementById('adjustModalEmployee');
                const overtimeHoursEl = document.getElementById('adjustOvertimeHours');
                const overtimeRateEl = document.getElementById('adjustOvertimeRate');
                const bonusEl = document.getElementById('adjustBonus');
                const penaltyEl = document.getElementById('adjustPenalty');
                const deductionEl = document.getElementById('adjustDeduction');
                const deductionReferenceEl = document.getElementById('adjustDeductionReference');
                const deductionNoteEl = document.getElementById('adjustDeductionNote');
                if (!root || !modal || !form || !employeeEl || !overtimeHoursEl || !overtimeRateEl || !bonusEl || !penaltyEl || !deductionEl || !deductionReferenceEl || !deductionNoteEl) return;

                const openModal = (btn) => {
                    form.setAttribute('action', btn.getAttribute('data-adjust-action') || '');
                    employeeEl.textContent = btn.getAttribute('data-adjust-employee') || 'Employee';
                    overtimeHoursEl.value = btn.getAttribute('data-adjust-overtime-hours') || '0';
                    overtimeRateEl.value = btn.getAttribute('data-adjust-overtime-rate') || '0';
                    bonusEl.value = btn.getAttribute('data-adjust-bonus') || '0';
                    penaltyEl.value = btn.getAttribute('data-adjust-penalty') || '0';
                    deductionEl.value = btn.getAttribute('data-adjust-deduction') || '0';
                    deductionReferenceEl.value = btn.getAttribute('data-adjust-deduction-reference') || '';
                    deductionNoteEl.value = btn.getAttribute('data-adjust-deduction-note') || '';
                    modal.classList.remove('hidden');
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                };

                root.addEventListener('click', (event) => {
                    const openBtn = event.target.closest('[data-adjust-open]');
                    if (openBtn) {
                        event.preventDefault();
                        openModal(openBtn);
                        return;
                    }

                    const closeBtn = event.target.closest('[data-adjust-close]');
                    if (closeBtn && modal.contains(closeBtn)) {
                        event.preventDefault();
                        closeModal();
                    }
                });

                root.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeModal();
                });
            };

            const setupPaymentModal = () => {
                const root = document.getElementById('appContent');
                const modal = document.getElementById('payrollPaymentModal');
                const form = document.getElementById('paymentModalForm');
                const employeeEl = document.getElementById('paymentModalEmployee');
                const amountEl = document.getElementById('paymentModalAmount');
                const amountInputEl = document.getElementById('paymentAmount');
                const amountHintEl = document.getElementById('paymentAmountHint');
                if (!root || !modal || !form || !employeeEl || !amountEl || !amountInputEl || !amountHintEl) return;

                const openModal = (btn) => {
                    const action = btn.getAttribute('data-payment-action') || '';
                    const employee = btn.getAttribute('data-payment-employee') || 'Employee';
                    const amount = btn.getAttribute('data-payment-net') || '--';
                    const amountValue = btn.getAttribute('data-payment-net-amount') || '0.00';
                    const paidAmount = btn.getAttribute('data-payment-paid-amount') || '0.00';
                    const remainingAmount = btn.getAttribute('data-payment-remaining-amount') || amountValue;
                    const currency = btn.getAttribute('data-payment-currency') || '';

                    form.setAttribute('action', action);
                    employeeEl.textContent = employee;
                    amountEl.textContent = `Net: ${amount}`;
                    amountInputEl.value = remainingAmount;
                    amountInputEl.max = remainingAmount;
                    amountHintEl.textContent = `Paid: ${paidAmount} ${currency} | Left: ${remainingAmount} ${currency}`;
                    modal.classList.remove('hidden');
                    setTimeout(() => {
                        amountInputEl.focus();
                        amountInputEl.select();
                    }, 0);
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                };

                root.addEventListener('click', (event) => {
                    const openBtn = event.target.closest('[data-payment-open]');
                    if (openBtn) {
                        event.preventDefault();
                        openModal(openBtn);
                        return;
                    }

                    const closeBtn = event.target.closest('[data-payment-close]');
                    if (closeBtn && modal.contains(closeBtn)) {
                        event.preventDefault();
                        closeModal();
                    }
                });

                root.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') closeModal();
                });
            };

            setupAdjustModal();
            setupPaymentModal();
        })();
    </script>
@endsection
