<div id="accountingTableWrap">
    <div class="card overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3 whitespace-nowrap">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Gateway</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php($isOutflow = $entry->isOutflow())
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $entry->entry_date->format($globalDateFormat) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($entry->type) }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($entry->customer)
                                <a href="{{ route('admin.customers.show', $entry->customer) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $entry->customer->name }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @if($entry->invoice)
                                <a href="{{ route('admin.invoices.show', $entry->invoice) }}" class="text-teal-600 hover:text-teal-500">
                                    {{ $entry->invoice->number ?? $entry->invoice->id }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->paymentGateway?->name ?? '-' }}</td>
                        <td class="px-4 py-3 font-semibold {{ $isOutflow ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $isOutflow ? '-' : '+' }}{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->reference ?: '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a
                                    href="{{ route('admin.accounting.edit', ['entry' => $entry, 'scope' => $scope ?? 'ledger', 'search' => $search ?? '']) }}"
                                    data-ajax-modal="true"
                                    data-url="{{ route('admin.accounting.edit', ['entry' => $entry, 'scope' => $scope ?? 'ledger', 'search' => $search ?? '']) }}"
                                    data-modal-title="Edit Accounting Entry"
                                    class="text-teal-600 hover:text-teal-500"
                                >Edit</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.accounting.destroy', $entry) }}"
                                    data-ajax-form="true"
                                    data-delete-confirm
                                    data-confirm-name="{{ $entry->reference ?: $entry->id }}"
                                    data-confirm-title="Delete entry {{ $entry->reference ?: $entry->id }}?"
                                    data-confirm-description="This will permanently delete the accounting entry."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="scope" value="{{ $scope ?? 'ledger' }}">
                                    <input type="hidden" name="search" value="{{ $search ?? '' }}">
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
