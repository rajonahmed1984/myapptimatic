@extends('layouts.admin')

@section('title', 'Income Categories')
@section('page-title', 'Income Categories')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Income categories</div>
        </div>
        <a href="{{ route('admin.income.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back to Income</a>
    </div>

    <div class="card p-6">
        <div class="section-label">Add category</div>
        <form method="POST" action="{{ route('admin.income.categories.store') }}" class="mt-4 grid gap-3 text-sm md:grid-cols-4">
            @csrf
            <div class="md:col-span-2">
                <input name="name" value="{{ old('name') }}" placeholder="Category name" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @error('name')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-1">
                <select name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                </select>
                @error('status')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-4">
                <textarea name="description" rows="2" placeholder="Description (optional)" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('description') }}</textarea>
                @error('description')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="md:col-span-4">
                <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add Category</button>
            </div>
        </form>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
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
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $category->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                    {{ ucfirst($category->status) }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-slate-500">{{ $category->description ?? '--' }}</td>
                            <td class="py-2 px-3 text-right">
                                <div class="flex justify-end gap-3 text-xs font-semibold">
                                    <a href="{{ route('admin.income.categories.edit', $category) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                    <form method="POST" action="{{ route('admin.income.categories.destroy', $category) }}">
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
@endsection
