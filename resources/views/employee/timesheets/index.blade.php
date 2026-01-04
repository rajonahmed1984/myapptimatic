@extends('layouts.admin')

@section('title', 'Timesheets')
@section('page-title', 'Timesheets')

@section('content')
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Employee</div>
                <div class="text-2xl font-semibold text-slate-900">Submit timesheet</div>
                <div class="text-sm text-slate-500">Weekly or date-range hours.</div>
            </div>
        </div>

        <form method="POST" action="{{ route('employee.timesheets.store') }}" class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Period start</label>
                <input type="date" name="period_start" value="{{ old('period_start') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Period end</label>
                <input type="date" name="period_end" value="{{ old('period_end') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Total hours</label>
                <input type="number" step="0.01" name="total_hours" value="{{ old('total_hours') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-1">
                <label class="text-xs text-slate-500">Notes</label>
                <input name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional">
            </div>
            <div class="md:col-span-4">
                <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Submit</button>
            </div>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Period</th>
                    <th class="py-2">Hours</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Submitted</th>
                </tr>
                </thead>
                <tbody>
                @forelse($timesheets as $sheet)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $sheet->period_start?->format($globalDateFormat) }} - {{ $sheet->period_end?->format($globalDateFormat) }}</td>
                        <td class="py-2">{{ $sheet->total_hours }}</td>
                        <td class="py-2">{{ ucfirst($sheet->status) }}</td>
                        <td class="py-2">{{ $sheet->submitted_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-3 text-center text-slate-500">No timesheets yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $timesheets->links() }}</div>
    </div>
@endsection
