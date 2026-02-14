@extends('layouts.admin')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">HR</div>
            <div class="text-2xl font-semibold text-slate-900">Payroll periods</div>
            <div class="text-sm text-slate-500">Generate, review, and finalize payroll based on work logs and compensation rules.</div>
        </div>
        <form method="POST" action="{{ route('admin.hr.payroll.generate') }}" class="flex items-center gap-2">
            @csrf
            @php
                $selectedGeneratePeriod = old('period_key', now()->format('Y-m'));
                $generatePeriods = collect(range(0, 36))
                    ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset))
                    ->values();
            @endphp
            <select name="period_key" class="w-40 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                @foreach($generatePeriods as $periodOption)
                    <option value="{{ $periodOption->format('Y-m') }}" @selected($selectedGeneratePeriod === $periodOption->format('Y-m'))>
                        {{ $periodOption->format('M Y') }}
                    </option>
                @endforeach
            </select>
            <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Generate</button>
        </form>
    </div>

    <div class="mb-6 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Draft Periods</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $summary['draft_periods'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Finalized Periods</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $summary['finalized_periods'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">To Pay Items</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $summary['approved_items_to_pay'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Paid Items</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $summary['paid_items'] ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Work Log Days (Month)</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $workLogDaysThisMonth ?? 0 }}</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Paid Holidays (Month)</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">{{ $paidHolidaysThisMonth ?? 0 }}</div>
        </div>
    </div>

    <div class="card p-6">
        <form method="GET" action="{{ route('admin.hr.payroll.index') }}" class="mb-5 grid gap-3 md:grid-cols-4">
            <div>
                <label for="periodKeyFilter" class="text-xs uppercase tracking-[0.2em] text-slate-500">Period</label>
                <input id="periodKeyFilter" type="month" name="period_key" value="{{ $selectedPeriodKey ?? '' }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="periodStatusFilter" class="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                <select id="periodStatusFilter" name="status" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                    <option value="">All</option>
                    <option value="draft" @selected(($selectedStatus ?? '') === 'draft')>Draft</option>
                    <option value="finalized" @selected(($selectedStatus ?? '') === 'finalized')>Finalized</option>
                </select>
            </div>
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                <a href="{{ route('admin.hr.payroll.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                <tr class="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <th class="py-2 px-3">Period</th>
                    <th class="py-2 px-3">Dates</th>
                    <th class="py-2 px-3">Status</th>
                    <th class="py-2 px-3">Items</th>
                    <th class="py-2 px-3 text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($periods as $period)
                    <tr class="border-b border-slate-100">
                        <td class="py-2 px-3">{{ $period->period_key }}</td>
                        <td class="py-2 px-3">{{ $period->start_date?->format($globalDateFormat) }} - {{ $period->end_date?->format($globalDateFormat) }}</td>
                        <td class="py-2 px-3">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $period->status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">
                                {{ ucfirst($period->status) }}
                            </span>
                        </td>
                        <td class="py-2 px-3">
                            <div>Total: {{ $period->items_count }}</div>
                            <div class="text-xs text-slate-500">To Pay: {{ $period->approved_items_count ?? 0 }} | Paid: {{ $period->paid_items_count ?? 0 }}</div>
                        </td>
                        <td class="py-2 px-3 text-right space-x-2">
                            <a href="{{ route('admin.hr.payroll.show', $period) }}" class="text-xs text-slate-700 hover:underline">View</a>
                            <a href="{{ route('admin.hr.payroll.export', $period) }}" class="text-xs text-slate-700 hover:underline">Export CSV</a>
                            @if($period->status === 'draft')
                                <a href="{{ route('admin.hr.payroll.edit', $period) }}" class="text-xs text-slate-700 hover:underline">Edit</a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.hr.payroll.destroy', $period) }}"
                                    class="inline"
                                    data-delete-confirm
                                    data-confirm-name="{{ $period->period_key }}"
                                    data-confirm-title="Delete payroll period {{ $period->period_key }}?"
                                    data-confirm-description="This will permanently delete this payroll period and its payroll items."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-700 hover:underline">Delete</button>
                                </form>
                            @endif
                            @if($period->status === 'draft' && $period->end_date?->lt(today()))
                                <form method="POST" action="{{ route('admin.hr.payroll.finalize', $period) }}" class="inline">
                                    @csrf
                                    <button class="text-xs text-emerald-700 hover:underline">Finalize</button>
                                </form>
                            @elseif($period->status === 'draft')
                                <span class="text-xs text-amber-700">Month not closed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-3 px-3 text-center text-slate-500">No payroll periods.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $periods->links() }}
        </div>
    </div>
@endsection
