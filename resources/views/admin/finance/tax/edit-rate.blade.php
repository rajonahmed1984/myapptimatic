@extends('layouts.admin')

@section('title', 'Edit Tax Rate')
@section('page-title', 'Edit Tax Rate')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Edit tax rate</div>
        </div>
        <a href="{{ route('admin.finance.tax.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.finance.tax.rates.update', $rate) }}" class="grid gap-4 text-sm">
            @csrf
            @method('PUT')
            <div>
                <label class="text-xs text-slate-500">Name</label>
                <input name="name" value="{{ old('name', $rate->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Rate percent</label>
                <input type="number" step="0.01" min="0" max="100" name="rate_percent" value="{{ old('rate_percent', $rate->rate_percent) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('rate_percent')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Effective from</label>
                    <input type="date" name="effective_from" value="{{ old('effective_from', optional($rate->effective_from)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('effective_from')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Effective to</label>
                    <input type="date" name="effective_to" value="{{ old('effective_to', optional($rate->effective_to)->format('Y-m-d')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('effective_to')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(old('is_active', $rate->is_active))>
                <span class="text-xs text-slate-600">Active</span>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Update Rate</button>
            </div>
        </form>
    </div>
@endsection
