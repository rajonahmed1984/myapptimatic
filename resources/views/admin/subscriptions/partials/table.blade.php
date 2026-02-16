<div id="subscriptionsTable">
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[1100px] text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Product & Plan</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Interval</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Next invoice</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $subscription)
                    @php
                        $plan = $subscription->plan;
                        $planPrice = $plan?->price;
                        $planCurrency = $plan?->currency;
                        $planInterval = $plan?->interval;
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->id }}</td>
                        <td class="px-4 py-3">
                            @php
                                $customer = $subscription->customer;
                            @endphp
                            @if($customer)
                                <a href="{{ route('admin.customers.show', $customer) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $customer->name }}
                                </a>
                            @else
                                <span class="text-slate-500">--</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $plan?->product?->name ?? '--' }} - {{ $plan?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            @if($planPrice !== null)
                                {{ $planCurrency ? $planCurrency.' ' : '' }}{{ number_format((float) $planPrice, 2) }}
                            @else
                                --
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $planInterval ? ucfirst($planInterval) : '--' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$subscription->status" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $subscription->next_invoice_at?->format($globalDateFormat) ?? '--' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <a
                                    href="{{ route('admin.subscriptions.edit', $subscription) }}"
                                    data-ajax-modal="true"
                                    data-modal-title="Manage Subscription"
                                    data-url="{{ route('admin.subscriptions.edit', $subscription) }}"
                                    class="text-teal-600 hover:text-teal-500"
                                >
                                    Manage
                                </a>
                                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}" data-ajax-form="true" onsubmit="return confirm('Delete this subscription?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-slate-500">No subscriptions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $subscriptions->links() }}
    </div>
</div>
