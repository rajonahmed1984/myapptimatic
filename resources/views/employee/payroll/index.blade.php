@extends('layouts.admin')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
    <div class="card p-6">
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Period</th>
                    <th class="py-2 px-3 text-right">Gross</th>
                    <th class="py-2 px-3 text-right">Bonus</th>
                    <th class="py-2 px-3 text-right">Penalty</th>
                    <th class="py-2 px-3 text-right">Advance</th>
                    <th class="py-2 px-3 text-right">Deduction</th>
                    <th class="py-2 px-3 text-right">Net Payable</th>
                    <th class="py-2 px-3 text-right">Paid</th>
                    <th class="py-2 px-3 text-right">Remaining</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3">Paid at</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $item->period?->period_key ?? '--' }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($item->gross_pay ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($bonusByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($penaltyByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($advancePaidByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($deductionByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right font-semibold text-slate-900">{{ number_format((float) ($netPayableByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format((float) ($paidAmountByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3 text-right font-semibold {{ (($remainingByItem[$item->id] ?? 0) > 0) ? 'text-amber-600' : 'text-emerald-600' }}">{{ number_format((float) ($remainingByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2 px-3">{{ ucfirst($item->status) }}</td>
                        <td class="py-2 px-3">{{ $item->paid_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-3 text-center text-slate-500">No payroll items yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
@endsection
