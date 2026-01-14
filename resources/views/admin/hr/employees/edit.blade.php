@extends('layouts.admin')

@section('title', 'Edit Employee')
@section('page-title', 'Edit Employee')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Edit employee</div>
            <div class="text-sm text-slate-500">Update profile and employment details.</div>
        </div>
        <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to employees</a>
    </div>

    <div class="card p-6 max-w-4xl">
        <form method="POST" action="{{ route('admin.hr.employees.update', $employee) }}" enctype="multipart/form-data" class="space-y-4">
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
                    <label class="text-xs text-slate-500">Login password (optional)</label>
                    <input name="user_password" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-400">Set to create or reset the employee login.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Confirm password</label>
                    <input name="user_password_confirmation" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Designation</label>
                    <input name="designation" value="{{ old('designation', $employee->designation) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
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
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name', $employee->name) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email', $employee->email) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Address</label>
                    <input name="address" value="{{ old('address', $employee->address) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Department (optional)</label>
                    <input name="department" value="{{ old('department', $employee->department) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Join date</label>
                    <input name="join_date" type="date" value="{{ old('join_date', optional($employee->join_date)->format('Y-m-d')) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="section-label">Employment</div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Employment type</label>
                    <select name="employment_type" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="full_time" @selected(old('employment_type', $employee->employment_type) === 'full_time')>Full-time</option>
                        <option value="part_time" @selected(old('employment_type', $employee->employment_type) === 'part_time')>Part-time</option>
                        <option value="contract" @selected(old('employment_type', $employee->employment_type) === 'contract')>Contract</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Work mode</label>
                    <select name="work_mode" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="remote" @selected(old('work_mode', $employee->work_mode) === 'remote')>Remote</option>
                        <option value="on_site" @selected(old('work_mode', $employee->work_mode) === 'on_site')>On-site</option>
                        <option value="hybrid" @selected(old('work_mode', $employee->work_mode) === 'hybrid')>Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', $employee->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $employee->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="section-label">Additional</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone', $employee->phone) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">NID upload (jpg/png/pdf)</label>
                    <input type="file" name="nid_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-slate-700">
                    @if($employee->nid_path)
                        <div class="text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'nid']) }}" class="text-teal-600 hover:text-teal-500">View current NID</a>
                        </div>
                    @endif
                </div>
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">Photo (jpg/png)</label>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png" class="w-full text-sm text-slate-700">
                    @if($employee->photo_path)
                        <div class="mt-2">
                            <x-avatar :path="$employee->photo_path" :name="$employee->name" size="h-16 w-16" textSize="text-sm" />
                        </div>
                    @endif
                </div>
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">CV upload (pdf)</label>
                    <input type="file" name="cv_file" accept=".pdf" class="w-full text-sm text-slate-700">
                    @if($employee->cv_path)
                        <div class="text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'employee', 'id' => $employee->id, 'doc' => 'cv']) }}" class="text-teal-600 hover:text-teal-500">View current CV</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Update employee</button>
            </div>
        </form>
    </div>
@endsection
