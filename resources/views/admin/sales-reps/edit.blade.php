@extends('layouts.admin')

@section('title', 'Edit Sales Representative')
@section('page-title', 'Edit Sales Representative')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Sales</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $rep->name }}</div>
            <div class="text-sm text-slate-500">Update contact details and status.</div>
        </div>
        <a href="{{ route('admin.sales-reps.index', [], false) }}" hx-boost="false" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <div class="card p-6">
        <form action="{{ route('admin.sales-reps.update', $rep) }}" method="POST" enctype="multipart/form-data" class="grid gap-4 text-sm text-slate-700 lg:grid-cols-2">
            @csrf
            @method('PUT')
            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3">
                <div>
                    <label class="text-xs text-slate-500">User</label>
                    <div class="mt-1 text-slate-900 font-semibold">{{ $rep->user?->name ?? '--' }}</div>
                    <div class="text-xs text-slate-500">{{ $rep->user?->email ?? '' }}</div>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Employee link (optional)</label>
                    <select name="employee_id" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="">None</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id', $rep->employee_id) == $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Status</label>
                    <select name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="active" @selected(old('status', $rep->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $rep->status) === 'inactive')>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3">
                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name', $rep->name) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Email</label>
                    <input name="email" type="email" value="{{ old('email', $rep->email) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Phone</label>
                    <input name="phone" value="{{ old('phone', $rep->phone) }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
            </div>

            <div class="rounded-2xl border border-slate-300 bg-white/80 p-4 space-y-3 lg:col-span-2">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Documents</div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs text-slate-500">Avatar</label>
                        <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        <div class="mt-2">
                            <x-avatar :path="$rep->avatar_path" :name="$rep->name" size="h-16 w-16" textSize="text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">NID</label>
                        <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        @if($rep->nid_path)
                            @php
                                $nidIsImage = \Illuminate\Support\Str::endsWith(strtolower($rep->nid_path), ['.jpg', '.jpeg', '.png', '.webp']);
                                $nidUrl = route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $rep->id, 'doc' => 'nid'], false);
                            @endphp
                            <div class="mt-2 flex items-center gap-3">
                                @if($nidIsImage)
                                    <img src="{{ $nidUrl }}" alt="NID" class="h-16 w-20 rounded-lg object-cover border border-slate-200">
                                @else
                                    <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                                @endif
                                <a href="{{ $nidUrl }}" class="text-xs text-teal-600 hover:text-teal-500">View current NID</a>
                            </div>
                        @endif
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">CV</label>
                        <input name="cv_file" type="file" accept=".pdf" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                        @if($rep->cv_path)
                            @php
                                $cvUrl = route('admin.user-documents.show', ['type' => 'sales-rep', 'id' => $rep->id, 'doc' => 'cv'], false);
                            @endphp
                            <div class="mt-2 flex items-center gap-3">
                                <div class="flex h-16 w-20 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-500">PDF</div>
                                <a href="{{ $cvUrl }}" class="text-xs text-teal-600 hover:text-teal-500">View current CV</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 flex items-center gap-3">
                <button type="submit" class="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Update rep</button>
                <div class="text-xs text-slate-500">Changes sync to rep dashboard immediately.</div>
            </div>
        </form>
    </div>
@endsection
