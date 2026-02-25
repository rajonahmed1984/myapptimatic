<div id="licensesTable">
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[1150px] text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Customer &amp; Order</th>
                    <th class="px-4 py-3">License &amp; URL</th>
                    <th class="px-4 py-3">True verification</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($licenses as $license)
                    @php
                        $activeDomain = $license->domains->firstWhere('status', 'active');
                        $domain = $activeDomain?->domain ?? $license->domains->first()?->domain;
                        $subscription = $license->subscription;
                        $customer = $subscription?->customer;
                        $isBlocked = $customer && ($accessBlockedCustomers[$customer->id] ?? false);

                        $verificationLabel = 'Verified';
                        $verificationClass = 'bg-emerald-100 text-emerald-700';
                        $verificationHint = 'Active and domain matched';

                        if (! $customer || $customer->status !== 'active') {
                            $verificationLabel = 'Blocked';
                            $verificationClass = 'bg-rose-100 text-rose-700';
                            $verificationHint = 'customer_inactive';
                        } elseif ($license->status !== 'active') {
                            $verificationLabel = 'Blocked';
                            $verificationClass = 'bg-rose-100 text-rose-700';
                            $verificationHint = 'license_inactive';
                        } elseif ($license->expires_at && $license->expires_at->isPast()) {
                            $verificationLabel = 'Blocked';
                            $verificationClass = 'bg-rose-100 text-rose-700';
                            $verificationHint = 'license_expired';
                        } elseif (! $subscription || $subscription->status !== 'active') {
                            $verificationLabel = 'Blocked';
                            $verificationClass = 'bg-rose-100 text-rose-700';
                            $verificationHint = 'subscription_inactive';
                        } elseif (! $activeDomain) {
                            $verificationLabel = 'Pending';
                            $verificationClass = 'bg-amber-100 text-amber-700';
                            $verificationHint = 'domain_not_bound';
                        } elseif ($isBlocked) {
                            $verificationLabel = 'Blocked';
                            $verificationClass = 'bg-rose-100 text-rose-700';
                            $verificationHint = 'invoice_overdue';
                        }

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

                        $latestOrder = $subscription?->latestOrder;
                        $orderNumber = $latestOrder?->order_number ?? $latestOrder?->id;
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $license->id }}</td>
                        <td class="px-4 py-3">
                            @if($customer)
                                <a href="{{ route('admin.customers.show', $customer) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $customer->name }}
                                </a>
                                @if($isBlocked)
                                    <div class="mt-1 text-xs text-rose-600">Access blocked</div>
                                @endif
                            @else
                                <span class="text-slate-500">--</span>
                            @endif
                            <div class="mt-1 text-xs text-slate-500">
                                Order: {{ $orderNumber ?? '--' }} > {{ $license->product?->name ?? '--' }} - {{ $subscription?->plan?->name ?? '--' }}
                            </div>
                        </td>
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
                            <div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $verificationClass }}">
                                {{ $verificationLabel }} 
                                <span class="ml-2">
                                    <x-status-badge :status="$license->status" />
                                </span>
                                <div class="inline-flex items-center rounded-full px-3 py-1 ml-2 text-xs font-semibold {{ $syncClass }}" data-sync-badge="{{ $license->id }}">
                                    {{ $syncLabel }}
                                </div>
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $verificationHint }}
                                <span class="mt-1 text-xs text-slate-500" data-sync-time="{{ $license->id }}">
                                    {{ $syncAt ? $syncAt->format(config('app.datetime_format', 'd-m-Y h:i A')) : 'No sync yet' }}
                                </span>
                            </div>
                            
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="inline-flex items-center gap-3">
                                @can('update', $license)
                                    <form method="POST" action="{{ route('admin.licenses.sync', $license) }}" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            data-license-sync
                                            data-license-id="{{ $license->id }}"
                                            data-sync-status-url="{{ route('admin.licenses.sync-status', $license) }}"
                                            class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                        >
                                            Sync
                                        </button>
                                    </form>
                                @endcan
                                <a href="{{ route('admin.licenses.edit', $license) }}" class="text-teal-600 hover:text-teal-500">Manage</a>
                            </div>
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
</div>
