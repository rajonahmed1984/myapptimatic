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

    <div class="card p-6">
        <div class="max-w-4xl">
            <div class="section-label">Add leave type</div>

            <form method="POST" action="{{ route('admin.hr.leave-types.store') }}" class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
                @csrf
                <input name="name" placeholder="Name" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-2">
                <input name="code" placeholder="Code" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_paid" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                    <span class="text-xs text-slate-600">Paid</span>
                </div>
                <input type="number" step="0.01" name="default_allocation" placeholder="Default days" class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                <div class="md:col-span-4">
                    <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Add</button>
                </div>
            </form>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
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
                        <td class="py-2 px-3">{{ $type->default_allocation ?? '∞' }}</td>
                        <td class="py-2 px-3 text-right">
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
    </div>
@endsection
