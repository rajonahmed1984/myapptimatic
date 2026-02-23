@extends('layouts.admin')

@section('title', 'Recurring Expense')
@section('page-title', 'Recurring Expense')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted} {$currencyCode}";
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Recurring Expense</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $recurringExpense->title }}</div>
            <div class="mt-1 text-sm text-slate-500">
                {{ $recurringExpense->category?->name ?? 'No category' }}
                | {{ $formatCurrency($recurringExpense->amount) }}
                | Every {{ $recurringExpense->recurrence_interval }} {{ $recurringExpense->recurrence_type === 'yearly' ? 'year(s)' : 'month(s)' }}
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.expenses.recurring.edit', $recurringExpense) }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Edit</a>
            <a href="{{ route('admin.expenses.recurring.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next due date</div>
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
        <div class="card px-4 py-3">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Advance balance</div>
            <div class="mt-2 text-lg font-semibold text-emerald-600">{{ $formatCurrency($advanceBalance ?? 0) }}</div>
            <div class="mt-1 text-[11px] text-slate-500">
                Total: {{ $formatCurrency($advanceTotal ?? 0) }} | Used: {{ $formatCurrency($advanceUsed ?? 0) }}
            </div>
        </div>
    </div>

    <div class="mt-6 overflow-hidden">
        <div class="mb-2 section-label">Advance Details</div>
        <div class="px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">Date</th>
                        <th class="py-2 px-3">Method</th>
                        <th class="py-2 px-3">Amount</th>
                        <th class="py-2 px-3">Reference</th>
                        <th class="py-2 px-3">Note</th>
                        <th class="py-2 px-3">By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($advancePayments as $advance)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3">{{ $advance->paid_at?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="py-2 px-3">{{ strtoupper((string) $advance->payment_method) }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $formatCurrency($advance->amount) }}</td>
                            <td class="py-2 px-3">{{ $advance->payment_reference ?: '--' }}</td>
                            <td class="py-2 px-3">{{ $advance->note ?: '--' }}</td>
                            <td class="py-2 px-3">{{ $advance->creator?->name ?? '--' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 px-3 text-center text-slate-500">No advance payment yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $advancePayments->links() }}</div>
    </div>

    <div class="mt-6 overflow-hidden">
        <div class="px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">ID</th>
                        <th class="py-2 px-3">Invoice</th>
                        <th class="py-2 px-3">Due date</th>
                        <th class="py-2 px-3">Paid date</th>
                        <th class="py-2 px-3">Amount</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
                            $paidAmount = round((float) ($invoice->payments_sum_amount ?? 0), 2, PHP_ROUND_HALF_UP);
                            if (($invoice->status ?? '') === 'paid' && $paidAmount <= 0) {
                                $paidAmount = $invoiceAmount;
                            }
                            $remainingAmount = round(max(0, $invoiceAmount - $paidAmount), 2, PHP_ROUND_HALF_UP);
                            $isPaid = $remainingAmount <= 0.009;
                            $isPartiallyPaid = $paidAmount > 0 && ! $isPaid;

                            $displayStatus = $isPaid ? 'paid' : ($invoice->status ?? 'unpaid');
                            if (! $isPaid && $invoice->due_date && $invoice->due_date->isPast()) {
                                $displayStatus = 'overdue';
                            } elseif (! $isPaid && $displayStatus === 'issued') {
                                $displayStatus = 'unpaid';
                            }

                            $statusKey = $isPartiallyPaid && $displayStatus !== 'overdue' ? 'partial' : $displayStatus;
                            $statusLabel = $isPartiallyPaid
                                ? ($displayStatus === 'overdue' ? 'Partial overdue' : 'Partially paid')
                                : ucfirst(str_replace('_', ' ', (string) $displayStatus));
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3 font-semibold text-slate-700">#{{ $invoice->id }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $invoice->invoice_no }}</td>
                            <td class="py-2 px-3">{{ $invoice->due_date?->format($globalDateFormat) ?? '--' }}</td>
                            <td class="py-2 px-3">
                                @php
                                    $paidDate = $invoice->paid_at;
                                    if (! $paidDate && ! empty($invoice->payments_max_paid_at)) {
                                        try {
                                            $paidDate = \Illuminate\Support\Carbon::parse($invoice->payments_max_paid_at);
                                        } catch (\Throwable $e) {
                                            $paidDate = null;
                                        }
                                    }
                                @endphp
                                {{ $paidDate?->format($globalDateFormat) ?? '--' }}
                            </td>
                            <td class="py-2 px-3 font-semibold text-slate-900">
                                {{ $formatCurrency($invoiceAmount) }}
                                @if($isPartiallyPaid)
                                    <div class="mt-1 text-[11px] font-normal text-slate-500">
                                        Paid: {{ $formatCurrency($paidAmount) }} | Left: {{ $formatCurrency($remainingAmount) }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                <x-status-badge :status="$statusKey" :label="$statusLabel" />
                            </td>
                            <td class="py-2 px-3 text-right">
                                @if(! $isPaid)
                                    <button
                                        type="button"
                                        class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                        data-expense-payment-open
                                        data-expense-payment-action="{{ route('admin.expenses.invoices.pay', $invoice) }}"
                                        data-expense-payment-invoice="{{ $invoice->invoice_no }}"
                                        data-expense-payment-total="{{ number_format($invoiceAmount, 2, '.', '') }}"
                                        data-expense-payment-paid="{{ number_format($paidAmount, 2, '.', '') }}"
                                        data-expense-payment-remaining="{{ number_format($remainingAmount, 2, '.', '') }}"
                                        data-expense-payment-currency="{{ $currencyCode }}"
                                    >
                                        Payment
                                    </button>
                                @else
                                    <span class="text-xs text-slate-400">Paid</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-4 px-3 text-center text-slate-500">No expense invoices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>

    <div id="expensePaymentModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-expense-payment-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="section-label">Record Payment</div>
                    <div id="expensePaymentInvoice" class="text-lg font-semibold text-slate-900">Invoice</div>
                    <div id="expensePaymentSummary" class="text-sm text-slate-500">Total / Paid / Remaining</div>
                </div>
                <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" data-expense-payment-close>Close</button>
            </div>

            <form id="expensePaymentForm" method="POST" action="" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label for="expensePaymentMethod" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                    @php
                        $hasAdvanceMethod = collect($paymentMethods ?? [])->contains(fn ($method) => (string) ($method->code ?? '') === 'advance');
                    @endphp
                    <select id="expensePaymentMethod" name="payment_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        <option value="">Select</option>
                        @unless($hasAdvanceMethod)
                            <option value="advance">Advance</option>
                        @endunless
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="expensePaymentType" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Type</label>
                    <select id="expensePaymentType" name="payment_type" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        <option value="full">Full Payment</option>
                        <option value="partial">Partial Payment</option>
                    </select>
                </div>
                <div>
                    <label for="expensePaymentAmount" class="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                    <input id="expensePaymentAmount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                    <div id="expensePaymentHint" class="mt-1 text-[11px] text-slate-500">Remaining amount</div>
                </div>
                <div>
                    <label for="expensePaymentDate" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Date</label>
                    <input id="expensePaymentDate" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="expensePaymentReference" class="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                    <input id="expensePaymentReference" name="payment_reference" type="text" maxlength="120" placeholder="Txn / note" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="expensePaymentNote" class="text-xs uppercase tracking-[0.2em] text-slate-500">Note</label>
                    <textarea id="expensePaymentNote" name="note" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 md:col-span-2">
                    <button type="button" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" data-expense-payment-close>Cancel</button>
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const root = document.getElementById('appContent');
            const modal = document.getElementById('expensePaymentModal');
            const form = document.getElementById('expensePaymentForm');
            const invoiceEl = document.getElementById('expensePaymentInvoice');
            const summaryEl = document.getElementById('expensePaymentSummary');
            const typeEl = document.getElementById('expensePaymentType');
            const methodEl = document.getElementById('expensePaymentMethod');
            const amountEl = document.getElementById('expensePaymentAmount');
            const hintEl = document.getElementById('expensePaymentHint');

            if (!root || !modal || !form || !invoiceEl || !summaryEl || !typeEl || !methodEl || !amountEl || !hintEl) return;

            let remainingAmount = 0;
            let paidAmount = 0;
            let totalAmount = 0;
            let currency = '';
            const advanceBalance = Number({{ number_format((float) ($advanceBalance ?? 0), 2, '.', '') }});

            const toNumber = (value) => {
                const n = Number.parseFloat(value || '0');
                return Number.isFinite(n) ? n : 0;
            };

            const formatNumber = (value) => Number(value).toFixed(2);

            const syncAmountField = () => {
                const isFull = typeEl.value === 'full';
                amountEl.max = formatNumber(remainingAmount);

                if (isFull) {
                    amountEl.value = formatNumber(remainingAmount);
                    amountEl.readOnly = true;
                } else {
                    if (toNumber(amountEl.value) <= 0 || toNumber(amountEl.value) > remainingAmount) {
                        amountEl.value = formatNumber(remainingAmount);
                    }
                    amountEl.readOnly = false;
                }

                hintEl.textContent = `Paid: ${formatNumber(paidAmount)} ${currency} | Left: ${formatNumber(remainingAmount)} ${currency}`;
            };

            const openModal = (btn) => {
                remainingAmount = toNumber(btn.getAttribute('data-expense-payment-remaining'));
                paidAmount = toNumber(btn.getAttribute('data-expense-payment-paid'));
                totalAmount = toNumber(btn.getAttribute('data-expense-payment-total'));
                currency = btn.getAttribute('data-expense-payment-currency') || '';

                form.setAttribute('action', btn.getAttribute('data-expense-payment-action') || '');
                invoiceEl.textContent = btn.getAttribute('data-expense-payment-invoice') || 'Invoice';
                summaryEl.textContent = `Total: ${formatNumber(totalAmount)} ${currency} | Paid: ${formatNumber(paidAmount)} ${currency} | Remaining: ${formatNumber(remainingAmount)} ${currency} | Advance: ${formatNumber(advanceBalance)} ${currency}`;
                typeEl.value = 'full';
                methodEl.value = advanceBalance > 0 ? 'advance' : '';
                syncAmountField();

                modal.classList.remove('hidden');
                setTimeout(() => amountEl.focus(), 0);
            };

            const closeModal = () => {
                modal.classList.add('hidden');
            };

            root.addEventListener('click', (event) => {
                const openBtn = event.target.closest('[data-expense-payment-open]');
                if (openBtn) {
                    event.preventDefault();
                    openModal(openBtn);
                    return;
                }

                const closeBtn = event.target.closest('[data-expense-payment-close]');
                if (closeBtn && modal.contains(closeBtn)) {
                    event.preventDefault();
                    closeModal();
                }
            });

            typeEl.addEventListener('change', syncAmountField);

            root.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        })();
    </script>
@endsection
