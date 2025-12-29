@extends('layouts.admin')

@section('title', 'New Customer')
@section('page-title', 'New Customer')

@section('content')
    <div class="card p-6">
        <h1 class="text-2xl font-semibold text-slate-900">Create Customer</h1>

        <form method="POST" action="{{ route('admin.customers.store') }}" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="text-sm text-slate-600">Name</label>
                    <input name="name" value="{{ old('name') }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm text-slate-600">Company Name</label>
                    <input name="company_name" value="{{ old('company_name') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
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
                    <label class="text-sm text-slate-600">Status</label>
                    <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Address</label>
                    <textarea name="address" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('address') }}</textarea>
                </div>
                <div>
                    <label class="text-sm text-slate-600">Override access until</label>
                    <input name="access_override_until" type="date" value="{{ old('access_override_until') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                </div>
                <div class="md:col-span-2">
                    <label class="text-sm text-slate-600">Notes</label>
                    <textarea name="notes" rows="2" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="card-muted p-4">
                <div class="text-sm font-semibold text-slate-800">Client login (optional)</div>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-sm text-slate-600">User name</label>
                        <input name="user_name" value="{{ old('user_name') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">User email</label>
                        <input name="user_email" type="email" value="{{ old('user_email') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-sm text-slate-600">Password</label>
                        <input name="user_password" type="password" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                    </div>
                </div>
            </div>

            <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save customer</button>
        </form>
    </div>
@endsection
