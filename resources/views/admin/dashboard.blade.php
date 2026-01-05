@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Overview')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Admin overview</div>
            <div class="text-2xl font-semibold text-slate-900">Snapshot</div>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 stagger">
        <div class="card p-6">
            <div class="section-label">Customers</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $customerCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Subscriptions</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $subscriptionCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Licenses</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $licenseCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Unpaid Invoices</div>
            <div class="mt-3 text-3xl font-semibold text-blue-600">{{ $pendingInvoiceCount }}</div>
        </div>
    </div>

    @php
        $projectMaintenance = $projectMaintenance ?? ['projects_active' => 0, 'projects_on_hold' => 0, 'subscriptions_blocked' => 0, 'renewals_30d' => 0, 'projects_profitable' => 0, 'projects_loss' => 0];
        $hrStats = $hrStats ?? [
            'active_employees' => 0,
            'pending_timesheets' => 0,
            'approved_timesheets' => 0,
            'draft_payroll_periods' => 0,
            'finalized_payroll_periods' => 0,
            'payroll_items_to_pay' => 0,
        ];
    @endphp

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active projects</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $projectMaintenance['projects_active'] }}</div>
            <div class="mt-1 text-xs text-slate-500">On hold: {{ $projectMaintenance['projects_on_hold'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Blocked services</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $projectMaintenance['subscriptions_blocked'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Suspended subscriptions</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Renewals (30d)</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $projectMaintenance['renewals_30d'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Upcoming maintenance invoices</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Profitability</div>
            <div class="mt-2 text-2xl font-semibold text-emerald-600">{{ $projectMaintenance['projects_profitable'] }}</div>
            <div class="mt-1 text-xs text-rose-600">Loss risk: {{ $projectMaintenance['projects_loss'] }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Active employees</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $hrStats['active_employees'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Currently enabled profiles</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Timesheets</div>
            <div class="mt-2 text-2xl font-semibold text-amber-600">{{ $hrStats['pending_timesheets'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Awaiting approval</div>
            <div class="mt-1 text-xs text-emerald-600">Approved: {{ $hrStats['approved_timesheets'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll periods</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $hrStats['draft_payroll_periods'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Draft</div>
            <div class="mt-1 text-xs text-emerald-600">Finalized: {{ $hrStats['finalized_payroll_periods'] }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs uppercase tracking-[0.25em] text-slate-400">Payroll to pay</div>
            <div class="mt-2 text-2xl font-semibold text-rose-600">{{ $hrStats['payroll_items_to_pay'] }}</div>
            <div class="mt-1 text-xs text-slate-500">Pending disbursements</div>
        </div>
    </div>
@endsection
