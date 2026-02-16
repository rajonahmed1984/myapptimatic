@extends('layouts.admin')

@section('title', 'Payment Method Ledger')
@section('page-title', 'Payment Method Ledger')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $paymentMethod->name }} Ledger</h1>
            <p class="mt-1 text-sm text-slate-500">Code: {{ $paymentMethod->code }}</p>
        </div>
        <a href="{{ route('admin.finance.payment-methods.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to methods</a>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div class="card p-5">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Total Entries</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format((int) ($summary['total_entries'] ?? 0)) }}</div>
        </div>
        <div class="card p-5">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Total Amount</div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $summary['total_amount'] ?? '0.00' }}</div>
        </div>
    </div>

    <div class="card p-6">
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="min-w-full text-sm text-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Party</th>
                        <th class="px-3 py-2">Reference</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledger as $row)
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-2">{{ $row['date'] ?: '--' }}</td>
                            <td class="px-3 py-2">{{ $row['type'] }}</td>
                            <td class="px-3 py-2">{{ $row['party'] }}</td>
                            <td class="px-3 py-2 text-xs text-slate-600">{{ $row['reference'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ number_format((float) ($row['amount'] ?? 0), 2) }} {{ $row['currency'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-slate-500">No ledger entries found for this payment method.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $ledger->links() }}
        </div>
    </div>
@endsection
