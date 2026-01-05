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
        <table class="w-full min-w-[1100px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">License &amp; URL</th>
                    <th class="px-4 py-3">Sync status</th>
                    <th class="px-4 py-3">Product &amp; Plan</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Order number</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                    @php
                        $domain = $license->domains->first()?->domain;
                        $syncAt = $license->last_check_at;
                        $syncLabel = 'Never';
                        $syncClass = 'bg-slate-100 text-slate-600';

                        if ($syncAt) {
                            $hours = $syncAt->diffInHours(now());
                            if ($hours <= 24) {
                                $syncLabel = 'Synced';
                                $syncClass = 'bg-emerald-100 text-emerald-700';
                            } elseif ($hours > 48) {
                                $syncLabel = 'Stale';
                                $syncClass = 'bg-amber-100 text-amber-700';
                            } else {
                                $syncLabel = 'Synced';
                                $syncClass = 'bg-emerald-100 text-emerald-700';
                            }
                        }

                        $customer = $license->subscription?->customer;
                        $isBlocked = $customer && ($accessBlockedCustomers[$customer->id] ?? false);
                        $orderNumber = $license->subscription?->latestOrder?->order_number;
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $licenses->firstItem() ? $licenses->firstItem() + $loop->index : $license->id }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-teal-700">
                            <div class="flex items-center gap-2">
                                <span class="license-key-text">{{ $license->license_key }}</span>
                                <button type="button" class="copy-license-btn text-slate-400 hover:text-teal-600 transition-colors" data-license-key="{{ $license->license_key }}" title="Copy license key">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="mt-2 text-slate-900">{{ $domain ?? '--' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $syncClass }}">
                                {{ $syncLabel }}
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $syncAt ? $syncAt->format($globalDateFormat.' H:i') : 'No sync yet' }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-900">
                            {{ $license->product?->name ?? '--' }}
                            <br>
                            {{ $license->subscription?->plan?->name ?? '--' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $orderNumber ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$license->status" />
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
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No licenses yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $licenses->links() }}
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const buttons = document.querySelectorAll('.copy-license-btn');
                buttons.forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const key = btn.getAttribute('data-license-key');
                        if (!key) return;

                        try {
                            await navigator.clipboard.writeText(key);
                            btn.classList.add('text-emerald-600');
                            setTimeout(() => btn.classList.remove('text-emerald-600'), 1200);
                        } catch (e) {
                            alert('Unable to copy license key.');
                        }
                    });
                });
            });
        </script>
    @endpush
@endsection
