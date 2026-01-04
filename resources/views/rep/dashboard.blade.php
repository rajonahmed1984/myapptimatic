@extends('layouts.rep')

@section('title', 'Sales Rep Dashboard')
@section('page-title', 'Sales Rep Dashboard')

@section('content')
    <div class="card p-6 space-y-6">
        <div>
            <div class="section-label">Commissions</div>
            <h1 class="text-2xl font-semibold text-slate-900">Welcome, {{ $rep->name }}</h1>
            <div class="text-sm text-slate-500">View your earnings, payouts, and balances.</div>
        </div>

        <div class="grid gap-4 md:grid-cols-4 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Payable balance</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($balance['payable_balance'], 2) }}</div>
                <div class="text-xs text-slate-500">Awaiting payout</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total earned</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($balance['total_earned'], 2) }}</div>
                <div class="text-xs text-slate-500">All time</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Earned this month</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($earnedThisMonth, 2) }}</div>
                <div class="text-xs text-slate-500">Current month</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Paid this month</div>
                <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($paidThisMonth, 2) }}</div>
                <div class="text-xs text-slate-500">Payouts completed</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Recent earnings</div>
                    <a href="{{ route('rep.earnings.index') }}" class="text-xs text-emerald-700 font-semibold hover:underline">View all</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($recentEarnings as $earning)
                        <div class="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-slate-900">#{{ $earning->id }} · {{ ucfirst($earning->source_type) }}</div>
                                <div class="text-xs text-slate-500">{{ $earning->earned_at?->format($globalDateFormat.' H:i') ?? '—' }}</div>
                            </div>
                            <div class="text-xs text-slate-600">Commission: {{ number_format($earning->commission_amount, 2) }} {{ $earning->currency }}</div>
                            <div class="text-xs text-slate-500">Status: {{ ucfirst($earning->status) }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No earnings yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-800">Recent payouts</div>
                    <a href="{{ route('rep.payouts.index') }}" class="text-xs text-emerald-700 font-semibold hover:underline">View all</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse($recentPayouts as $payout)
                        <div class="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <div class="font-semibold text-slate-900">Payout #{{ $payout->id }}</div>
                                <div class="text-xs text-slate-500">{{ $payout->paid_at?->format($globalDateFormat.' H:i') ?? 'Draft' }}</div>
                            </div>
                            <div class="text-xs text-slate-600">Amount: {{ number_format($payout->total_amount, 2) }} {{ $payout->currency }}</div>
                            <div class="text-xs text-slate-500">Status: {{ ucfirst($payout->status) }}</div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">No payouts yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
