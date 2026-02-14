@extends('layouts.admin')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
    <div class="card p-6">
        <div>
            <div class="section-label">Employee</div>
            <div class="text-2xl font-semibold text-slate-900">Attendance Details</div>
            <div class="text-sm text-slate-500">Daily attendance recorded by HR.</div>
        </div>

        <form method="GET" action="{{ route('employee.attendance.index') }}" class="mt-4 flex flex-wrap items-end gap-2">
            <div>
                <label for="attendanceMonth" class="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                <input id="attendanceMonth" type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
            <a href="{{ route('employee.attendance.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
        </form>

        <div class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Present</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($statusSummary['present'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Absent</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($statusSummary['absent'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Leave</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($statusSummary['leave'] ?? 0) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Half Day</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ (int) ($statusSummary['half_day'] ?? 0) }}</div>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Date</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Note</th>
                    <th class="py-2">Recorded By</th>
                    <th class="py-2">Updated At</th>
                </tr>
                </thead>
                <tbody>
                @forelse($attendances as $attendance)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $attendance->date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                        <td class="py-2">{{ ucfirst(str_replace('_', ' ', (string) $attendance->status)) }}</td>
                        <td class="py-2">{{ $attendance->note ?? '--' }}</td>
                        <td class="py-2">{{ $attendance->recorder?->name ?? '--' }}</td>
                        <td class="py-2">{{ $attendance->updated_at?->format(($globalDateFormat ?? 'Y-m-d').' H:i') ?? '--' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-3 text-center text-slate-500">No attendance records for this month.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $attendances->links() }}</div>
    </div>
@endsection

