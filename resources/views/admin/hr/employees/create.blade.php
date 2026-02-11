@extends('layouts.admin')

@section('title', 'Add Employee')
@section('page-title', 'Add Employee')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Add employee</div>
            <div class="text-sm text-slate-500">Create a new employee record.</div>
        </div>
        <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to employees</a>
    </div>

    <div class="card p-6">
        <form method="POST" action="{{ route('admin.hr.employees.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="section-label">Profile</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Address</label>
                    <input name="address" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Department (optional)</label>
                    <input name="department" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Designation</label>
                    <input name="designation" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
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
                    <label class="text-xs text-slate-500">Login password (optional)</label>
                    <input name="user_password" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-xs text-slate-500">Confirm password</label>
                    <input name="user_password_confirmation" type="password" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-400">Set a password to allow employee login.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Join date</label>
                    <input name="join_date" type="date" value="{{ old('join_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="section-label">Employment</div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Employment type</label>
                    <select name="employment_type" required data-employment-type class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="full_time" @selected(old('employment_type') === 'full_time')>Full-time</option>
                        <option value="part_time" @selected(old('employment_type') === 'part_time')>Part-time</option>
                        <option value="contract" @selected(old('employment_type') === 'contract')>Contract</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Work mode</label>
                    <select name="work_mode" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="remote" @selected(old('work_mode') === 'remote')>Remote</option>
                        <option value="on_site" @selected(old('work_mode') === 'on_site')>On-site</option>
                        <option value="hybrid" @selected(old('work_mode') === 'hybrid')>Hybrid</option>
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

            <div class="section-label">Compensation</div>
            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-xs text-slate-500">Salary type</label>
                    <select name="salary_type" required data-salary-type class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="monthly" @selected(old('salary_type', 'monthly') === 'monthly')>Monthly</option>
                        <option value="hourly" @selected(old('salary_type') === 'hourly')>Hourly</option>
                        <option value="project_base" @selected(old('salary_type') === 'project_base')>Project base</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Currency</label>
                    <select name="currency" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @php($currencyOptions = ['BDT', 'USD'])
                        @foreach($currencyOptions as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', 'BDT') === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Basic pay</label>
                    <input name="basic_pay" type="number" step="0.01" min="0" value="{{ old('basic_pay', 0) }}" data-basic-pay class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400" data-basic-pay-note>Required unless contract + project base.</p>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Hourly rate (optional)</label>
                    <input name="hourly_rate" type="number" step="0.01" min="0" value="{{ old('hourly_rate') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="section-label">Additional</div>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">NID upload (jpg/png/pdf)</label>
                    <input type="file" name="nid_file" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-slate-700">
                </div>
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">Photo (jpg/png)</label>
                    <input type="file" name="photo" accept=".jpg,.jpeg,.png" class="w-full text-sm text-slate-700">
                    <div class="mt-2">
                        <x-avatar id="employee-photo-preview" :path="null" :name="old('name')" size="h-16 w-16" textSize="text-sm" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-xs text-slate-500">CV upload (pdf)</label>
                    <input type="file" name="cv_file" accept=".pdf" class="w-full text-sm text-slate-700">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('admin.hr.employees.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Cancel</a>
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create employee</button>
            </div>
        </form>
    </div>

    <script>
        const employeePhotoInput = document.querySelector('input[name="photo"]');
        const employeePhotoPreview = document.getElementById('employee-photo-preview');

        if (employeePhotoInput && employeePhotoPreview) {
            employeePhotoInput.addEventListener('change', () => {
                const file = employeePhotoInput.files && employeePhotoInput.files[0];
                if (!file) {
                    return;
                }

                const previewUrl = URL.createObjectURL(file);
                employeePhotoPreview.innerHTML = `<img src="${previewUrl}" alt="Photo preview" class="h-full w-full object-cover">`;
            });
        }

        const employmentTypeInput = document.querySelector('[data-employment-type]');
        const salaryTypeInput = document.querySelector('[data-salary-type]');
        const basicPayInput = document.querySelector('[data-basic-pay]');
        const basicPayNote = document.querySelector('[data-basic-pay-note]');

        const syncBasicPayRequirement = () => {
            if (!employmentTypeInput || !salaryTypeInput || !basicPayInput) {
                return;
            }

            const isOptional = employmentTypeInput.value === 'contract' && salaryTypeInput.value === 'project_base';
            basicPayInput.required = !isOptional;

            if (basicPayNote) {
                basicPayNote.textContent = isOptional
                    ? 'Optional for contract employees on project-base salary.'
                    : 'Required unless contract + project base.';
            }
        };

        if (employmentTypeInput && salaryTypeInput && basicPayInput) {
            syncBasicPayRequirement();
            employmentTypeInput.addEventListener('change', syncBasicPayRequirement);
            salaryTypeInput.addEventListener('change', syncBasicPayRequirement);
        }
    </script>
@endsection
