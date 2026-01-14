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
        <form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data" class="grid gap-6 md:grid-cols-2">
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
            <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                <div class="mt-3 grid gap-4 md:grid-cols-3 text-sm">
                    <div>
                        <label class="text-sm text-slate-600">Avatar</label>
                        <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        <div class="mt-2">
                            <x-avatar :path="$user->avatar_path" :name="$user->name" size="h-16 w-16" textSize="text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">NID</label>
                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        @if($user->nid_path)
                            <div class="mt-1 text-xs text-slate-500">
                                <a href="{{ route('admin.user-documents.show', ['type' => 'user', 'id' => $user->id, 'doc' => 'nid']) }}" class="text-teal-600 hover:text-teal-500">View current NID</a>
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">CV</label>
                        <input name="cv_file" type="file" accept=".pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        @if($user->cv_path)
                            <div class="mt-1 text-xs text-slate-500">
                                <a href="{{ route('admin.user-documents.show', ['type' => 'user', 'id' => $user->id, 'doc' => 'cv']) }}" class="text-teal-600 hover:text-teal-500">View current CV</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
