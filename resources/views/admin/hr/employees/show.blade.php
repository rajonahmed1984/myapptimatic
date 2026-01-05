@extends('layouts.admin')

@section('title', $employee->name)
@section('page-title', $employee->name)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Employee</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $employee->name }}</div>
            <div class="text-sm text-slate-500">{{ $employee->email }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
            <form action="{{ route('admin.hr.employees.impersonate', $employee) }}" method="POST">
                @csrf
                <button type="submit" class="rounded-full border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-800">
                    Login as Employee
                </button>
            </form>
            <a href="{{ route('admin.hr.employees.edit', $employee) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 text-sm font-semibold text-slate-700">
        @php $tabs = ['summary' => 'Summary', 'profile' => 'Profile', 'compensation' => 'Compensation', 'timesheets' => 'Timesheets', 'leave' => 'Leave', 'payroll' => 'Payroll']; @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('admin.hr.employees.show', ['employee' => $employee->id, 'tab' => $key]) }}"
               class="rounded-full border px-3 py-1 {{ $tab === $key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-700 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($tab === 'summary')
        <div class="grid gap-4 md:grid-cols-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($employee->status) }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Salary Type</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ ucfirst($summary['salary_type'] ?? '--') }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-[0.28em] text-slate-500">Basic Pay</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
            </div>
        </div>
    @elseif($tab === 'profile')
        <div class="card p-6">
            <div class="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                <div><span class="font-semibold text-slate-900">Department:</span> {{ $employee->department ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Designation:</span> {{ $employee->designation ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Manager:</span> {{ $employee->manager?->name ?? '--' }}</div>
                <div><span class="font-semibold text-slate-900">Employment Type:</span> {{ ucfirst($employee->employment_type) }}</div>
                <div><span class="font-semibold text-slate-900">Work Mode:</span> {{ ucfirst(str_replace('_',' ',$employee->work_mode)) }}</div>
                <div><span class="font-semibold text-slate-900">Join Date:</span> {{ $employee->join_date?->format('Y-m-d') ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Address:</span> {{ $employee->address ?? '--' }}</div>
                <div class="md:col-span-2"><span class="font-semibold text-slate-900">Linked User:</span> {{ $employee->user?->name ? $employee->user->name.' ('.$employee->user->email.')' : '--' }}</div>
            </div>
        </div>
    @elseif($tab === 'compensation')
        <div class="card p-6">
            <div class="text-sm text-slate-700">
                <div class="font-semibold text-slate-900 mb-2">Current Compensation</div>
                <div>Salary Type: {{ ucfirst($summary['salary_type'] ?? '--') }}</div>
                <div>Basic Pay: {{ $summary['currency'] ?? '' }} {{ number_format($summary['basic_pay'] ?? 0, 2) }}</div>
                <div>Effective From: {{ $employee->activeCompensation?->effective_from?->format('Y-m-d') ?? '--' }}</div>
            </div>
        </div>
    @else
        <div class="card p-6 text-sm text-slate-600">
            No data available for this tab yet.
        </div>
    @endif
@endsection
