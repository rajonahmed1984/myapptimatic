@extends('layouts.admin')

@section('title', 'Work Logs')
@section('page-title', 'Work Logs')

@section('content')
    @php
        $formatDuration = function (int $seconds): string {
            $hours = (int) floor($seconds / 3600);
            $minutes = (int) floor(($seconds % 3600) / 60);
            $secs = (int) ($seconds % 60);

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <form method="GET" action="{{ route('admin.hr.timesheets.index') }}" class="mb-5 grid gap-3 md:grid-cols-4">
                <div>
                    <label for="workLogMonth" class="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                    <input id="workLogMonth" type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="workLogEmployee" class="text-xs uppercase tracking-[0.2em] text-slate-500">Employee</label>
                    <select id="workLogEmployee" name="employee_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">All employees</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected($selectedEmployeeId === $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                    <a href="{{ route('admin.hr.timesheets.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-6">
        

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Employee</th>
                    <th class="py-2 px-3">Date</th>
                    <th class="py-2 px-3">Sessions</th>
                    <th class="py-2 px-3">First Start</th>
                    <th class="py-2 px-3">Last Activity</th>
                    <th class="py-2 px-3 text-right">Active Time</th>
                    <th class="py-2 px-3 text-right">Required</th>
                    <th class="py-2 px-3 text-right">Coverage</th>
                    <th class="py-2 px-3 text-right">Est. Salary</th>
                </tr>
                </thead>
                <tbody>
                @forelse($dailyLogs as $log)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $log->employee?->name ?? '--' }}</td>
                        <td class="py-2 px-3">{{ $log->work_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                        <td class="py-2 px-3">{{ (int) ($log->sessions_count ?? 0) }}</td>
                        <td class="py-2 px-3">{{ $log->first_started_at ? \Illuminate\Support\Carbon::parse($log->first_started_at)->format('H:i:s') : '--' }}</td>
                        <td class="py-2 px-3">{{ $log->last_activity_at ? \Illuminate\Support\Carbon::parse($log->last_activity_at)->format('H:i:s') : '--' }}</td>
                        <td class="py-2 px-3 text-right">{{ $formatDuration((int) ($log->active_seconds ?? 0)) }}</td>
                        <td class="py-2 px-3 text-right">{{ $formatDuration((int) ($log->required_seconds ?? 0)) }}</td>
                        <td class="py-2 px-3 text-right">{{ (int) ($log->coverage_percent ?? 0) }}%</td>
                        <td class="py-2 px-3 text-right">{{ $log->currency ?? 'BDT' }} {{ number_format((float) ($log->estimated_amount ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-3 px-3 text-center text-slate-500">No work logs.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $dailyLogs->links() }}</div>
    </div>
@endsection
