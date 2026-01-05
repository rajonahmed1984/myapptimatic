@extends('layouts.admin')

@section('title', 'Employees')
@section('page-title', 'Employees')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Employees</div>
        </div>
        <a href="{{ route('admin.hr.employees.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add employee</a>
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">ID</th>
                    <th class="py-2 px-3">Name</th>
                    <th class="py-2 px-3">Designation</th>
                    <th class="py-2 px-3">Employment</th>
                    <th class="py-2 px-3">Manager</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3 font-semibold text-slate-900">#{{ $employee->id }}</td>
                        <td class="py-2 px-3">
                            <div class="font-semibold text-slate-900">
                                <a href="{{ route('admin.hr.employees.show', $employee) }}" class="hover:text-teal-600">
                                    {{ $employee->name }}
                                </a>
                            </div>
                            <div class="text-xs text-slate-500">{{ $employee->email }}</div>
                        </td>
                        <td class="py-2 px-3">{{ $employee->designation ?? '--' }}</td>
                        <td class="py-2 px-3">{{ ucfirst($employee->employment_type ?? '--') }}</td>
                        <td class="py-2 px-3">{{ $employee->manager?->name ?? '--' }}</td>
                        <td class="py-2 px-3">
                            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $employee->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-200 text-slate-600 bg-slate-50' }}">
                                {{ ucfirst($employee->status) }}
                            </span>
                        </td>
                        <td class="py-2 px-3 text-right space-x-2">
                            <a href="{{ route('admin.hr.employees.edit', $employee) }}" class="text-xs text-emerald-700 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.hr.employees.destroy', $employee) }}" class="inline" onsubmit="return confirm('Delete this employee?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-rose-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-3 px-3 text-center text-slate-500">No employees found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $employees->links() }}</div>
    </div>
@endsection
