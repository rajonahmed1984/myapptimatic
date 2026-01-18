@extends('layouts.admin')

@section('title', 'Profile')
@section('page-title', 'Profile')

@section('content')
    <div class="card p-6">
        <div class="section-label">Employee profile</div>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">
            Hello, {{ $employee?->name ?? $user?->name ?? 'Team Member' }}
        </h1>
        <p class="mt-2 text-sm text-slate-500">Update your contact details, profile photo, and password here.</p>

        <form method="POST" action="{{ route('employee.profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex items-center gap-4">
                    <div class="h-16 w-16 overflow-hidden rounded-full border border-slate-200 bg-white">
                        <x-avatar :path="$employee?->photo_path ?? $user?->avatar_path" :name="$employee?->name ?? $user?->name" size="h-16 w-16" textSize="text-sm" />
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Profile photo</label>
                        <input name="avatar" type="file" accept="image/*" class="mt-2 text-sm text-slate-600" />
                        <p class="text-xs text-slate-500">PNG/JPG up to 2MB.</p>
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Full name</label>
                    <input name="name" value="{{ old('name', $user?->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" value="{{ old('email', $user?->email) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Phone</label>
                    <input name="phone" type="text" value="{{ old('phone', $employee?->phone) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm text-slate-600">Current password</label>
                    <input name="current_password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Required when choosing a new password.</p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">New password</label>
                    <input name="password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Confirm new password</label>
                    <input name="password_confirmation" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">Save profile</button>
            </div>
        </form>
    </div>
@endsection
