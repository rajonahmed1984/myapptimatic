@extends('layouts.admin')

@section('title', 'Edit Income Category')
@section('page-title', 'Edit Income Category')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Edit category</div>
        </div>
        <a href="{{ route('admin.income.categories.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6 max-w-3xl">
        <form method="POST" action="{{ route('admin.income.categories.update', $category) }}" class="grid gap-4 text-sm">
            @csrf
            @method('PUT')
            <div>
                <label class="text-xs text-slate-500">Name</label>
                <input name="name" value="{{ old('name', $category->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Status</label>
                <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="active" @selected(old('status', $category->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $category->status) === 'inactive')>Inactive</option>
                </select>
                @error('status')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div>
                <label class="text-xs text-slate-500">Description</label>
                <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description', $category->description) }}</textarea>
                @error('description')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Update</button>
            </div>
        </form>
    </div>
@endsection
