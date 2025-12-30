@extends('layouts.admin')

@section('title', 'Affiliate Commissions')
@section('page-title', 'Affiliate Commissions')

@section('content')
    <div class="mb-6">
        <div class="section-label">Commission Management</div>
        <h1 class="mt-2 text-2xl font-semibold text-slate-900">Affiliate commissions</h1>
    </div>

    <div class="card p-6">
        <form method="GET" class="mb-6 flex flex-wrap gap-4">
            <select name="affiliate_id" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                <option value="">All affiliates</option>
                @foreach($affiliates as $affiliate)
                    <option value="{{ $affiliate->id }}" @selected(request('affiliate_id') == $affiliate->id)>
                        {{ $affiliate->customer->name }} ({{ $affiliate->affiliate_code }})
                    </option>
                @endforeach
            </select>
            <select name="status" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                <option value="">All statuses</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                <option value="paid" @selected(request('status') === 'paid')>Paid</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
            <button type="submit" class="rounded-full bg-slate-900 px-6 py-2 text-sm font-semibold text-white">
                Filter
            </button>
            @if(request()->hasAny(['affiliate_id', 'status']))
                <a href="{{ route('admin.affiliates.commissions.index') }}" class="rounded-full border border-slate-200 px-6 py-2 text-sm font-semibold text-slate-600">
                    Clear
                </a>
            @endif
        </form>

        @if($commissions->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-600">
                No commissions found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="pb-3 font-semibold">Date</th>
                            <th class="pb-3 font-semibold">Affiliate</th>
                            <th class="pb-3 font-semibold">Description</th>
                            <th class="pb-3 font-semibold">Amount</th>
                            <th class="pb-3 font-semibold">Status</th>
                            <th class="pb-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach($commissions as $commission)
                            <tr>
                                <td class="py-4 text-slate-600">{{ $commission->created_at->format('M d, Y') }}</td>
                                <td class="py-4">
                                    <div class="font-semibold">{{ $commission->affiliate->customer->name }}</div>
                                    <code class="text-xs text-slate-500">{{ $commission->affiliate->affiliate_code }}</code>
                                </td>
                                <td class="py-4">{{ $commission->description }}</td>
                                <td class="py-4 font-semibold">${{ number_format($commission->amount, 2) }}</td>
                                <td class="py-4">
                                    @if($commission->status === 'paid')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">Paid</span>
                                    @elseif($commission->status === 'approved')
                                        <span class="inline-flex items-center rounded-full bg-teal-100 px-3 py-1 text-xs font-semibold text-teal-700">Approved</span>
                                    @elseif($commission->status === 'cancelled')
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">Cancelled</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                    @endif
                                </td>
                                <td class="py-4">
                                    @if($commission->status === 'pending')
                                        <form method="POST" action="{{ route('admin.affiliates.commissions.approve', $commission) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-teal-600 hover:text-teal-500">Approve</button>
                                        </form>
                                        <span class="mx-2 text-slate-300">|</span>
                                        <form method="POST" action="{{ route('admin.affiliates.commissions.reject', $commission) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-rose-600 hover:text-rose-500">Reject</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $commissions->links() }}
            </div>
        @endif
    </div>
@endsection
