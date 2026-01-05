@extends('layouts.admin')

@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Customers</h1>
        <a href="{{ route('admin.customers.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Customer</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Client ID</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Company Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Services</th>
                    <th class="px-4 py-3">Created</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500"><a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-teal-600">{{ $customer->id }}</a></td>
                        <td class="px-4 py-3 font-medium text-slate-900">
                            <a href="{{ route('admin.customers.show', $customer) }}" class="hover:text-teal-600">{{ $customer->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $customer->company_name ?: '--' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $customer->email }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $customer->active_subscriptions_count }} ({{ $customer->subscriptions_count }})
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $customer->created_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$customer->status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer? This will remove related subscriptions and invoices.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $customers->links() }}
    </div>
@endsection
