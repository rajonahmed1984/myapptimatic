@extends('layouts.admin')

@section('title', 'Licenses')
@section('page-title', 'Licenses')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Licenses</h1>
        <a href="{{ route('admin.licenses.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New License</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">License</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-mono text-xs text-teal-700">{{ $license->license_key }}</td>
                        <td class="px-4 py-3 text-slate-900">{{ $license->product->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $license->subscription->customer->name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($license->status) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.licenses.edit', $license) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No licenses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
