@extends('layouts.admin')

@section('title', 'Add Sales Representative')
@section('page-title', 'Add Sales Representative')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales</div>
            <div class="text-2xl font-semibold text-slate-900">Add sales representative</div>
            <div class="text-sm text-slate-500">Create profile details and set login credentials.</div>
        </div>
        <a href="{{ route('admin.sales-reps.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6">
        <form action="{{ route('admin.sales-reps.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Password</label>
                    <input name="user_password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Set a password to create sales portal login.</p>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Employee link (optional)</label>
                    <select name="employee_id" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="">None</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm text-slate-600">Avatar</label>
                        <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG, or WebP up to 2MB.</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">NID</label>
                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">JPG, PNG, or PDF up to 10MB.</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">CV</label>
                        <input name="cv_file" type="file" accept=".pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">PDF up to 10MB.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create rep</button>
            </div>
        </form>
    </div>
@endsection
