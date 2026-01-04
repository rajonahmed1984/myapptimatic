@extends('layouts.admin')

@section('title', 'Edit Employee')
@section('page-title', 'Edit Employee')

@section('content')
    <div class="card p-6 max-w-4xl">
        <form method="POST" action="{{ route('admin.hr.employees.update', $employee) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="section-label">Profile</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Linked user (optional)</label>
                    <select name="user_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected($employee->user_id === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Manager (optional)</label>
                    <select name="manager_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected($employee->manager_id === $manager->id)>{{ $manager->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name', $employee->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email', $employee->email) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Designation</label>
                    <input name="designation" value="{{ old('designation', $employee->designation) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Department</label>
                    <input name="department" value="{{ old('department', $employee->department) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Join date</label>
                    <input type="date" name="join_date" value="{{ old('join_date', optional($employee->join_date)->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Employment type</label>
                    <select name="employment_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach(['full_time','part_time','contract'] as $type)
                            <option value="{{ $type }}" @selected($employee->employment_type === $type)>{{ ucfirst(str_replace('_',' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Work mode</label>
                    <select name="work_mode" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach(['remote','on_site','hybrid'] as $mode)
                            <option value="{{ $mode }}" @selected($employee->work_mode === $mode)>{{ ucfirst(str_replace('_',' ', $mode)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach(['active','inactive'] as $status)
                            <option value="{{ $status }}" @selected($employee->status === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save</button>
                <a href="{{ route('admin.hr.employees.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            </div>
        </form>
    </div>
@endsection
