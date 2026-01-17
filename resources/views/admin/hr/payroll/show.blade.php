@extends('layouts.admin')

@section('title', 'Payroll '.$period->period_key)
@section('page-title', 'Payroll')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Payroll {{ $period->period_key }}</div>
            <div class="text-sm text-slate-500">
                {{ $period->start_date?->format($globalDateFormat) }} - {{ $period->end_date?->format($globalDateFormat) }}
                · {{ ucfirst($period->status) }}
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.hr.payroll.export', $period) }}" class="text-sm text-slate-700 hover:underline">Export CSV</a>
            @if($period->status === 'draft')
                <form method="POST" action="{{ route('admin.hr.payroll.finalize', $period) }}">
                    @csrf
                    <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Finalize</button>
                </form>
            @endif
            <a href="{{ route('admin.hr.payroll.index') }}" class="text-sm text-slate-600 hover:text-slate-800">Back</a>
        </div>
    </div>

    @if($totals->isNotEmpty())
        <div class="mb-6 grid gap-4 md:grid-cols-3">
            @foreach($totals as $total)
                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Totals ({{ $total->currency }})</div>
                    <div class="mt-2 space-y-1">
                        <div>Base: {{ number_format((float) $total->base_total, 2) }}</div>
                        <div>Gross: {{ number_format((float) $total->gross_total, 2) }}</div>
                        <div>Net: {{ number_format((float) $total->net_total, 2) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card p-6">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Employee</th>
                        <th class="py-2 px-3">Pay Type</th>
                        <th class="py-2 px-3">Currency</th>
                        <th class="py-2 px-3">Base</th>
                        <th class="py-2 px-3">Hours</th>
                        <th class="py-2 px-3">Overtime</th>
                        <th class="py-2 px-3">Bonus</th>
                        <th class="py-2 px-3">Penalty</th>
                        <th class="py-2 px-3">Advance</th>
                        <th class="py-2 px-3">Deduction</th>
                        <th class="py-2 px-3">Gross</th>
                        <th class="py-2 px-3">Net</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3">Paid At</th>
                        <th class="py-2 px-3">Reference</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $bonus = is_array($item->bonuses) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->bonuses)) : (float) ($item->bonuses ?? 0);
                            $penalty = is_array($item->penalties) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->penalties)) : (float) ($item->penalties ?? 0);
                            $advance = is_array($item->advances) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->advances)) : (float) ($item->advances ?? 0);
                            $deduction = is_array($item->deductions) ? array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? $row ?? 0), $item->deductions)) : (float) ($item->deductions ?? 0);
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3">{{ $item->employee?->name ?? 'N/A' }}</td>
                            <td class="py-2 px-3">{{ ucfirst($item->pay_type) }}</td>
                            <td class="py-2 px-3">{{ $item->currency }}</td>
                            <td class="py-2 px-3">{{ number_format((float) $item->base_pay, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format((float) $item->timesheet_hours, 2) }}</td>
                            <td class="py-2 px-3">
                                {{ number_format((float) $item->overtime_hours, 2) }}
                                @if($item->overtime_rate)
                                    <div class="text-[11px] text-slate-500">@ {{ number_format((float) $item->overtime_rate, 2) }}</div>
                                @endif
                            </td>
                            <td class="py-2 px-3">{{ number_format($bonus, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format($penalty, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format($advance, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format($deduction, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format((float) $item->gross_pay, 2) }}</td>
                            <td class="py-2 px-3">{{ number_format((float) $item->net_pay, 2) }}</td>
                            <td class="py-2 px-3">{{ ucfirst($item->status) }}</td>
                            <td class="py-2 px-3">{{ $item->paid_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                            <td class="py-2 px-3">{{ $item->payment_reference ?? '--' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="py-3 px-3 text-center text-slate-500">No payroll items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>
@endsection
