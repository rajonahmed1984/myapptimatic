@extends('layouts.admin')

@section('title', 'HR Dashboard')
@section('page-title', 'HR Dashboard')

@section('content')
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">HR & Payroll</div>
                <div class="text-2xl font-semibold text-slate-900">Overview</div>
                <div class="text-sm text-slate-500">Monitor employees, timesheets, and payroll periods.</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-800">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Active employees</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $activeEmployees }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Pending timesheets</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $pendingTimesheets }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Draft payroll periods</div>
                <div class="mt-2 text-3xl font-semibold text-slate-900">{{ $draftPeriods }}</div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-slate-700">
            <div class="text-sm font-semibold text-slate-900">Next steps</div>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-600">
                <li>Create employee profiles and compensation packages.</li>
                <li>Enable timesheet submissions for hourly staff.</li>
                <li>Generate the current month's payroll period and review items before payment.</li>
            </ul>
        </div>
    </div>
@endsection
