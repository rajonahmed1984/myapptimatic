@extends('layouts.admin')

@section('title', 'Payout #'.$payout->id)
@section('page-title', 'Payout #'.$payout->id)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Commission Payout</div>
            <h1 class="text-2xl font-semibold text-slate-900">Payout #{{ $payout->id }}</h1>
            <div class="text-sm text-slate-500">Sales rep: {{ $payout->salesRep?->name ?? '--' }}</div>
        </div>
        <a href="{{ route('admin.commission-payouts.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Back to payouts</a>
    </div>

    <div class="card p-6 space-y-6">
        <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Amount</div>
                <div class="mt-2 text-xl font-semibold text-slate-900">{{ number_format($payout->total_amount, 2) }} {{ $payout->currency }}</div>
                <div class="text-xs text-slate-500">Status: {{ ucfirst($payout->status) }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Details</div>
                <div class="mt-2 text-sm text-slate-700">Type: {{ ucfirst($payout->type ?? 'regular') }}</div>
                <div class="text-sm text-slate-700">Project: {{ $payout->project?->name ?? '--' }}</div>
                <div class="text-sm text-slate-700">Method: {{ $payout->payout_method ?? '--' }}</div>
                <div class="text-sm text-slate-700">Reference: {{ $payout->reference ?? '--' }}</div>
                <div class="text-sm text-slate-700">Note: {{ $payout->note ?? '--' }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Timeline</div>
                <div class="mt-2 text-sm text-slate-700">Created: {{ $payout->created_at?->format($globalDateFormat.' H:i') }}</div>
                <div class="text-sm text-slate-700">Paid: {{ $payout->paid_at?->format($globalDateFormat.' H:i') ?? '--' }}</div>
                <div class="text-sm text-slate-700">Reversed: {{ $payout->reversed_at?->format($globalDateFormat.' H:i') ?? '--' }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-sm font-semibold text-slate-800">Earnings in this payout</div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead>
                        <tr class="text-xs uppercase text-slate-500">
                            <th class="px-2 py-2">ID</th>
                            <th class="px-2 py-2">Source</th>
                            <th class="px-2 py-2">Customer</th>
                            <th class="px-2 py-2">Commission</th>
                            <th class="px-2 py-2">Status</th>
                            <th class="px-2 py-2">Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payout->earnings as $earning)
                            <tr class="border-t border-slate-200">
                                <td class="px-2 py-2">#{{ $earning->id }}</td>
                                <td class="px-2 py-2">
                                    {{ ucfirst($earning->source_type) }}
                                    @if($earning->invoice)
                                        (Invoice #{{ $earning->invoice->id }})
                                    @elseif($earning->project)
                                        (Project #{{ $earning->project->id }})
                                    @endif
                                </td>
                                <td class="px-2 py-2">{{ $earning->customer?->name ?? '--' }}</td>
                                <td class="px-2 py-2">{{ number_format($earning->commission_amount, 2) }} {{ $earning->currency }}</td>
                                <td class="px-2 py-2">{{ ucfirst($earning->status) }}</td>
                                <td class="px-2 py-2">{{ $earning->earned_at?->format($globalDateFormat.' H:i') ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-3 text-slate-500">No earnings linked (advance payment).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 space-y-3">
            <div class="text-sm font-semibold text-slate-800">Actions</div>
            @if($payout->status !== 'paid' && $payout->status !== 'reversed')
                <form method="POST" action="{{ route('admin.commission-payouts.pay', $payout) }}" class="grid gap-3 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-500">Reference</label>
                        <input name="reference" value="{{ old('reference', $payout->reference) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Payout method</label>
                        <select name="payout_method" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Keep</option>
                            <option value="bank">Bank</option>
                            <option value="mobile">Mobile</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-500">Note</label>
                        <input name="note" value="{{ old('note', $payout->note) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-3 flex items-center gap-3">
                        <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Mark as paid</button>
                    </div>
                </form>
            @endif

            @if($payout->status !== 'reversed')
                <form method="POST" action="{{ route('admin.commission-payouts.reverse', $payout) }}" class="space-y-2" onsubmit="return confirm('Reverse this payout? Earnings will return to payable.');">
                    @csrf
                    <label class="text-xs text-slate-500">Reversal note</label>
                    <input name="note" value="{{ old('note') }}" class="w-full rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm">
                    <button type="submit" class="rounded-full border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reverse payout</button>
                </form>
            @endif
        </div>
    </div>
@endsection
