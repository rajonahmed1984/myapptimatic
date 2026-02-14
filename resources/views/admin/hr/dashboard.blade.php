@extends('layouts.admin')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR & Payroll</div>
            <div class="text-2xl font-semibold text-slate-900">Overview</div>
            <div class="text-sm text-slate-500">Live summary from Employees, Work Logs, Leave, Attendance, Paid Holidays, and Payroll.</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.hr.employees.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add employee</a>
            <a href="{{ route('admin.hr.attendance.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Attendance</a>
            <a href="{{ route('admin.hr.paid-holidays.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Paid holidays</a>
            <a href="{{ route('admin.hr.payroll.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Payroll</a>
        </div>
    </div>

    <div class="space-y-6">
        <div>
            <div class="section-label">People & Leave</div>
            <div class="mt-3 grid gap-4 md:grid-cols-4">
                <a href="{{ route('admin.hr.employees.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active employees</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $activeEmployees }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">All active profiles</div>
                </a>
                <a href="{{ route('admin.hr.leave-requests.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Pending leave requests</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $pendingLeaveRequests }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Awaiting HR approval</div>
                </a>
                <a href="{{ route('admin.hr.leave-requests.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">On leave today</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $onLeaveToday }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Approved leave covering today</div>
                </a>
                <a href="{{ route('admin.hr.paid-holidays.index', ['month' => $currentMonth]) }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Paid holidays (month)</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $paidHolidaysThisMonth }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Current month calendar</div>
                </a>
            </div>
        </div>

        <div>
            <div class="section-label">Work, Attendance & Payroll</div>
            <div class="mt-3 grid gap-4 md:grid-cols-4">
                <a href="{{ route('admin.hr.attendance.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Attendance marked today</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $attendanceMarkedToday }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Full-time total: {{ $activeFullTimeEmployees }}</div>
                </a>
                <a href="{{ route('admin.hr.attendance.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Attendance pending today</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $attendanceMissingToday }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Not marked yet</div>
                </a>
                <a href="{{ route('admin.hr.timesheets.index', ['month' => $currentMonth]) }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Work log days (month)</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $workLogDaysThisMonth }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Employee-date rows</div>
                </a>
                <a href="{{ route('admin.hr.timesheets.index', ['month' => $currentMonth]) }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">On-target days (month)</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $onTargetDaysThisMonth }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Required time reached</div>
                </a>
                <a href="{{ route('admin.hr.payroll.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Draft payroll periods</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $draftPeriods }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Needs finalize</div>
                </a>
                <a href="{{ route('admin.hr.payroll.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll to pay</div>
                    <div class="text-xl font-semibold text-slate-900">{{ $payrollToPay }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">Approved unpaid items</div>
                </a>
            </div>
        </div>

        <div class="card p-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent work logs</div>
                        <a href="{{ route('admin.hr.timesheets.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>
                    </div>
                    <div class="mt-3 space-y-3 text-sm text-slate-700">
                        @forelse($recentWorkLogs as $log)
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $log->employee?->name ?? '--' }}</div>
                                    <div class="text-xs text-slate-500">{{ $log->work_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                                </div>
                                <div class="text-xs text-slate-500">{{ number_format(((int) ($log->active_seconds ?? 0)) / 3600, 2) }}h</div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500">No recent work logs.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent leave requests</div>
                        <a href="{{ route('admin.hr.leave-requests.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>
                    </div>
                    <div class="mt-3 space-y-3 text-sm text-slate-700">
                        @forelse($recentLeaveRequests as $leave)
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $leave->employee?->name ?? '--' }}</div>
                                    <div class="text-xs text-slate-500">{{ $leave->leaveType?->name ?? 'Leave' }} ({{ ucfirst((string) $leave->status) }})</div>
                                </div>
                                <div class="text-xs text-slate-500">{{ $leave->start_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500">No recent leave requests.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent attendance</div>
                        <a href="{{ route('admin.hr.attendance.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>
                    </div>
                    <div class="mt-3 space-y-3 text-sm text-slate-700">
                        @forelse($recentAttendance as $attendance)
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $attendance->employee?->name ?? '--' }}</div>
                                    <div class="text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', (string) $attendance->status)) }}</div>
                                </div>
                                <div class="text-xs text-slate-500">{{ $attendance->date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500">No recent attendance records.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
