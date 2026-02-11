@extends('layouts.admin')

@section('title', 'New Admin')
@section('page-title', 'New Admin')

@section('content')
    <div class="mb-6 flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-slate-900">Create Admin User</h1>
        <a href="{{ route('admin.admins.index') }}" class="text-sm text-slate-500 hover:text-teal-600" hx-boost="false">Back to admin users</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.admins.store') }}" class="grid gap-6 md:grid-cols-2">
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
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Create Admin</button>
            </div>
        </form>
    </div>
@endsection
