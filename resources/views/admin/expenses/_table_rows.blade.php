@forelse($expenses as $expense)
    <tr class="border-t border-slate-100">
        <td class="px-3 py-2">{{ $expense['expense_date']?->format($globalDateFormat) ?? '--' }}</td>
        <td class="px-3 py-2">
            <div class="font-semibold text-slate-900">{{ $expense['title'] }}</div>
            @if($expense['notes'])
                <div class="text-xs text-slate-500">{{ $expense['notes'] }}</div>
            @endif
        </td>
        <td class="px-3 py-2">{{ $expense['category_name'] ?? '--' }}</td>
        <td class="px-3 py-2">
            @php
                $typeClasses = [
                    'one_time' => 'border-slate-300 text-slate-600 bg-slate-50',
                    'recurring' => 'border-amber-200 text-amber-700 bg-amber-50',
                    'salary' => 'border-blue-200 text-blue-700 bg-blue-50',
                    'contract_payout' => 'border-emerald-200 text-emerald-700 bg-emerald-50',
                    'sales_payout' => 'border-purple-200 text-purple-700 bg-purple-50',
                ];
                $typeKey = $expense['expense_type'] ?? $expense['source_type'] ?? 'expense';
            @endphp
            <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $typeClasses[$typeKey] ?? 'border-slate-300 text-slate-600 bg-slate-50' }}">
                {{ $expense['source_label'] ?? ucfirst(str_replace('_', ' ', $typeKey)) }}
            </span>
            @if(!empty($expense['source_detail']))
                <div class="text-[11px] text-slate-500">{{ $expense['source_detail'] }}</div>
            @endif
        </td>
        <td class="px-3 py-2">
            <div class="text-sm text-slate-700">{{ $expense['person_name'] ?? '--' }}</div>
        </td>
        <td class="px-3 py-2 font-semibold text-slate-900">{{ $formatCurrency($expense['amount']) }}</td>
        <td class="px-3 py-2">
            @if($expense['invoice_no'])
                <div class="text-xs font-semibold text-slate-700">{{ $expense['invoice_no'] }}</div>
                <div class="text-[11px] text-slate-500">{{ ucfirst($expense['invoice_status'] ?? 'issued') }}</div>
            @else
                <form method="POST" action="{{ route('admin.expenses.invoices.store') }}">
                    @csrf
                    <input type="hidden" name="source_type" value="{{ $expense['source_type'] }}">
                    <input type="hidden" name="source_id" value="{{ $expense['source_id'] }}">
                    <button type="submit" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Generate</button>
                </form>
            @endif
        </td>
        <td class="px-3 py-2">
            @if($expense['attachment_path'] && $expense['source_type'] === 'expense')
                <a href="{{ route('admin.expenses.attachments.show', $expense['source_id']) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
            @else
                <span class="text-xs text-slate-400">--</span>
            @endif
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-3 py-4 text-center text-slate-500">No expenses found.</td>
    </tr>
@endforelse
