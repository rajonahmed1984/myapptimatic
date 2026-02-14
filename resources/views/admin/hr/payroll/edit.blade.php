@extends('layouts.admin')

@section('title', 'Edit Payroll Period')
@section('page-title', 'Edit Payroll Period')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Edit Payroll Period {{ $period->period_key }}</div>
            <div class="text-sm text-slate-500">Only draft payroll periods can be edited.</div>
        </div>
        <a href="{{ route('admin.hr.payroll.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6 max-w-2xl">
        <form method="POST" action="{{ route('admin.hr.payroll.update', $period) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label for="periodKey" class="text-xs uppercase tracking-[0.2em] text-slate-500">Period Key</label>
                <input id="periodKey" name="period_key" value="{{ old('period_key', $period->period_key) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="YYYY-MM" required>
            </div>
            <div>
                <label for="startDate" class="text-xs uppercase tracking-[0.2em] text-slate-500">Start Date</label>
                <input id="startDate" type="date" name="start_date" value="{{ old('start_date', $period->start_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
            </div>
            <div>
                <label for="endDate" class="text-xs uppercase tracking-[0.2em] text-slate-500">End Date</label>
                <input id="endDate" type="date" name="end_date" value="{{ old('end_date', $period->end_date?->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
            </div>
            <div class="md:col-span-2 flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Changes</button>
                <a href="{{ route('admin.hr.payroll.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
            </div>
        </form>
    </div>
@endsection
