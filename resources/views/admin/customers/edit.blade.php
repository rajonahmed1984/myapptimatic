@extends('layouts.admin')

@section('title', 'Edit Customer')
@section('page-title', 'Edit Customer')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Customer</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $customer->name }}</h1>
            <div class="mt-1 text-sm text-slate-500">Client ID: {{ $customer->id }}</div>
        </div>
        <div class="text-sm text-slate-600">
            <div>Status: {{ ucfirst($customer->status) }}</div>
            <div>Created: {{ $customer->created_at?->format($globalDateFormat) ?? '--' }}</div>
        </div>
    </div>

    <div class="card p-6">
        @include('admin.customers.partials.tabs', ['customer' => $customer, 'activeTab' => 'profile'])

        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name', $customer->name) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Company Name</label>
                    <input name="company_name" value="{{ old('company_name', $customer->company_name) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Email</label>
                    <input name="email" type="email" value="{{ old('email', $customer->email) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Phone</label>
                    <input name="phone" value="{{ old('phone', $customer->phone) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Address</label>
                    <textarea name="address" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('address', $customer->address) }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active" {{ old('status', $customer->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $customer->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Access Override Until</label>
                    <input name="access_override_until" type="date" value="{{ old('access_override_until', $customer->access_override_until?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <p class="mt-1 text-xs text-slate-500">Grant temporary access even if status is inactive</p>
                </div>
            </div>

            <div>
                <label class="text-sm text-slate-600">Notes</label>
                <textarea name="notes" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes', $customer->notes) }}</textarea>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm text-slate-600">Avatar</label>
                    <input name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    <div class="mt-2">
                        <x-avatar :path="$customer->avatar_path" :name="$customer->name" size="h-16 w-16" textSize="text-sm" />
                    </div>
                </div>
                <div>
                    <label class="text-sm text-slate-600">NID</label>
                    <input name="nid_file" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @if($customer->nid_path)
                        <div class="mt-1 text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'customer', 'id' => $customer->id, 'doc' => 'nid']) }}" class="text-teal-600 hover:text-teal-500">View current NID</a>
                        </div>
                    @endif
                </div>
                <div>
                    <label class="text-sm text-slate-600">CV</label>
                    <input name="cv_file" type="file" accept=".pdf" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    @if($customer->cv_path)
                        <div class="mt-1 text-xs text-slate-500">
                            <a href="{{ route('admin.user-documents.show', ['type' => 'customer', 'id' => $customer->id, 'doc' => 'cv']) }}" class="text-teal-600 hover:text-teal-500">View current CV</a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Update customer</button>
                <a href="{{ route('admin.customers.index') }}" class="text-sm text-slate-600 hover:text-teal-600">Cancel</a>
            </div>
        </form>
    </div>
@endsection
