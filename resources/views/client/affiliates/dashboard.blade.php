@extends('layouts.client')

@section('title', 'Affiliate Dashboard')
@section('page-title', 'Affiliate Dashboard')

@section('content')
    <div class="mb-6">
        <div class="section-label">Affiliate Program</div>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Your affiliate dashboard</h1>
    </div>

    @if($affiliate->status !== 'active')
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
            Your affiliate account is {{ $affiliate->status }}. {{ $affiliate->status === 'inactive' ? 'It is pending approval.' : '' }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Balance</div>
            <div class="mt-2 text-3xl font-bold text-teal-600">${{ number_format($affiliate->balance, 2) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Total Earned</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">${{ number_format($affiliate->total_earned, 2) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Clicks</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['total_clicks'] }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Conversions</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['total_conversions'] }}</div>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Your Referral Link</h2>
        <div class="mt-4 flex gap-3">
            <input type="text" id="referral-link" value="{{ $affiliate->getReferralLink() }}" readonly class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm" />
            <button onclick="copyLink()" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white hover:bg-teal-400">
                Copy Link
            </button>
        </div>
        <p class="mt-2 text-xs text-slate-500">Share this link to earn commissions on referrals.</p>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card p-6">
            <h2 class="text-lg font-semibold text-slate-900">Commission Summary</h2>
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">Pending</span>
                    <span class="font-semibold">${{ number_format($stats['pending_commissions'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">Approved</span>
                    <span class="font-semibold">${{ number_format($stats['approved_commissions'], 2) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-600">Paid</span>
                    <span class="font-semibold">${{ number_format($stats['paid_commissions'], 2) }}</span>
                </div>
            </div>
            <a href="{{ route('client.affiliates.commissions') }}" class="mt-4 inline-block text-sm text-teal-600 hover:text-teal-500">View all commissions →</a>
        </div>

        <div class="card p-6">
            <h2 class="text-lg font-semibold text-slate-900">Recent Referrals</h2>
            @if($stats['recent_referrals']->isEmpty())
                <p class="mt-4 text-sm text-slate-600">No referrals yet.</p>
            @else
                <div class="mt-4 space-y-3">
                    @foreach($stats['recent_referrals']->take(5) as $referral)
                        <div class="flex items-center justify-between text-sm">
                            <div>
                                <div class="font-semibold">{{ $referral->customer ? $referral->customer->name : 'Visitor' }}</div>
                                <div class="text-xs text-slate-500">{{ $referral->created_at->format('M d, Y') }}</div>
                            </div>
                            <span class="text-xs {{ $referral->status === 'converted' ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ ucfirst($referral->status) }}
                            </span>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('client.affiliates.referrals') }}" class="mt-4 inline-block text-sm text-teal-600 hover:text-teal-500">View all referrals →</a>
            @endif
        </div>
    </div>

    <script>
        function copyLink() {
            const input = document.getElementById('referral-link');
            input.select();
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        }
    </script>
@endsection
