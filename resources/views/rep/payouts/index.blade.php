@extends('layouts.rep')

@section('title', 'My Payouts')
@section('page-title', 'My Payouts')

@section('content')
    <div class="card p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="section-label">Commissions</div>
                <h1 class="text-2xl font-semibold text-slate-900">Payout history</h1>
                <div class="text-sm text-slate-500">Read-only view of your payouts.</div>
            </div>
            <a href="{{ route('rep.dashboard') }}" class="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead>
                        <tr class="text-xs uppercase text-slate-500">
                            <th class="px-2 py-2">ID</th>
                            <th class="px-2 py-2">Amount</th>
                            <th class="px-2 py-2">Status</th>
                            <th class="px-2 py-2">Method</th>
                            <th class="px-2 py-2">Paid at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payouts as $payout)
                            <tr class="border-t border-slate-200">
                                <td class="px-2 py-2">#{{ $payout->id }}</td>
                                <td class="px-2 py-2">{{ number_format($payout->total_amount, 2) }} {{ $payout->currency }}</td>
                                <td class="px-2 py-2">{{ ucfirst($payout->status) }}</td>
                                <td class="px-2 py-2">{{ $payout->payout_method ?? '—' }}</td>
                                <td class="px-2 py-2">{{ $payout->paid_at?->format($globalDateFormat.' H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-2 py-3 text-slate-500">No payouts yet.</td>
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
