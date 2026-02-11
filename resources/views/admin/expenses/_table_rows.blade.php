@forelse($expenses as $expense)
    <tr class="border-t border-slate-100">
        <td class="px-3 py-2 font-semibold text-slate-900">{{ $expense['invoice_no'] ?? '--' }}</td>
        <td class="px-3 py-2">{{ $expense['expense_date']?->format($globalDateFormat) ?? '--' }}</td>
        <td class="px-3 py-2">
            <div class="font-semibold text-slate-900">{{ $expense['title'] }}</div>
            @if($expense['notes'])
                <div class="text-xs text-slate-500">{{ $expense['notes'] }}</div>
            @endif
        </td>
        <td class="px-3 py-2">{{ $expense['category_name'] ?? '--' }}</td>
        <td class="px-3 py-2">
            <div class="text-sm text-slate-700">{{ $expense['person_name'] ?? '--' }}</div>
        </td>
        <td class="px-3 py-2 font-semibold text-slate-900">{{ $formatCurrency($expense['amount']) }}</td>
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
        <td colspan="7" class="px-3 py-4 text-center text-slate-500">No expenses found.</td>
    </tr>
@endforelse
