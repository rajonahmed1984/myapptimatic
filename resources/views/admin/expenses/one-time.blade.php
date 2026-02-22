@extends('layouts.admin')

@section('title', 'Add Expense')
@section('page-title', 'Add Expense')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencyCode} {$formatted}";
        };
        $openExpenseModal = $errors->hasAny([
            'category_id',
            'title',
            'amount',
            'expense_date',
            'notes',
            'attachment',
            'generate_invoice',
        ]);
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">New one-time expense</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
            <button type="button" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" data-one-time-open>
                Add expense
            </button>
        </div>
    </div>

    <div class="card p-6">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">One-time expense list</div>
                <div class="text-sm text-slate-500">Latest {{ $oneTimeExpenses->count() }} entries</div>
            </div>
            <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                View all
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-[0.18em] text-slate-400">
                    <tr>
                        <th class="pb-2 pr-3 font-medium">ID</th>
                        <th class="pb-2 pr-3 font-medium">Date</th>
                        <th class="pb-2 pr-3 font-medium">Title</th>
                        <th class="pb-2 pr-3 font-medium">Category</th>
                        <th class="pb-2 pr-3 font-medium text-right">Amount</th>
                        <th class="pb-2 pr-3 font-medium text-right">Paid</th>
                        <th class="pb-2 pr-3 font-medium text-center">Status</th>
                        <th class="pb-2 pr-3 font-medium">Invoice</th>
                        <th class="pb-2 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse($oneTimeExpenses as $expense)
                        @php
                            $invoice = $expense->invoice;
                            $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
                            $paidAmount = round((float) ($invoice->payments_sum_amount ?? 0), 2, PHP_ROUND_HALF_UP);
                            if (($invoice->status ?? '') === 'paid' && $paidAmount <= 0) {
                                $paidAmount = $invoiceAmount;
                            }
                            $remainingAmount = round(max(0, $invoiceAmount - $paidAmount), 2, PHP_ROUND_HALF_UP);
                            $invoicePaid = $invoice && $remainingAmount <= 0.009;
                            $invoicePartiallyPaid = $invoice && $paidAmount > 0 && ! $invoicePaid;
                            $paymentStatus = $invoicePaid ? 'Paid' : ($invoicePartiallyPaid ? 'Partial' : 'Due');
                            $paymentStatusClass = $invoicePaid
                                ? 'bg-emerald-50 text-emerald-700'
                                : ($invoicePartiallyPaid ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                        @endphp
                        <tr>
                            <td class="py-2 pr-3 whitespace-nowrap font-semibold text-slate-900">#{{ $expense->id }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ optional($expense->expense_date)->format('d-M-Y') }}</td>
                            <td class="py-2 pr-3">{{ $expense->title }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $expense->category?->name ?? 'N/A' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap text-right font-semibold text-slate-900">{{ $formatCurrency($expense->amount) }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap text-right font-semibold text-slate-900">{{ $formatCurrency($paidAmount) }}</td>
                            <td class="py-2 pr-3 text-center">
                                <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $paymentStatusClass }}">{{ $paymentStatus }}</span>
                            </td>
                            <td class="py-2 pr-3">
                                @if($invoice)
                                    <div class="font-semibold text-slate-900">{{ $invoice->invoice_no }}</div>
                                    @if($invoicePaid)
                                        <div class="mt-1 text-[11px] font-semibold text-emerald-700">Paid</div>
                                    @elseif($invoicePartiallyPaid)
                                        <div class="mt-1 text-[11px] text-slate-500">
                                            Paid: {{ $formatCurrency($paidAmount) }} | Left: {{ $formatCurrency($remainingAmount) }}
                                        </div>
                                    @else
                                        <div class="mt-1 text-[11px] text-slate-500">Unpaid</div>
                                    @endif
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">Not generated</span>
                                @endif
                            </td>
                            <td class="py-2 text-right">
                                <div class="flex flex-wrap justify-end gap-2 text-xs font-semibold">
                                    @if($invoice)
                                        @if(! $invoicePaid)
                                            <button
                                                type="button"
                                                class="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700 hover:border-emerald-300"
                                                data-expense-payment-open
                                                data-expense-payment-action="{{ route('admin.expenses.invoices.pay', $invoice) }}"
                                                data-expense-payment-invoice="{{ $invoice->invoice_no }}"
                                                data-expense-payment-total="{{ number_format($invoiceAmount, 2, '.', '') }}"
                                                data-expense-payment-paid="{{ number_format($paidAmount, 2, '.', '') }}"
                                                data-expense-payment-remaining="{{ number_format($remainingAmount, 2, '.', '') }}"
                                                data-expense-payment-currency="{{ $currencyCode }}"
                                            >
                                                Add payment
                                            </button>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Paid</span>
                                        @endif
                                    @else
                                        <form method="POST" action="{{ route('admin.expenses.invoices.store') }}">
                                            @csrf
                                            <input type="hidden" name="source_type" value="expense">
                                            <input type="hidden" name="source_id" value="{{ $expense->id }}">
                                            <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700 hover:border-emerald-300">Generate invoice</button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.expenses.edit', $expense) }}" class="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600">Edit</a>

                                    <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this one-time expense?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-full border border-rose-200 px-3 py-1 text-rose-700 hover:border-rose-300">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-sm text-slate-500">No one-time expenses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="oneTimeExpenseModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-one-time-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="section-label">Add Expense</div>
                    <div class="text-lg font-semibold text-slate-900">Create a new one-time expense entry</div>
                </div>
                <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" data-one-time-close>Close</button>
            </div>

            <form method="POST" action="{{ route('admin.expenses.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4 text-sm">
                @csrf
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Category</label>
                        <select name="category_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Title</label>
                        <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @error('title')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="text-xs text-slate-500">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @error('amount')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Expense date</label>
                        <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @error('expense_date')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-500">Notes</label>
                    <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-500">Receipt (jpg/png/pdf)</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block text-xs text-slate-600">
                    @error('attachment')
                        <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="generate_invoice" value="0">
                    <input type="checkbox" name="generate_invoice" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                    <span class="text-xs text-slate-600">Generate expense invoice</span>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Save Expense</button>
                </div>
            </form>
        </div>
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
                    <select id="expensePaymentMethod" name="payment_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        <option value="">Select</option>
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
            const addModal = document.getElementById('oneTimeExpenseModal');
            const modal = document.getElementById('expensePaymentModal');
            const form = document.getElementById('expensePaymentForm');
            const invoiceEl = document.getElementById('expensePaymentInvoice');
            const summaryEl = document.getElementById('expensePaymentSummary');
            const typeEl = document.getElementById('expensePaymentType');
            const amountEl = document.getElementById('expensePaymentAmount');
            const hintEl = document.getElementById('expensePaymentHint');

            if (!root || !modal || !form || !invoiceEl || !summaryEl || !typeEl || !amountEl || !hintEl) return;

            let remainingAmount = 0;
            let paidAmount = 0;
            let totalAmount = 0;
            let currency = '';

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
                summaryEl.textContent = `Total: ${formatNumber(totalAmount)} ${currency} | Paid: ${formatNumber(paidAmount)} ${currency} | Remaining: ${formatNumber(remainingAmount)} ${currency}`;
                typeEl.value = 'full';
                syncAmountField();

                modal.classList.remove('hidden');
                setTimeout(() => amountEl.focus(), 0);
            };

            const closeModal = () => {
                modal.classList.add('hidden');
            };

            const openAddModal = () => {
                if (addModal) {
                    addModal.classList.remove('hidden');
                }
            };

            const closeAddModal = () => {
                if (addModal) {
                    addModal.classList.add('hidden');
                }
            };

            root.addEventListener('click', (event) => {
                const addOpenBtn = event.target.closest('[data-one-time-open]');
                if (addOpenBtn) {
                    event.preventDefault();
                    openAddModal();
                    return;
                }

                const addCloseBtn = event.target.closest('[data-one-time-close]');
                if (addCloseBtn && addModal && addModal.contains(addCloseBtn)) {
                    event.preventDefault();
                    closeAddModal();
                    return;
                }

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
                    closeAddModal();
                }
            });

            if (@json($openExpenseModal)) {
                openAddModal();
            }
        })();
    </script>
@endsection
