@extends('layouts.admin')

@section('title', 'Add Sales Representative')
@section('page-title', 'Add Sales Representative')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales</div>
            <div class="text-2xl font-semibold text-slate-900">Add sales representative</div>
            <div class="text-sm text-slate-500">Link to an existing user to grant portal access (optional).</div>
        </div>
        <a href="{{ route('admin.sales-reps.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6">
        <form action="{{ route('admin.sales-reps.store') }}" method="POST" enctype="multipart/form-data" autocomplete="off" class="grid gap-4 text-sm text-slate-700 lg:grid-cols-2">
            @csrf
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 space-y-3">
                <div>
                    <label class="text-xs text-slate-500">User (optional)</label>
                    <select name="user_id" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <option value="">Select user</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}"
                                    data-name="{{ $user->name }}"
                                    data-email="{{ $user->email }}"
                                    data-phone="{{ $user->phone ?? '' }}"
                                    @selected(old('user_id') == $user->id)>
                                {{ $user->name }} - {{ $user->email }}
                            </option>
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
                    <input name="name" value="{{ old('name') }}" autocomplete="off" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Defaults to user name">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="off" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Defaults to user email">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone') }}" autocomplete="off" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 space-y-3 lg:col-span-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs text-slate-500">Avatar</label>
                        <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <div class="mt-1 text-[11px] text-slate-500">JPG, PNG, or WebP up to 2MB.</div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">NID</label>
                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <div class="mt-1 text-[11px] text-slate-500">JPG, PNG, or PDF up to 10MB.</div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">CV</label>
                        <input name="cv_file" type="file" accept=".pdf" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        <div class="mt-1 text-[11px] text-slate-500">PDF up to 10MB.</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Create rep</button>
                <div class="text-xs text-slate-500">Active reps with a linked user can access /sales dashboard.</div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const userSelect = document.querySelector('select[name="user_id"]');
        const nameInput = document.querySelector('input[name="name"]');
        const emailInput = document.querySelector('input[name="email"]');
        const phoneInput = document.querySelector('input[name="phone"]');

        const fillFromSelectedUser = () => {
            const option = userSelect?.selectedOptions?.[0];
            if (!option) return;

            const selectedName = option.dataset.name || '';
            const selectedEmail = option.dataset.email || '';
            const selectedPhone = option.dataset.phone || '';

            if (nameInput) nameInput.value = selectedName;
            if (emailInput) emailInput.value = selectedEmail;
            if (phoneInput) phoneInput.value = selectedPhone;
        };

        userSelect?.addEventListener('change', fillFromSelectedUser);
        fillFromSelectedUser(); // prefill if old value selected
    });
</script>
@endpush
