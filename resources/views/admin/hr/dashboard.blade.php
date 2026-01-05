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
    </div>

    <div class="card p-6">
        <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-800">
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
    </div>
@endsection
