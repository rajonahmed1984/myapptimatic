@extends('layouts.admin')

@section('title', 'Edit User')
@section('page-title', 'Edit User')

@php
    $roleLabels = $roles ?? [];
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Edit User</h1>
        <a href="{{ route('admin.users.index', $user->role) }}" class="text-sm text-slate-500 hover:text-teal-600">Back to users</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-6 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm text-slate-600">Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Email</label>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Password (leave blank to keep)</label>
                <input name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Confirm Password</label>
                <input name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Role</label>
                <select name="role" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    @foreach($roleLabels as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
