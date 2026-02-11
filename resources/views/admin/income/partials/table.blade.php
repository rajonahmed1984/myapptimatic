<div id="incomeTable">
    <div class="card p-6">
        <div class="section-label">Income list</div>
        <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="px-3 py-2 whitespace-nowrap">Date</th>
                        <th class="px-3 py-2">Title & Ref</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Project</th>
                        <th class="px-3 py-2">Amount</th>
                        <th class="px-3 py-2">Attachment</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incomes as $income)
                        <tr class="border-t border-slate-100">
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if(!empty($income['income_date']))
                                    {{ \Illuminate\Support\Carbon::parse($income['income_date'])->format($globalDateFormat) }}
                                @else
                                    --
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-semibold text-slate-900">{{ $income['title'] }}</div>
                                @if(!empty($income['notes']))
                                    <div class="text-xs text-slate-500">{{ $income['notes'] }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $income['category_name'] ?? '--' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                    {{ $income['source_label'] ?? ucfirst($income['source_type'] ?? 'manual') }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $income['customer_name'] ?? '--' }}</td>
                            <td class="px-3 py-2">{{ $income['project_name'] ?? '--' }}</td>
                            <td class="px-3 py-2 font-semibold text-slate-900">
                                {{ $currencySymbol }}{{ number_format((float) ($income['amount'] ?? 0), 2) }}{{ $currencyCode }}
                            </td>
                            <td class="px-3 py-2">
                                @if(!empty($income['attachment_path']) && ($income['source_type'] ?? 'manual') === 'manual')
                                    <a href="{{ route('admin.income.attachments.show', $income['source_id']) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
                                @else
                                    <span class="text-xs text-slate-400">--</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-slate-500">No income found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $incomes->links() }}</div>
    </div>
</div>
