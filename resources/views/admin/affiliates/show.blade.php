@extends('layouts.admin')

@section('title', 'Affiliate Details')
@section('page-title', 'Affiliate Details')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('admin.affiliates.index') }}" class="text-sm text-teal-600 hover:text-teal-500">← Back to affiliates</a>
        <div class="flex gap-3">
            <a href="{{ route('admin.affiliates.edit', $affiliate) }}" class="rounded-full border border-slate-200 px-6 py-2 text-sm font-semibold text-slate-600">
                Edit
            </a>
            <form method="POST" action="{{ route('admin.affiliates.destroy', $affiliate) }}" onsubmit="return confirm('Delete this affiliate?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-6 py-2 text-sm font-semibold text-rose-600">
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
        
        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <div>
                <div class="text-sm text-slate-600">Customer</div>
                <div class="mt-1 font-semibold text-slate-900">{{ $affiliate->customer->name }}</div>
                <div class="text-sm text-slate-500">{{ $affiliate->customer->email }}</div>
            </div>

            <div>
                <div class="text-sm text-slate-600">Affiliate Code</div>
                <code class="mt-1 inline-block rounded bg-slate-100 px-3 py-1 font-mono text-sm text-slate-700">{{ $affiliate->affiliate_code }}</code>
            </div>

            <div>
                <div class="text-sm text-slate-600">Status</div>
                <div class="mt-1">
                    @if($affiliate->status === 'active')
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
                    @elseif($affiliate->status === 'suspended')
                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Suspended</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">Inactive</span>
                    @endif
                </div>
            </div>

            <div>
                <div class="text-sm text-slate-600">Commission</div>
                <div class="mt-1 font-semibold">
                    @if($affiliate->commission_type === 'percentage')
                        {{ $affiliate->commission_rate }}%
                    @else
                        ${{ number_format($affiliate->fixed_commission_amount, 2) }}
                    @endif
                </div>
            </div>

            <div>
                <div class="text-sm text-slate-600">Referral Link</div>
                <div class="mt-1">
                    <input type="text" value="{{ $affiliate->getReferralLink() }}" readonly class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-1 text-sm" />
                </div>
            </div>

            <div>
                <div class="text-sm text-slate-600">Stats</div>
                <div class="mt-1 text-sm">
                    <span class="font-semibold">{{ $affiliate->total_referrals }}</span> clicks, 
                    <span class="font-semibold">{{ $affiliate->total_conversions }}</span> conversions
                </div>
            </div>
        </div>

        @if($affiliate->payment_details)
            <div class="mt-6">
                <div class="text-sm text-slate-600">Payment Details</div>
                <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">{{ $affiliate->payment_details }}</div>
            </div>
        @endif

        @if($affiliate->notes)
            <div class="mt-6">
                <div class="text-sm text-slate-600">Notes</div>
                <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">{{ $affiliate->notes }}</div>
            </div>
        @endif
    </div>

    <div class="card mt-6 p-6">
        <h2 class="text-lg font-semibold text-slate-900">Recent Commissions</h2>
        
        @if($affiliate->commissions->isEmpty())
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-600">
                No commissions yet.
            </div>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="pb-3 font-semibold">Date</th>
                            <th class="pb-3 font-semibold">Description</th>
                            <th class="pb-3 font-semibold">Amount</th>
                            <th class="pb-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach($affiliate->commissions->take(10) as $commission)
                            <tr>
                                <td class="py-3 text-slate-600">{{ $commission->created_at->format('M d, Y') }}</td>
                                <td class="py-3">{{ $commission->description }}</td>
                                <td class="py-3 font-semibold">${{ number_format($commission->amount, 2) }}</td>
                                <td class="py-3">
                                    @if($commission->status === 'paid')
                                        <span class="text-xs text-emerald-600">Paid</span>
                                    @elseif($commission->status === 'approved')
                                        <span class="text-xs text-teal-600">Approved</span>
                                    @else
                                        <span class="text-xs text-amber-600">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
