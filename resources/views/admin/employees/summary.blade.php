@extends('layouts.admin')

@section('title', 'Employee Activity Summary')
@section('page-title', 'Employee Activity')

@php
    $formatDuration = function ($seconds) {
        $total = max(0, (int) $seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    };

    $formatDateTime = function ($value) {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '--';
    };
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Workforce</div>
            <div class="text-2xl font-semibold text-slate-900">Employee Activity</div>
            <div class="text-sm text-slate-500">Sessions and active time with live online indicator.</div>
        </div>
    </div>

    <div class="mb-6 card p-6">
        <form method="GET" action="{{ route('admin.employees.summary') }}" class="grid gap-4 md:grid-cols-5">
            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</label>
                <select name="employee_id" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-teal-400 focus:outline-none">
                    <option value="">All employees</option>
                    @foreach($employeeOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) $filters['employee_id'] === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-teal-400 focus:outline-none" />
            </div>
            <div>
                <label class="text-xs uppercase tracking-[0.2em] text-slate-500">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-teal-400 focus:outline-none" />
            </div>
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Filter</button>
                <a href="{{ route('admin.employees.summary') }}" class="text-sm text-slate-600 hover:text-teal-600">Reset</a>
            </div>
        </form>
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Employee</th>
                    <th class="py-2 px-3">Today</th>
                    <th class="py-2 px-3">This Week</th>
                    <th class="py-2 px-3">This Month</th>
                    @if($showRange)
                        <th class="py-2 px-3">Selected Range</th>
                    @endif
                    <th class="py-2 px-3">Last Login</th>
                    <th class="py-2 px-3">Last Seen</th>
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr class="border-b border-slate-100">
                        <td class="py-3 px-3 align-top">
                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full {{ $employee->is_online ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                <x-avatar :path="$employee->photo_path" :name="$employee->name" size="h-8 w-8" textSize="text-xs" />
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $employee->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $employee->email }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $employee->designation ?? '--' }} @if($employee->department) • {{ $employee->department }} @endif</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-3 align-top">
                            <div class="font-semibold text-slate-900">{{ (int) ($employee->today_sessions_count ?? 0) }} session(s)</div>
                            <div class="text-xs text-slate-500">{{ $formatDuration($employee->today_active_seconds ?? 0) }} hrs</div>
                        </td>
                        <td class="py-3 px-3 align-top">
                            <div class="font-semibold text-slate-900">{{ (int) ($employee->week_sessions_count ?? 0) }} session(s)</div>
                            <div class="text-xs text-slate-500">{{ $formatDuration($employee->week_active_seconds ?? 0) }} hrs</div>
                        </td>
                        <td class="py-3 px-3 align-top">
                            <div class="font-semibold text-slate-900">{{ (int) ($employee->month_sessions_count ?? 0) }} session(s)</div>
                            <div class="text-xs text-slate-500">{{ $formatDuration($employee->month_active_seconds ?? 0) }} hrs</div>
                        </td>
                        @if($showRange)
                            <td class="py-3 px-3 align-top">
                                <div class="font-semibold text-slate-900">{{ (int) ($employee->range_sessions_count ?? 0) }} session(s)</div>
                                <div class="text-xs text-slate-500">{{ $formatDuration($employee->range_active_seconds ?? 0) }} hrs</div>
                            </td>
                        @endif
                        <td class="py-3 px-3 align-top">{{ $formatDateTime($employee->last_login_at) }}</td>
                        <td class="py-3 px-3 align-top">{{ $formatDateTime($employee->last_seen_at) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showRange ? 7 : 6 }}" class="py-4 px-3 text-center text-slate-500">No employee activity found for the selected filters.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

