@extends('layouts.admin')

@section('title', 'Expense Categories')
@section('page-title', 'Expense Categories')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Expense categories</div>
        </div>
        <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to Expenses</a>
    </div>

    @php
        $editId = old('edit_id', request('edit'));
        $editCategory = $editId ? $categories->firstWhere('id', (int) $editId) : null;
        $isEditing = $editCategory !== null;
        $formAction = $isEditing
            ? route('admin.expenses.categories.update', $editCategory)
            : route('admin.expenses.categories.store');
        $formLabel = $isEditing ? 'Edit category' : 'Add category';
        $buttonLabel = $isEditing ? 'Update Category' : 'Add Category';
        $defaultStatus = $isEditing ? $editCategory->status : 'active';
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1fr_2fr]">
        <div class="card p-6">
            <div class="section-label">{{ $formLabel }}</div>
            @if($isEditing)
                <div class="mt-2 text-xs text-slate-500">
                    Editing: <span class="font-semibold text-slate-700">{{ $editCategory->name }}</span>
                </div>
            @endif
            <form method="POST" action="{{ $formAction }}" class="mt-4 grid gap-3 text-sm">
                @csrf
                @if($isEditing)
                    @method('PUT')
                    <input type="hidden" name="edit_id" value="{{ $editCategory->id }}">
                @endif
                <div>
                    <input name="name" value="{{ old('name', $editCategory->name ?? '') }}" placeholder="Category name" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    @error('name')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <select name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', $defaultStatus) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $defaultStatus) === 'inactive')>Inactive</option>
                    </select>
                    @error('status')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <textarea name="description" rows="3" placeholder="Description (optional)" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">{{ old('description', $editCategory->description ?? '') }}</textarea>
                    @error('description')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">{{ $buttonLabel }}</button>
                    @if($isEditing)
                        <a href="{{ route('admin.expenses.categories.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700">Cancel edit</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card p-6">
            <div class="section-label">Category list</div>
            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                <table class="min-w-full text-sm text-slate-700">
                    <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="py-2 px-3">Name</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Description</th>
                            <th class="py-2 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 px-3 font-semibold text-slate-900">{{ $category->name }}</td>
                                <td class="py-2 px-3">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $category->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50' }}">
                                        {{ ucfirst($category->status) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-slate-500">{{ $category->description ?? '--' }}</td>
                                <td class="py-2 px-3 text-right">
                                    <div class="flex justify-end gap-3 text-xs font-semibold">
                                        <a href="{{ route('admin.expenses.categories.index', ['edit' => $category->id]) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                        <form
                                            method="POST"
                                            action="{{ route('admin.expenses.categories.destroy', $category) }}"
                                            data-delete-confirm
                                            data-confirm-name="{{ $category->name }}"
                                            data-confirm-title="Delete category {{ $category->name }}?"
                                            data-confirm-description="This will permanently delete the expense category."
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 px-3 text-center text-slate-500">No categories yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
