@extends('layouts.admin')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Daily Manual Attendance</div>
            <div class="text-sm text-slate-500">Only active full-time employees are listed.</div>
            @if(!empty($isPaidHoliday))
                <div class="mt-2 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                    Paid holiday: employees default to Present for this date
                </div>
            @endif
        </div>
    </div>

    <div class="card p-6">
        <form method="GET" action="{{ route('admin.hr.attendance.index') }}" class="mb-5 flex flex-wrap items-end gap-2" data-ajax-form="true">
            <div>
                <label for="attendanceDate" class="text-xs uppercase tracking-[0.2em] text-slate-500">Date</label>
                <input id="attendanceDate" type="date" name="date" value="{{ $selectedDate }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
            <a href="{{ route('admin.hr.attendance.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Today</a>
        </form>

        <form method="POST" action="{{ route('admin.hr.attendance.store') }}" data-ajax-form="true">
            @csrf
            <input type="hidden" name="date" value="{{ $selectedDate }}">

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-slate-700">
                    <thead>
                    <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Employee</th>
                        <th class="py-2 px-3">Department</th>
                        <th class="py-2 px-3">Designation</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">Note</th>
                        <th class="py-2 px-3">Recorded By</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($employees as $index => $employee)
                        @php($entry = $attendanceByEmployee->get($employee->id))
                        @php($defaultStatus = old("records.$index.status", $entry?->status ?? ((!empty($isPaidHoliday)) ? 'present' : null)))
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3">
                                <div class="font-semibold text-slate-900">{{ $employee->name }}</div>
                                <div class="text-xs text-slate-500">{{ $employee->email }}</div>
                                <input type="hidden" name="records[{{ $index }}][employee_id]" value="{{ $employee->id }}">
                            </td>
                            <td class="py-2 px-3">{{ $employee->department ?? '--' }}</td>
                            <td class="py-2 px-3">{{ $employee->designation ?? '--' }}</td>
                            <td class="py-2 px-3">
                                <select name="records[{{ $index }}][status]" class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs">
                                    <option value="" @selected($defaultStatus === null)>Not set</option>
                                    <option value="present" @selected($defaultStatus === 'present')>Present</option>
                                    <option value="absent" @selected($defaultStatus === 'absent')>Absent</option>
                                    <option value="leave" @selected($defaultStatus === 'leave')>Leave</option>
                                    <option value="half_day" @selected($defaultStatus === 'half_day')>Half Day</option>
                                </select>
                            </td>
                            <td class="py-2 px-3">
                                <input
                                    type="text"
                                    name="records[{{ $index }}][note]"
                                    value="{{ old("records.$index.note", $entry?->note) }}"
                                    placeholder="Optional note"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs"
                                >
                            </td>
                            <td class="py-2 px-3 text-xs text-slate-500">
                                @if($entry?->recorder?->name)
                                    {{ $entry->recorder->name }}
                                @elseif(!empty($isPaidHoliday) && $defaultStatus === 'present')
                                    Paid holiday (System)
                                @else
                                    --
                                @endif
                                @if($entry?->updated_at)
                                    <div>{{ $entry->updated_at->format(($globalDateFormat ?? 'Y-m-d').' H:i') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-3 px-3 text-center text-slate-500">No active full-time employees found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($employees->isNotEmpty())
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Attendance</button>
                </div>
            @endif
        </form>
    </div>
@endsection
