@extends('layouts.admin')

@section('title', 'Add Employee')
@section('page-title', 'Add Employee')

@section('content')
    <div class="card p-6 max-w-4xl">
        <form method="POST" action="{{ route('admin.hr.employees.store') }}" class="space-y-4">
            @csrf
            <div class="section-label">Profile</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Linked user (optional)</label>
                    <select name="user_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Manager (optional)</label>
                    <select name="manager_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">-- none --</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Designation</label>
                    <input name="designation" value="{{ old('designation') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Department</label>
                    <input name="department" value="{{ old('department') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Join date</label>
                    <input type="date" name="join_date" value="{{ old('join_date') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Employment type</label>
                    <select name="employment_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="full_time">Full-time</option>
                        <option value="part_time">Part-time</option>
                        <option value="contract">Contract</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Work mode</label>
                    <select name="work_mode" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="remote">Remote</option>
                        <option value="on_site">On-site</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="section-label">Compensation (current)</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Salary type</label>
                    <select name="salary_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="hourly">Hourly</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Currency</label>
                    <input name="currency" value="{{ old('currency', 'BDT') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Basic pay</label>
                    <input type="number" step="0.01" name="basic_pay" value="{{ old('basic_pay') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Hourly rate (optional)</label>
                    <input type="number" step="0.01" name="hourly_rate" value="{{ old('hourly_rate') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
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
