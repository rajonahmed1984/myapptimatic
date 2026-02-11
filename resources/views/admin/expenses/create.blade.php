@extends('layouts.admin')

@section('title', 'Add Expense')
@section('page-title', 'Add Expense')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">New one-time expense</div>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
    </div>

    <div class="card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.expenses.store') }}" enctype="multipart/form-data" class="grid gap-4 text-sm">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Category</label>
                <select name="category_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Title</label>
                <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('title')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('amount')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Expense date</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    @error('expense_date')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Receipt (jpg/png/pdf)</label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block text-xs text-slate-600">
                @error('attachment')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="generate_invoice" value="0">
                <input type="checkbox" name="generate_invoice" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                <span class="text-xs text-slate-600">Generate expense invoice</span>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save Expense</button>
            </div>
        </form>
    </div>
@endsection
