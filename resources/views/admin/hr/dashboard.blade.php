@extends('layouts.admin')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR & Payroll</div>
            <div class="text-2xl font-semibold text-slate-900">Overview</div>
            <div class="text-sm text-slate-500">Monitor employees, timesheets, and payroll periods.</div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.hr.employees.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add employee</a>
            <a href="{{ route('admin.hr.leave-requests.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Review leave</a>
            <a href="{{ route('admin.hr.timesheets.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Review timesheets</a>
            <a href="{{ route('admin.hr.payroll.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Payroll</a>
        </div>
    </div>

    <div class="space-y-6">
        <div>
            <div class="section-label">Team overview</div>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                <a href="{{ route('admin.hr.employees.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active employees</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $activeEmployees }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Currently enabled profiles</div>
                </a>
                <a href="{{ route('admin.hr.employees.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">New hires (30d)</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $newHires30 }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Joined in the last 30 days</div>
                </a>
                <a href="{{ route('admin.hr.leave-requests.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">On leave today</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $onLeaveToday }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Approved leave covering today</div>
                </a>
            </div>
        </div>

        <div>
            <div class="section-label">Approvals & reviews</div>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                <a href="{{ route('admin.hr.leave-requests.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Pending leave requests</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $pendingLeaveRequests }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Awaiting approval</div>
                </a>
                <a href="{{ route('admin.hr.timesheets.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Pending timesheets</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $pendingTimesheets }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Submitted and awaiting review</div>
                </a>
                <a href="{{ route('admin.hr.timesheets.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Approved timesheets</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $approvedTimesheets }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Ready to lock into payroll</div>
                </a>
            </div>
        </div>

        <div>
            <div class="section-label">Payroll status</div>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                <a href="{{ route('admin.hr.payroll.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Draft periods</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $draftPeriods }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Ready to finalize</div>
                </a>
                <a href="{{ route('admin.hr.payroll.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Finalized periods</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $finalizedPeriods }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Awaiting disbursement</div>
                </a>
                <a href="{{ route('admin.hr.payroll.index') }}" class="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll to pay</div>
                            <div class="text-xl font-semibold text-slate-900">{{ $payrollToPay }}</div>
                        </div>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Approved items pending payout</div>
                </a>
            </div>
        </div>

        <div class="card p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="section-label">Recent activity</div>
                    <div class="text-sm text-slate-500">Latest timesheets and leave requests.</div>
                </div>
                <div class="text-xs text-slate-400">Locked timesheets: {{ $lockedTimesheets }}</div>
            </div>

            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Recent timesheets</div>
                        <a href="{{ route('admin.hr.timesheets.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-600">View all</a>
                    </div>
                    <div class="mt-3 space-y-3 text-sm text-slate-700">
                        @forelse($recentTimesheets as $sheet)
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $sheet->employee?->name ?? '--' }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $sheet->period_start?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}
                                        -
                                        {{ $sheet->period_end?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500">{{ ucfirst($sheet->status) }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500">No recent timesheets.</div>
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
                                    <div class="text-xs text-slate-500">
                                        {{ $leave->leaveType?->name ?? 'Leave' }}
                                        -
                                        {{ $leave->start_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}
                                        -
                                        {{ $leave->end_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500">{{ ucfirst($leave->status) }}</div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-500">No recent leave requests.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
