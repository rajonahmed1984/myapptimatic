@extends('layouts.admin')

@section('title', 'Recurring Expense')
@section('page-title', 'Recurring Expense')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted}{$currencyCode}";
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Recurring Expense</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $recurringExpense->title }}</div>
            <div class="mt-1 text-sm text-slate-500">
                {{ $recurringExpense->category?->name ?? 'No category' }}
                · {{ $formatCurrency($recurringExpense->amount) }}
                · Every {{ $recurringExpense->recurrence_interval }} {{ $recurringExpense->recurrence_type === 'yearly' ? 'year(s)' : 'month(s)' }}
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.expenses.recurring.edit', $recurringExpense) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.expenses.recurring.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next run</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">
                {{ $recurringExpense->next_run_date?->format($globalDateFormat) ?? '--' }}
            </div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next due</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">
                {{ $nextDueDate ? \Carbon\Carbon::parse($nextDueDate)->format($globalDateFormat) : '--' }}
            </div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Total invoices</div>
            <div class="mt-2 text-lg font-semibold text-slate-900">{{ $totalInvoices }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Unpaid</div>
            <div class="mt-2 text-lg font-semibold text-amber-600">{{ $unpaidCount }}</div>
        </div>
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Overdue</div>
            <div class="mt-2 text-lg font-semibold text-rose-600">{{ $overdueCount }}</div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden">
        <div class="px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Invoice</th>
                        <th class="py-2 px-3">Expense date</th>
                        <th class="py-2 px-3">Due date</th>
                        <th class="py-2 px-3">Amount</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $displayStatus = $invoice->status ?? 'unpaid';
                            if ($displayStatus === 'issued') {
                                $displayStatus = 'unpaid';
                            }
                            if ($displayStatus !== 'paid' && $invoice->due_date && $invoice->due_date->isPast()) {
                                $displayStatus = 'overdue';
                            }
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $invoice->invoice_no }}</td>
                            <td class="py-2 px-3">{{ $invoice->expense?->expense_date?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="py-2 px-3">{{ $invoice->due_date?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $formatCurrency($invoice->amount) }}</td>
                            <td class="py-2 px-3">
                                <x-status-badge :status="$displayStatus" />
                            </td>
                            <td class="py-2 px-3 text-right">
                                @if($displayStatus !== 'paid')
                                    <form method="POST" action="{{ route('admin.expenses.invoices.pay', $invoice) }}">
                                        @csrf
                                        <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">Payment</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">Paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 px-3 text-center text-slate-500">No expense invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>
@endsection
