@extends('layouts.admin')

@section('title', 'Leave Types')
@section('page-title', 'Leave Types')

@section('content')
    <div class="card p-6 max-w-3xl">
        <div class="section-label">HR</div>
        <div class="text-2xl font-semibold text-slate-900">Leave types</div>

        <form method="POST" action="{{ route('admin.hr.leave-types.store') }}" class="mt-4 grid gap-3 md:grid-cols-4 text-sm">
            @csrf
            <input name="name" placeholder="Name" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm md:col-span-2">
            <input name="code" placeholder="Code" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_paid" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                <span class="text-xs text-slate-600">Paid</span>
            </div>
            <input type="number" step="0.01" name="default_allocation" placeholder="Default days" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            <div class="md:col-span-4">
                <button class="rounded-full bg-emerald-600 px-4 py-2 text-white text-sm font-semibold hover:bg-emerald-500">Add</button>
            </div>
        </form>

        <div class="mt-4">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Name</th>
                    <th class="py-2">Code</th>
                    <th class="py-2">Paid</th>
                    <th class="py-2">Default</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($types as $type)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $type->name }}</td>
                        <td class="py-2">{{ $type->code }}</td>
                        <td class="py-2">{{ $type->is_paid ? 'Yes' : 'No' }}</td>
                        <td class="py-2">{{ $type->default_allocation ?? '—' }}</td>
                        <td class="py-2 text-right">
                            <form method="POST" action="{{ route('admin.hr.leave-types.destroy', $type) }}" onsubmit="return confirm('Delete type?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 text-center text-slate-500">No leave types.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $types->links() }}</div>
    </div>
@endsection
