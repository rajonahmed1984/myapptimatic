@extends('layouts.admin')

@section('title', 'Leave Types')
@section('page-title', 'Leave Types')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Leave types</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card p-6 lg:col-span-2">
            <div class="section-label">Add leave type</div>

            <form method="POST" action="{{ route('admin.hr.leave-types.store') }}" class="mt-4 grid gap-3 text-sm">
                @csrf
                <input name="name" value="{{ old('name') }}" placeholder="Name" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <input name="code" value="{{ old('code') }}" placeholder="Code" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_paid" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(old('is_paid'))>
                    <span class="text-xs text-slate-600">Paid</span>
                </div>
                <input type="number" step="0.01" name="default_allocation" value="{{ old('default_allocation') }}" placeholder="Default days" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Add</button>
            </form>

            @if($editingType)
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <div class="section-label">Edit leave type</div>
                    <form method="POST" action="{{ route('admin.hr.leave-types.update', $editingType) }}" class="mt-4 grid gap-3 text-sm">
                        @csrf
                        @method('PUT')
                        <input name="name" value="{{ old('name', $editingType->name) }}" placeholder="Name" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <input name="code" value="{{ old('code', $editingType->code) }}" placeholder="Code" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="is_paid" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600" @checked(old('is_paid', $editingType->is_paid))>
                            <span class="text-xs text-slate-600">Paid</span>
                        </div>
                        <input type="number" step="0.01" name="default_allocation" value="{{ old('default_allocation', $editingType->default_allocation) }}" placeholder="Default days" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <div class="flex items-center gap-3">
                            <button class="rounded-full bg-slate-900 px-4 py-2 text-white text-sm font-semibold hover:bg-slate-700">Update</button>
                            <a href="{{ route('admin.hr.leave-types.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="card p-6 lg:col-span-3">
            <div class="overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                <table class="min-w-full text-sm text-slate-700">
                    <thead>
                    <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Name</th>
                        <th class="py-2 px-3">Code</th>
                        <th class="py-2 px-3">Paid</th>
                        <th class="py-2 px-3">Default</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($types as $type)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3">{{ $type->name }}</td>
                            <td class="py-2 px-3">{{ $type->code }}</td>
                            <td class="py-2 px-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $type->is_paid ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50' }}">
                                    {{ $type->is_paid ? 'Paid' : 'Unpaid' }}
                                </span>
                            </td>
                            <td class="py-2 px-3">{{ $type->default_allocation ?? 'inf' }}</td>
                            <td class="py-2 px-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.hr.leave-types.index', ['edit' => $type->id]) }}" class="text-xs font-semibold text-slate-700 hover:text-slate-900">Edit</a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.hr.leave-types.destroy', $type) }}"
                                        data-delete-confirm
                                        data-confirm-name="{{ $type->name }}"
                                        data-confirm-title="Delete leave type {{ $type->name }}?"
                                        data-confirm-description="This will permanently delete the leave type."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 px-3 text-center text-slate-500">No leave types yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $types->links() }}</div>
        </div>
    </div>
@endsection
