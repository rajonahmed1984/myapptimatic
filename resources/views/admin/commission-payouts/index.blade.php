@extends('layouts.admin')

@section('title', 'Commission Payouts')
@section('page-title', 'Commission Payouts')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Commissions</div>
            <h1 class="text-2xl font-semibold text-slate-900">Payouts</h1>
            <div class="text-sm text-slate-500">Review payouts and create new ones from payable earnings.</div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.commission-payouts.export') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Export payouts CSV</a>
            <a href="{{ route('admin.commission-earnings.export') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Export earnings CSV</a>
            <a href="{{ route('admin.commission-payouts.create') }}" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">New payout</a>
        </div>
    </div>

    <div class="card p-6 space-y-6">
        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="text-sm font-semibold text-slate-800 mb-2">Payable by sales rep</div>
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @forelse($salesReps as $rep)
                    @php
                        $agg = $payableByRep[$rep->id] ?? null;
                    @endphp
                    <a href="{{ route('admin.commission-payouts.create', ['sales_rep_id' => $rep->id]) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 hover:border-emerald-300 hover:shadow-sm transition">
                        <div class="font-semibold text-slate-900">{{ $rep->name }}</div>
                        <div class="text-xs text-slate-500">Status: {{ ucfirst($rep->status) }}</div>
                        <div class="mt-1 text-xs text-slate-600">Payable: {{ $agg?->earnings_count ?? 0 }} items</div>
                        <div class="text-xs text-slate-600">Total: {{ number_format($agg?->total_amount ?? 0, 2) }}</div>
                    </a>
                @empty
                    <div class="text-sm text-slate-500">No payable earnings.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl border border-slate-300 bg-white/80 p-4">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-slate-800">Payout history</div>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead>
                        <tr class="text-xs uppercase text-slate-500">
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Sales rep</th>
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Paid at</th>
                            <th class="px-3 py-2">Updated</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payouts as $payout)
                            <tr class="border-t border-slate-300">
                                <td class="px-3 py-2">#{{ $payout->id }}</td>
                                <td class="px-3 py-2">{{ $payout->salesRep?->name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $payout->project?->name ?? '--' }}</td>
                                <td class="px-3 py-2">{{ ucfirst($payout->type ?? 'regular') }}</td>
                                <td class="px-3 py-2">{{ number_format($payout->total_amount, 2) }} {{ $payout->currency }}</td>
                                <td class="px-3 py-2">{{ ucfirst($payout->status) }}</td>
                                <td class="px-3 py-2">{{ $payout->paid_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $payout->updated_at?->format($globalDateFormat.' H:i') }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('admin.commission-payouts.show', $payout) }}" class="text-emerald-700 font-semibold hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-3 text-slate-500">No payouts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $payouts->links() }}
            </div>
        </div>
    </div>
@endsection
