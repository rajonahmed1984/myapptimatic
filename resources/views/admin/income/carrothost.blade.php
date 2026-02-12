@extends('layouts.admin')

@section('title', 'CarrotHost Income')
@section('page-title', 'CarrotHost Income')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Income Sync</div>
            <div class="text-2xl font-semibold text-slate-900">CarrotHost</div>
            <div class="mt-1 text-sm text-slate-500">Transactions (from {{ $startDate }}).</div>
        </div>
        <div class="text-xs text-slate-500">Last refreshed: {{ now()->format($globalDateFormat.' H:i') }}</div>
    </div>

    @if(!empty($whmcsErrors))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <div class="font-semibold text-amber-900">WHMCS warnings</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($whmcsErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        <div class="card p-6">
            <div class="text-sm font-semibold text-slate-700">Transactions</div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-700">
                    <thead class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th class="px-3 py-2 whitespace-nowrap">Date</th>
                            <th class="px-3 py-2">Transaction ID</th>
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Amount In</th>
                            <th class="px-3 py-2">Fees</th>
                            <th class="px-3 py-2">Gateway</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $row)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2 whitespace-nowrap">{{ $row['date'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['transid'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['invoiceid'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['clientname'] ?? ($row['userid'] ?? '--') }}</td>
                                <td class="px-3 py-2">{{ $row['amountin'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['fees'] ?? '--' }}</td>
                                <td class="px-3 py-2">{{ $row['gateway'] ?? '--' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-slate-500">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
