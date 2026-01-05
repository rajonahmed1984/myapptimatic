@extends('layouts.admin')

@section('title', 'Licenses')
@section('page-title', 'Licenses')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Licenses</h1>
        </div>
        <a href="{{ route('admin.licenses.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New License</a>
    </div>

    <div class="card overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">License</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Expires</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $licenses->firstItem() ? $licenses->firstItem() + $loop->index : $license->id }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $license->license_key }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $license->subscription?->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $license->product?->name ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$license->status" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $license->expires_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.licenses.edit', $license) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No licenses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $licenses->links() }}
    </div>
@endsection
