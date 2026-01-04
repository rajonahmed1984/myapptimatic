@extends('layouts.admin')

@section('title', 'Employees')
@section('page-title', 'Employees')

@section('content')
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="section-label">HR</div>
                <div class="text-2xl font-semibold text-slate-900">Employees</div>
            </div>
            <a href="{{ route('admin.hr.employees.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add employee</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Name</th>
                    <th class="py-2">Designation</th>
                    <th class="py-2">Employment</th>
                    <th class="py-2">Manager</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $employee->name }}</td>
                        <td class="py-2">{{ $employee->designation ?? '—' }}</td>
                        <td class="py-2">{{ ucfirst(str_replace('_',' ', $employee->employment_type)) }}</td>
                        <td class="py-2">{{ $employee->manager?->name ?? '—' }}</td>
                        <td class="py-2">{{ ucfirst($employee->status) }}</td>
                        <td class="py-2 text-right space-x-2">
                            <a href="{{ route('admin.hr.employees.edit', $employee) }}" class="text-xs text-emerald-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.hr.employees.destroy', $employee) }}" class="inline" onsubmit="return confirm('Delete employee?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-slate-500">No employees yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $employees->links() }}</div>
    </div>
@endsection
