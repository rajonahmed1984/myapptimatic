@extends('layouts.admin')

@section('title', 'Recurring Expenses')
@section('page-title', 'Recurring Expenses')

@section('content')
    @php
        $formatCurrency = function ($amount) use ($currencySymbol, $currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);
            return "{$currencySymbol}{$formatted} {$currencyCode}";
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Finance</div>
            <div class="text-2xl font-semibold text-slate-900">Recurring expenses</div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.expenses.recurring.create') }}" class="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Add recurring</a>
            <a href="{{ route('admin.expenses.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Back</a>
        </div>
    </div>

    <div class="overflow-hidden">
        <div class="px-3 py-3 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
            <table class="min-w-full text-sm text-slate-700">
                <thead>
                    <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                        <th class="py-2 px-3">ID</th>
                        <th class="py-2 px-3">Title</th>
                        <th class="py-2 px-3">Category</th>
                        <th class="py-2 px-3">Amount</th>
                        <th class="py-2 px-3">Advance</th>
                        <th class="py-2 px-3">Recurrence</th>
                        <th class="py-2 px-3">Next due date</th>
                        <th class="py-2 px-3">Status</th>
                        <th class="py-2 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recurringExpenses as $recurring)
                        <tr class="border-b border-slate-100">
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $recurring->id }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">
                                <a href="{{ route('admin.expenses.recurring.show', $recurring) }}" class="hover:text-teal-600">
                                    {{ $recurring->title }}
                                </a>
                            </td>
                            <td class="py-2 px-3">{{ $recurring->category?->name ?? '--' }}</td>
                            <td class="py-2 px-3">{{ number_format($recurring->amount, 2) }}</td>
                            <td class="py-2 px-3 font-semibold text-slate-900">{{ $formatCurrency($recurring->advances_sum_amount ?? 0) }}</td>
                            <td class="py-2 px-3">
                                Every {{ $recurring->recurrence_interval }} {{ $recurring->recurrence_type === 'yearly' ? 'year(s)' : 'month(s)' }}
                            </td>
                            <td class="py-2 px-3 whitespace-nowrap">
                                @if(! empty($recurring->next_due_date))
                                    {{ \Illuminate\Support\Carbon::parse($recurring->next_due_date)->format($globalDateFormat) }}
                                @else
                                    {{ $recurring->next_run_date?->format($globalDateFormat) ?? '--' }}
                                @endif
                            </td>
                            <td class="py-2 px-3">
                                <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $recurring->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($recurring->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-300 text-slate-600 bg-slate-50') }}">
                                    {{ ucfirst($recurring->status) }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-right">
                                <div class="flex justify-end gap-3 text-xs font-semibold">
                                    <button
                                        type="button"
                                        class="text-emerald-600 hover:text-emerald-500"
                                        data-advance-open
                                        data-advance-action="{{ route('admin.expenses.recurring.advance.store', $recurring) }}"
                                        data-advance-title="{{ $recurring->title }}"
                                        data-advance-current="{{ number_format((float) ($recurring->advances_sum_amount ?? 0), 2, '.', '') }}"
                                        data-advance-currency="{{ $currencyCode }}"
                                    >
                                        Advance
                                    </button>
                                    <a href="{{ route('admin.expenses.recurring.edit', $recurring) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                    @if($recurring->status === 'paused')
                                        <form method="POST" action="{{ route('admin.expenses.recurring.resume', $recurring) }}">
                                            @csrf
                                            <button type="submit" class="text-emerald-600 hover:text-emerald-500">Resume</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.expenses.recurring.stop', $recurring) }}">
                                        @csrf
                                        <button type="submit" class="text-rose-600 hover:text-rose-500">Stop</button>
                                    </form>
                                    <a href="{{ route('admin.expenses.recurring.show', $recurring) }}" class="text-slate-600 hover:text-teal-600">View</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-4 px-3 text-center text-slate-500">No recurring expenses yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $recurringExpenses->links() }}</div>
    </div>

    <div id="recurringAdvanceModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-advance-close></div>
        <div class="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="section-label">Advance Payment</div>
                    <div id="recurringAdvanceTitle" class="text-lg font-semibold text-slate-900">Recurring Expense</div>
                    <div id="recurringAdvanceSummary" class="text-sm text-slate-500">Current advance</div>
                </div>
                <button type="button" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900" data-advance-close>Close</button>
            </div>

            <form id="recurringAdvanceForm" method="POST" action="" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label for="recurringAdvanceMethod" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                    <select id="recurringAdvanceMethod" name="payment_method" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                        <option value="">Select</option>
                        @foreach($paymentMethods as $method)
                            <option value="{{ $method->code }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="recurringAdvanceDate" class="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Date</label>
                    <input id="recurringAdvanceDate" type="date" name="paid_at" value="{{ now()->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="recurringAdvanceAmount" class="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                    <input id="recurringAdvanceAmount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label for="recurringAdvanceReference" class="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                    <input id="recurringAdvanceReference" name="payment_reference" type="text" maxlength="120" placeholder="Txn / note" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label for="recurringAdvanceNote" class="text-xs uppercase tracking-[0.2em] text-slate-500">Note</label>
                    <textarea id="recurringAdvanceNote" name="note" rows="3" maxlength="500" class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="Optional note"></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2 md:col-span-2">
                    <button type="button" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" data-advance-close>Cancel</button>
                    <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Advance</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const root = document.getElementById('appContent');
            const modal = document.getElementById('recurringAdvanceModal');
            const form = document.getElementById('recurringAdvanceForm');
            const titleEl = document.getElementById('recurringAdvanceTitle');
            const summaryEl = document.getElementById('recurringAdvanceSummary');
            const amountEl = document.getElementById('recurringAdvanceAmount');

            if (!root || !modal || !form || !titleEl || !summaryEl || !amountEl) return;

            const toNumber = (value) => {
                const n = Number.parseFloat(value || '0');
                return Number.isFinite(n) ? n : 0;
            };

            const formatNumber = (value) => Number(value).toFixed(2);

            const openModal = (btn) => {
                const action = btn.getAttribute('data-advance-action') || '';
                const title = btn.getAttribute('data-advance-title') || 'Recurring Expense';
                const currentAdvance = toNumber(btn.getAttribute('data-advance-current'));
                const currency = btn.getAttribute('data-advance-currency') || '';

                form.setAttribute('action', action);
                titleEl.textContent = title;
                summaryEl.textContent = `Current advance: ${formatNumber(currentAdvance)} ${currency}`;
                amountEl.value = '';

                modal.classList.remove('hidden');
                setTimeout(() => amountEl.focus(), 0);
            };

            const closeModal = () => {
                modal.classList.add('hidden');
            };

            root.addEventListener('click', (event) => {
                const openBtn = event.target.closest('[data-advance-open]');
                if (openBtn) {
                    event.preventDefault();
                    openModal(openBtn);
                    return;
                }

                const closeBtn = event.target.closest('[data-advance-close]');
                if (closeBtn && modal.contains(closeBtn)) {
                    event.preventDefault();
                    closeModal();
                }
            });

            root.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeModal();
                }
            });
        })();
    </script>
@endsection
