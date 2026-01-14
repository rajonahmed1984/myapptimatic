@extends('layouts.admin')

@section('title', 'Admin Users')
@section('page-title', 'Admin Users')

@php
    $roleLabels = $roles ?? [];
    $currentRoleLabel = $roleLabels[$selectedRole] ?? ucfirst(str_replace('_', ' ', $selectedRole));
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="section-label">User Management</div>
            <div class="text-xl font-semibold text-slate-900">{{ $currentRoleLabel }}</div>
        </div>
        <a href="{{ route('admin.users.create', $selectedRole) }}"
           class="inline-flex items-center gap-2 rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-600">
            New {{ $currentRoleLabel }}
        </a>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach($roleLabels as $roleValue => $label)
            <a href="{{ route('admin.users.index', $roleValue) }}"
               class="rounded-full border px-3 py-1 text-sm font-semibold transition
               {{ $selectedRole === $roleValue ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-300 hover:text-teal-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-3">Name</th>
                        <th class="py-3">Email</th>
                        <th class="py-3">Role</th>
                        <th class="py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr>
                            <td class="py-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar :path="$user->avatar_path" :name="$user->name" size="h-8 w-8" textSize="text-xs" />
                                    <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                                </div>
                            </td>
                            <td class="py-3 text-slate-600">{{ $user->email }}</td>
                            <td class="py-3 text-slate-600">{{ $roleLabels[$user->role] ?? $user->role }}</td>
                            <td class="py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-sm font-semibold text-teal-600 hover:text-teal-700">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-700">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-slate-500">No users found for this role.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
