@extends('layouts.admin')

@section('title', 'Add Income')
@section('page-title', 'Add Income')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">New income</div>
        </div>
        <a href="{{ route('admin.income.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.income.store') }}" enctype="multipart/form-data" class="grid gap-4 text-sm">
            @csrf
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Category</label>
                    <select name="income_category_id" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('income_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('income_category_id')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @error('title')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @error('amount')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Income date</label>
                    <input type="date" name="income_date" value="{{ old('income_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @error('income_date')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div>
                <label class="text-xs text-slate-500">Notes</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                @error('notes')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Attachment (jpg/png/pdf)</label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block text-xs text-slate-600">
                @error('attachment')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save Income</button>
            </div>
        </form>
    </div>
@endsection
