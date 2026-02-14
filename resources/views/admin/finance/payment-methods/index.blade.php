@extends('layouts.admin')

@section('title', 'Payment Methods')
@section('page-title', 'Payment Methods')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Payment methods</h1>
            <p class="mt-1 text-sm text-slate-500">Manage payout/payment accounts for all finance forms.</p>
        </div>
    </div>

    @php
        $formAction = $editMethod
            ? route('admin.finance.payment-methods.update', $editMethod)
            : route('admin.finance.payment-methods.store');
    @endphp

    <div class="grid gap-6 lg:grid-cols-10">
        <div class="card p-6 lg:col-span-3">
            <div class="text-sm font-semibold text-slate-900">{{ $editMethod ? 'Edit payment method' : 'Add payment method' }}</div>
            <form method="POST" action="{{ $formAction }}" class="mt-4 grid gap-4 text-sm">
                @csrf
                @if($editMethod)
                    @method('PUT')
                @endif

                <div>
                    <label class="text-xs text-slate-500">Name</label>
                    <input name="name" value="{{ old('name', $editMethod?->name) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" required>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Code</label>
                    <input name="code" value="{{ old('code', $editMethod?->code) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="bank-transfer">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Sort order</label>
                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $editMethod?->sort_order ?? 0) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2">
                </div>
                <div>
                    <label class="text-xs text-slate-500">Account details</label>
                    <textarea name="account_details" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2" placeholder="Account number, wallet number, branch, etc.">{{ old('account_details', $editMethod?->account_details) }}</textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-emerald-600" @checked(old('is_active', $editMethod?->is_active ?? true))>
                    Active
                </label>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                        {{ $editMethod ? 'Update method' : 'Add method' }}
                    </button>
                    @if($editMethod)
                        <a href="{{ route('admin.finance.payment-methods.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-800">Cancel edit</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card p-6 lg:col-span-7">
            <div class="text-sm font-semibold text-slate-900">Method list</div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table class="min-w-full text-sm text-slate-700">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Details</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Order</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($methods as $method)
                            <tr class="border-t border-slate-200">
                                <td class="px-3 py-2 font-medium text-slate-900">{{ $method->name }}</td>
                                <td class="px-3 py-2">{{ $method->code }}</td>
                                <td class="px-3 py-2 font-medium text-slate-800">{{ $amountByMethod[$method->id] ?? '0.00' }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $method->account_details ?: '--' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $method->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $method->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ $method->sort_order }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <a href="{{ route('admin.finance.payment-methods.show', $method) }}" class="text-slate-700 hover:text-slate-900">View</a>
                                        <a href="{{ route('admin.finance.payment-methods.index', ['edit' => $method->id]) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                        <form method="POST" action="{{ route('admin.finance.payment-methods.destroy', $method) }}" onsubmit="return confirm('Delete this payment method?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-slate-500">No payment methods found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
