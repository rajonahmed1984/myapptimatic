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
                    @php($syncStatus = $lastCheck ? ($hoursSinceCheck <= 24 ? 'synced' : 'stale') : 'never')
                    @php($customerId = $license->subscription?->customer?->id)
                    @php($isBlocked = $customerId ? ($accessBlockedCustomers[$customerId] ?? false) : false)
                    @php($licenseStatus = $license->status === 'active' && $isBlocked ? 'blocked' : $license->status)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-teal-700">
                            <div class="flex items-center gap-2">
                                <span class="license-key-text">{{ $license->license_key }}</span>
                                <button type="button" class="copy-license-btn text-slate-400 hover:text-teal-600 transition-colors" data-license-key="{{ $license->license_key }}" title="Copy license key">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            <br>
                            {{ $primaryDomain ?? '--' }}
                        </td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$syncStatus" />
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
                        <td class="px-4 py-3">
                            <x-status-badge :status="$licenseStatus" />
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

    <script>
        document.querySelectorAll('.copy-license-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const licenseKey = this.dataset.licenseKey;
                const btn = this;
                const originalSvg = btn.innerHTML;

                try {
                    await navigator.clipboard.writeText(licenseKey);
                    
                    // Show success feedback
                    btn.classList.remove('text-slate-400', 'hover:text-teal-600');
                    btn.classList.add('text-emerald-600');
                    btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        btn.classList.remove('text-emerald-600');
                        btn.classList.add('text-slate-400', 'hover:text-teal-600');
                        btn.innerHTML = originalSvg;
                    }, 2000);
                } catch (err) {
                    console.error('Failed to copy:', err);
                    btn.classList.add('text-rose-600');
                    setTimeout(() => {
                        btn.classList.remove('text-rose-600');
                    }, 2000);
                }
            });
        });
    </script>
@endsection

