@extends('layouts.admin')

@section('title', 'Employee Payout')
@section('page-title', 'Employee Payout')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <h1 class="text-2xl font-semibold text-slate-900">Create payout</h1>
            <div class="text-sm text-slate-500">Select an employee and payable projects to include.</div>
        </div>
        <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" hx-boost="false">Back to employees</a>
    </div>

    <div class="card p-6 space-y-6">
        <form method="GET" action="{{ route('admin.hr.employee-payouts.create') }}" class="grid gap-3 md:grid-cols-3">
            <div>
                <label class="text-xs text-slate-500">Employee</label>
                <select name="employee_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">Select employee</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($selectedEmployee == $employee->id)>
                            {{ $employee->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Total Earned</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($summary['total'] ?? 0, 2) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Payable</div>
                <div class="mt-2 text-2xl font-semibold text-amber-700">{{ number_format($summary['payable'] ?? 0, 2) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Paid</div>
                <div class="mt-2 text-2xl font-semibold text-emerald-700">{{ number_format($summary['paid'] ?? 0, 2) }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.hr.employee-payouts.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="employee_id" value="{{ $selectedEmployee }}">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Payable earnings</div>
                    <div class="text-xs text-slate-500">Select at least one project.</div>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead>
                            <tr class="text-xs uppercase text-slate-500">
                                <th class="px-2 py-2"><input type="checkbox" class="rounded border-slate-300" onclick="document.querySelectorAll('.earning-checkbox').forEach(cb => cb.checked = this.checked)" /></th>
                                <th class="px-2 py-2">Project</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Payable</th>
                                <th class="px-2 py-2">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($earnings as $earning)
                                <tr class="border-t border-slate-200">
                                    <td class="px-2 py-2">
                                        <input type="checkbox" class="earning-checkbox rounded border-slate-300" name="project_ids[]" value="{{ $earning->id }}" checked>
                                    </td>
                                    <td class="px-2 py-2">
                                        <a class="text-teal-700 hover:text-teal-600" href="{{ route('admin.projects.show', $earning) }}">
                                            {{ $earning->name }}
                                        </a>
                                    </td>
                                    <td class="px-2 py-2">{{ ucfirst($earning->contract_employee_payout_status ?? 'earned') }}</td>
                                    <td class="px-2 py-2">{{ $earning->currency ?? $summary['currency'] }} {{ number_format($earning->contract_employee_payable ?? 0, 2) }}</td>
                                    <td class="px-2 py-2 text-xs text-slate-600">{{ $earning->updated_at?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-2 py-3 text-slate-500">No payable earnings found for the selected employee.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Payout method</label>
                    @php
                        $paymentMethods = \App\Models\PaymentMethod::dropdownOptions();
                    @endphp
                    <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Not set</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}" @selected(old('payout_method') === $method->code)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Reference</label>
                    <input name="reference" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional reference">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Note</label>
                    <input name="note" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional note">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create payout</button>
                <a href="{{ route('admin.hr.employees.index') }}" class="text-sm text-slate-600 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
@endsection
