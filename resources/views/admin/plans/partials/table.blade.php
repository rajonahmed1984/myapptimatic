<div id="plansTableWrap" class="card overflow-x-auto">
    <table class="w-full min-w-[900px] text-left text-sm">
        <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
            <tr>
                <th class="px-4 py-3">SL</th>
                <th class="px-4 py-3">Plan</th>
                <th class="px-4 py-3">Slug</th>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Price</th>
                <th class="px-4 py-3">Interval</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($plans as $plan)
                <tr id="plan-row-{{ $plan->id }}" class="border-b border-slate-100">
                    <td class="px-4 py-3 text-slate-500">{{ $loop->iteration }}</td>
                    <td class="px-4 py-3 font-medium text-slate-900">{{ $plan->name }}</td>
                    <td class="px-4 py-3 text-slate-500">
                        @if($plan->slug && $plan->product?->slug)
                            {{ $plan->product->slug }}/plans/{{ $plan->slug }}
                        @else
                            --
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-500">{{ $plan->product->name }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ $defaultCurrency }} {{ $plan->price }}</td>
                    <td class="px-4 py-3 text-slate-700">{{ ucfirst($plan->interval) }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$plan->is_active ? 'active' : 'inactive'" />
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a
                                href="{{ route('admin.plans.edit', $plan) }}"
                                data-ajax-modal="true"
                                data-modal-title="Edit Plan"
                                data-url="{{ route('admin.plans.edit', $plan) }}"
                                class="text-teal-600 hover:text-teal-500"
                            >
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" data-ajax-form="true" onsubmit="return confirm('Delete this plan?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-6 text-center text-slate-500">No plans yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
