@extends('layouts.admin')

@section('title', 'Paid Holidays')
@section('page-title', 'Paid Holidays')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Paid Holiday Calendar</div>
            <div class="text-sm text-slate-500">Marked days are treated as fully paid days in salary calculations.</div>
        </div>
    </div>

    <div class="card p-6">
        <form method="GET" action="{{ route('admin.hr.paid-holidays.index') }}" class="mb-5 flex flex-wrap items-end gap-2">
            <div>
                <label for="paidHolidayMonth" class="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                <input id="paidHolidayMonth" type="month" name="month" value="{{ $selectedMonth }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
            <a href="{{ route('admin.hr.paid-holidays.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Current month</a>
        </form>

        <div class="max-w-4xl">
            <div class="section-label">Add paid holiday</div>
            <form method="POST" action="{{ route('admin.hr.paid-holidays.store') }}" class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
                @csrf
                <input type="date" name="holiday_date" value="{{ old('holiday_date') }}" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                <select name="name" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-2" required>
                    <option value="">Select holiday type</option>
                    @foreach($holidayTypes as $holidayType)
                        <option value="{{ $holidayType }}" @selected(old('name') === $holidayType)>{{ $holidayType }}</option>
                    @endforeach
                </select>
                <input name="note" value="{{ old('note') }}" placeholder="Optional note" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <div class="md:col-span-4">
                    <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Save holiday</button>
                </div>
            </form>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Date</th>
                    <th class="py-2 px-3">Name</th>
                    <th class="py-2 px-3">Note</th>
                    <th class="py-2 px-3">Type</th>
                    <th class="py-2 px-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($holidays as $holiday)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $holiday->holiday_date?->format($globalDateFormat ?? 'Y-m-d') ?? '--' }}</td>
                        <td class="py-2 px-3 font-semibold text-slate-900">{{ $holiday->name }}</td>
                        <td class="py-2 px-3">{{ $holiday->note ?? '--' }}</td>
                        <td class="py-2 px-3">
                            <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                Paid
                            </span>
                        </td>
                        <td class="py-2 px-3 text-right">
                            <form
                                method="POST"
                                action="{{ route('admin.hr.paid-holidays.destroy', $holiday) }}"
                                data-delete-confirm
                                data-confirm-name="{{ $holiday->name }}"
                                data-confirm-title="Delete paid holiday {{ $holiday->name }}?"
                                data-confirm-description="This date will no longer be auto-paid in salary calculations."
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-4 px-3 text-center text-slate-500">No paid holidays found for this month.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $holidays->links() }}</div>
    </div>
@endsection
