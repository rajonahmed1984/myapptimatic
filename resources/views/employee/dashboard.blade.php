@extends('layouts.admin')

@section('title', 'Employee Dashboard')
@section('page-title', 'Employee Dashboard')

@section('content')
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Welcome</div>
                <div class="text-2xl font-semibold text-slate-900">Employee portal</div>
                <div class="text-sm text-slate-500">Access timesheets, leave requests, and payroll once enabled.</div>
            </div>
            <form method="POST" action="{{ route('employee.logout') }}">
                @csrf
                <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-600">
                    Logout
                </button>
            </form>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Timesheets</div>
                <div class="mt-2 text-slate-900 font-semibold">Submit your weekly hours.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Leave</div>
                <div class="mt-2 text-slate-900 font-semibold">Request time off and track approvals.</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payroll</div>
                <div class="mt-2 text-slate-900 font-semibold">View payroll history and payslip data.</div>
            </div>
        </div>
    </div>
@endsection
