@extends('layouts.admin')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
    <div class="card p-6">
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2">Period</th>
                    <th class="py-2">Gross</th>
                    <th class="py-2">Net</th>
                    <th class="py-2">Advance Paid</th>
                    <th class="py-2">Est. Salary</th>                    
                    <th class="py-2">Status</th>
                    <th class="py-2">Paid Amount</th>
                    <th class="py-2">Paid at</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr class="border-b border-slate-100">
                        <td class="py-2">{{ $item->period?->period_key ?? '--' }}</td>
                        <td class="py-2">{{ number_format($item->gross_pay, 2) }} {{ $item->currency }}</td>
                        <td class="py-2">{{ number_format($item->net_pay, 2) }} {{ $item->currency }}</td>
                        <td class="py-2">{{ number_format((float) ($advancePaidByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2">
                            @if($workSessionEligible)
                                {{ number_format((float) ($estimatedSalaryByItem[$item->id] ?? 0), 2) }} {{ $estimatedCurrency }}
                            @else
                                --
                            @endif
                        </td>
                        <td class="py-2">{{ ucfirst($item->status) }}</td>
                        <td class="py-2">{{ number_format((float) ($paidAmountByItem[$item->id] ?? 0), 2) }} {{ $item->currency }}</td>
                        <td class="py-2">{{ $item->paid_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-3 text-center text-slate-500">No payroll items yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
@endsection
