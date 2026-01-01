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
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">License & URL</th>
                    <th class="px-4 py-3">Sync status</th>
                    <th class="px-4 py-3">Product & Plan</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Order number</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                    @php($primaryDomain = $license->domains->first()?->domain)
                    @php($lastCheck = $license->last_check_at)
                    @php($hoursSinceCheck = $lastCheck ? $lastCheck->diffInHours(now()) : null)
                    @php($syncLabel = $lastCheck ? ($hoursSinceCheck <= 24 ? 'Synced' : 'Stale') : 'Never')
                    @php($syncClass = $lastCheck ? ($hoursSinceCheck <= 24 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700') : 'bg-slate-100 text-slate-600')
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-teal-700">
                            {{ $license->license_key }}
                            <br>
                            {{ $primaryDomain ?? '--' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $syncClass }}">{{ $syncLabel }}</div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $lastCheck ? $lastCheck->format($globalDateFormat . ' H:i') : 'No sync yet' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-900">
                            {{ $license->product->name }}
                            <br>
                            {{ $license->subscription?->plan?->name ?? '--' }}
                        </td>                        
                        <td class="px-4 py-3 text-slate-500">{{ $license->subscription?->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $license->subscription?->latestOrder?->order_number ?? '--' }}</td>
                        @php
                            $customerId = $license->subscription?->customer?->id;
                            $isBlocked = $customerId ? ($accessBlockedCustomers[$customerId] ?? false) : false;
                        @endphp
                        <td class="px-4 py-3 text-slate-700">
                            @if($license->status === 'suspended')
                                Suspended
                            @elseif($license->status === 'active' && $isBlocked)
                                Access blocked
                            @else
                                {{ ucfirst($license->status) }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <form method="POST" action="{{ route('admin.licenses.sync', $license) }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="text-blue-600 hover:text-blue-500 text-sm font-medium">Sync</button>
                                </form>
                                <a href="{{ route('admin.licenses.edit', $license) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form method="POST" action="{{ route('admin.licenses.destroy', $license) }}" onsubmit="return confirm('Delete this license?');" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-slate-500">No licenses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

