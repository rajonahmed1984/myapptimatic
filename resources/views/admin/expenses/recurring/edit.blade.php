@extends('layouts.admin')

@section('title', 'Edit Recurring Expense')
@section('page-title', 'Edit Recurring Expense')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Edit recurring expense</div>
        </div>
        <a href="{{ route('admin.expenses.recurring.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
    </div>

    <div class="card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.expenses.recurring.update', $recurringExpense) }}" class="grid gap-4 text-sm">
            @csrf
            @method('PUT')
            <div>
                <label class="text-xs text-slate-500">Category</label>
                <select name="category_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $recurringExpense->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Title</label>
                <input name="title" value="{{ old('title', $recurringExpense->title) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('title')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $recurringExpense->amount) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('amount')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Recurrence</label>
                    <div class="mt-1 flex gap-2">
                        <select name="recurrence_type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="monthly" @selected(old('recurrence_type', $recurringExpense->recurrence_type) === 'monthly')>Monthly</option>
                            <option value="yearly" @selected(old('recurrence_type', $recurringExpense->recurrence_type) === 'yearly')>Yearly</option>
                        </select>
                        <input type="number" min="1" name="recurrence_interval" value="{{ old('recurrence_interval', $recurringExpense->recurrence_interval ?? 1) }}" class="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" title="Interval">
                    </div>
                    @error('recurrence_type')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                    @error('recurrence_interval')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($recurringExpense->start_date)->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('start_date')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">End date (optional)</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($recurringExpense->end_date)->toDateString()) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('end_date')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes', $recurringExpense->notes) }}</textarea>
                @error('notes')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Update</button>
            </div>
        </form>
    </div>
@endsection
