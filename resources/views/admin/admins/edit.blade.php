@extends('layouts.admin')

@section('title', 'Edit Admin')
@section('page-title', 'Edit Admin')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Edit Admin User</h1>
        <a href="{{ route('admin.admins.index') }}" class="text-sm text-slate-500 hover:text-teal-600">Back to admin users</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.admins.update', $admin) }}" class="grid gap-6 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label class="text-sm text-slate-600">Name</label>
                <input name="name" value="{{ old('name', $admin->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Email</label>
                <input name="email" type="email" value="{{ old('email', $admin->email) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">New Password</label>
                <input name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                <div class="mt-1 text-xs text-slate-500">Leave blank to keep the current password.</div>
            </div>
            <div>
                <label class="text-sm text-slate-600">Confirm New Password</label>
                <input name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
