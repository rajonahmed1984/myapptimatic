@extends('layouts.admin')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500">Track payments, refunds, credits, and expenses.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.accounting.create', ['type' => 'payment']) }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Payment</a>
            <a href="{{ route('admin.accounting.create', ['type' => 'refund']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">New Refund</a>
            <a href="{{ route('admin.accounting.create', ['type' => 'credit']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">New Credit</a>
            <a href="{{ route('admin.accounting.create', ['type' => 'expense']) }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">New Expense</a>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.accounting.index') }}" class="{{ $scope === 'ledger' ? 'rounded-full bg-slate-900 px-4 py-2 font-semibold text-white' : 'rounded-full border border-slate-200 px-4 py-2 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Ledger</a>
        <a href="{{ route('admin.accounting.transactions') }}" class="{{ $scope === 'transactions' ? 'rounded-full bg-slate-900 px-4 py-2 font-semibold text-white' : 'rounded-full border border-slate-200 px-4 py-2 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Transactions</a>
        <a href="{{ route('admin.accounting.refunds') }}" class="{{ $scope === 'refunds' ? 'rounded-full bg-slate-900 px-4 py-2 font-semibold text-white' : 'rounded-full border border-slate-200 px-4 py-2 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Refunds</a>
        <a href="{{ route('admin.accounting.credits') }}" class="{{ $scope === 'credits' ? 'rounded-full bg-slate-900 px-4 py-2 font-semibold text-white' : 'rounded-full border border-slate-200 px-4 py-2 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Credits</a>
        <a href="{{ route('admin.accounting.expenses') }}" class="{{ $scope === 'expenses' ? 'rounded-full bg-slate-900 px-4 py-2 font-semibold text-white' : 'rounded-full border border-slate-200 px-4 py-2 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600' }}">Expenses</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Gateway</th>
                    <th class="px-4 py-3">Amount</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                    @php($isOutflow = $entry->isOutflow())
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-600">{{ $entry->entry_date->format($globalDateFormat) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($entry->type) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->customer?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->invoice?->number ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->paymentGateway?->name ?? '-' }}</td>
                        <td class="px-4 py-3 font-semibold {{ $isOutflow ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $isOutflow ? '-' : '+' }}{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $entry->reference ?: '-' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.accounting.edit', $entry) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form method="POST" action="{{ route('admin.accounting.destroy', $entry) }}" onsubmit="return confirm('Delete this entry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-slate-500">No entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

