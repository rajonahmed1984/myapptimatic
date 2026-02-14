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
        $estimatedSubtotal = (float) $dailyLogs->getCollection()->sum(fn ($log) => (float) ($log->estimated_amount ?? 0));
        $subtotalCurrency = $dailyLogs->getCollection()->first()?->currency ?? 'BDT';
    @endphp

    <div class="card p-6">
        <div class="flex items-center justify-between">
            <form method="GET" action="{{ route('employee.timesheets.index') }}" class="mt-4 flex flex-wrap items-end gap-2">
                <div>
                    <input id="employeeWorkLogMonth" type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                <a href="{{ route('employee.timesheets.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
            </form>
        </div>

        @if(! $isEligible)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Work session tracking is enabled for remote full-time/part-time employees only.
            </div>
        @endif

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Date</th>
                    <th class="py-2">Sessions</th>
                    <th class="py-2">First Start</th>
                    <th class="py-2">Last Activity</th>
                    <th class="py-2 text-right">Active Time</th>
                    <th class="py-2 text-right">Required</th>
                    <th class="py-2 text-right">Coverage</th>
                    <th class="py-2 text-right">Est. Salary</th>
                </tr>
                </thead>
                <tbody>
                @forelse($dailyLogs as $log)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $log->work_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                        <td class="py-2">{{ (int) ($log->sessions_count ?? 0) }}</td>
                        <td class="py-2">{{ $log->first_started_at ? \Illuminate\Support\Carbon::parse($log->first_started_at)->format('H:i:s') : '--' }}</td>
                        <td class="py-2">{{ $log->last_activity_at ? \Illuminate\Support\Carbon::parse($log->last_activity_at)->format('H:i:s') : '--' }}</td>
                        <td class="py-2 text-right">{{ $formatDuration((int) ($log->active_seconds ?? 0)) }}</td>
                        <td class="py-2 text-right">{{ $formatDuration((int) ($log->required_seconds ?? 0)) }}</td>
                        <td class="py-2 text-right">{{ (int) ($log->coverage_percent ?? 0) }}%</td>
                        <td class="py-2 text-right">{{ $log->currency ?? 'BDT' }} {{ number_format((float) ($log->estimated_amount ?? 0), 2) }}</td>
                    </tr>
                    @if($loop->last)
                        <tr class="border-t border-slate-300 bg-slate-50/70">
                            <td colspan="7" class="py-2 text-right font-semibold text-slate-800">Est. Salary Subtotal (This Page)</td>
                            <td class="py-2 text-right font-semibold text-slate-900">{{ $subtotalCurrency }} {{ number_format($estimatedSubtotal, 2) }}</td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="py-3 text-center text-slate-500">No work logs yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $dailyLogs->links() }}</div>
    </div>
@endsection
