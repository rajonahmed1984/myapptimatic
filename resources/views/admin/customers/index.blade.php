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
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $customer->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $customer->email }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($customer->status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.customers.edit', $customer) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-slate-500">No customers yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
