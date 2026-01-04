@extends('layouts.admin')

@section('title', 'Add Sales Representative')
@section('page-title', 'Add Sales Representative')

@section('content')
    <div class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">Sales</div>
                <div class="text-2xl font-semibold text-slate-900">Add sales representative</div>
                <div class="text-sm text-slate-500">Link to an existing user to grant sales access.</div>
            </div>
            <a href="{{ route('admin.sales-reps.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>

        <form action="{{ route('admin.sales-reps.store') }}" method="POST" class="mt-6 grid gap-4 lg:grid-cols-2 text-sm text-slate-700">
            @csrf
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 space-y-3">
                <div>
                    <label class="text-xs text-slate-500">User (required)</label>
                    <select name="user_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select user</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }} — {{ $user->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Employee link (optional)</label>
                    <select name="employee_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">None</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 space-y-3">
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Defaults to user name">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Defaults to user email">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="lg:col-span-2 flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create rep</button>
                <div class="text-xs text-slate-500">Active reps can access /rep dashboard.</div>
            </div>
        </form>
    </div>
@endsection
