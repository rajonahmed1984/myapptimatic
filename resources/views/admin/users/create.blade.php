@extends('layouts.admin')

@section('title', 'New User')
@section('page-title', 'New User')

@php
    $roleLabels = $roles ?? [];
@endphp

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Create User</h1>
        <a href="{{ route('admin.users.index', $selectedRole) }}" class="text-sm text-slate-500 hover:text-teal-600" hx-boost="false">Back to users</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.users.store', $selectedRole) }}" enctype="multipart/form-data" class="grid gap-6 md:grid-cols-2">
            @csrf
            <div>
                <label class="text-sm text-slate-600">Name</label>
                <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Password</label>
                <input name="password" type="password" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Confirm Password</label>
                <input name="password_confirmation" type="password" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Role</label>
                <div class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700">
                    {{ $roleLabels[$selectedRole] ?? ucfirst($selectedRole) }}
                </div>
            </div>
            <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                <div class="mt-3 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm text-slate-600">Avatar</label>
                        <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">NID</label>
                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">CV</label>
                        <input name="cv_file" type="file" accept=".pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                </div>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create User</button>
            </div>
        </form>
    </div>
@endsection
