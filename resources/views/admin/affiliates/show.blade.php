@extends('layouts.admin')

@section('title', 'Affiliate Details')
@section('page-title', 'Affiliate Details')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Affiliates</div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $affiliate->customer->name }}</h1>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="rounded-full border border-slate-200 px-6 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                Edit
            </a>
            <form
                method="POST"
                action="{{ route('admin.affiliates.destroy', $affiliate) }}"
                data-delete-confirm
                data-confirm-name="{{ $affiliate->customer->name }}"
                data-confirm-title="Delete affiliate {{ $affiliate->customer->name }}?"
                data-confirm-description="This will permanently delete the affiliate record."
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-6 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Total Earned</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">${{ number_format($affiliate->total_earned, 2) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Balance</div>
            <div class="mt-2 text-3xl font-bold text-teal-600">${{ number_format($affiliate->balance, 2) }}</div>
        </div>
        <div class="card p-6">
            <div class="text-xs uppercase tracking-wider text-slate-400">Conversion Rate</div>
            <div class="mt-2 text-3xl font-bold text-slate-900">{{ $stats['conversion_rate'] }}%</div>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Affiliate Information</h2>
        <div class="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                <div class="mt-1 font-semibold text-slate-900">{{ $affiliate->customer->name }}</div>
                <div class="text-xs text-slate-500">{{ $affiliate->customer->email }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Code</div>
                <div class="mt-1 font-mono text-sm text-slate-800">{{ $affiliate->affiliate_code }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</div>
                <div class="mt-1 text-sm text-slate-700">{{ ucfirst($affiliate->status) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Commission</div>
                <div class="mt-1 text-sm text-slate-700">
                    @if($affiliate->commission_type === 'percentage')
                        {{ $affiliate->commission_rate }}%
                    @else
                        ${{ number_format($affiliate->fixed_commission_amount, 2) }}
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Performance</h2>
        <div class="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total Clicks</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['total_clicks'] }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Referrals</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $affiliate->total_referrals }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversions</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['total_conversions'] }}</div>
            </div>
        </div>
    </div>
@endsection
