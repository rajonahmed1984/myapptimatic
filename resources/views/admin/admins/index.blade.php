@extends('layouts.admin')

@section('title', 'Admin Users')
@section('page-title', 'Admin Users')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Admin Users</h1>
        <a href="{{ route('admin.admins.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Admin</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[700px] text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($admins as $admin)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $admin->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $admin->email }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $admin->created_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.admins.edit', $admin) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.admins.destroy', $admin) }}"
                                    data-delete-confirm
                                    data-confirm-name="{{ $admin->name }}"
                                    data-confirm-title="Delete admin {{ $admin->name }}?"
                                    data-confirm-description="This will permanently delete the admin user."
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
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No admin users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

