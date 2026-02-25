import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { formatDate } from '@/react/utils/datetime';

const statusClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 text-emerald-700 bg-emerald-50';
    }

    if (status === 'paused') {
        return 'border-amber-200 text-amber-700 bg-amber-50';
    }

    return 'border-slate-300 text-slate-600 bg-slate-50';
};

const formatCurrency = (amount, symbol, code) => {
    const numeric = Number.parseFloat(String(amount ?? 0));
    const safe = Number.isFinite(numeric) ? numeric : 0;

    return `${symbol}${safe.toFixed(2)} ${code}`;
};

export default function Index({
    pageTitle,
    recurringExpenses = { data: [], links: [] },
    paymentMethods = [],
    currency = { symbol: '', code: '' },
    routes = {},
}) {
    const { csrf_token: csrfToken } = usePage().props;
    const [advanceModal, setAdvanceModal] = useState({
        open: false,
        action: '',
        title: 'Recurring Expense',
        currentAdvance: '0.00',
    });

    const hasRows = useMemo(() => (recurringExpenses?.data ?? []).length > 0, [recurringExpenses]);

    useEffect(() => {
        if (!advanceModal.open) {
            return undefined;
        }

        const onEscape = (event) => {
            if (event.key === 'Escape') {
                setAdvanceModal((prev) => ({ ...prev, open: false }));
            }
        };

        window.addEventListener('keydown', onEscape);

        return () => {
            window.removeEventListener('keydown', onEscape);
        };
    }, [advanceModal.open]);

    return (
        <>
            <Head title={pageTitle ?? 'Recurring Expenses'} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">Recurring expenses</div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <a
                        href={routes?.create}
                        data-native="true"
                        className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Add recurring
                    </a>
                    <a
                        href={routes?.back}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Back
                    </a>
                </div>
            </div>

            <div className="overflow-hidden">
                <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white/80 px-3 py-3">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">ID</th>
                                <th className="px-3 py-2">Title</th>
                                <th className="px-3 py-2">Category</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Advance</th>
                                <th className="px-3 py-2">Recurrence</th>
                                <th className="px-3 py-2">Next due date</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {hasRows ? (
                                recurringExpenses.data.map((recurring) => (
                                    <tr key={recurring.id} className="border-b border-slate-100">
                                        <td className="px-3 py-2 font-semibold text-slate-900">{recurring.id}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">
                                            <a
                                                href={recurring.routes?.show}
                                                data-native="true"
                                                className="hover:text-teal-600"
                                            >
                                                {recurring.title}
                                            </a>
                                        </td>
                                        <td className="px-3 py-2">{recurring.category_name || '--'}</td>
                                        <td className="px-3 py-2">{Number(recurring.amount ?? 0).toFixed(2)}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">
                                            {formatCurrency(recurring.advance_amount, currency?.symbol, currency?.code)}
                                        </td>
                                        <td className="px-3 py-2">
                                            Every {recurring.recurrence_interval}{' '}
                                            {recurring.recurrence_type === 'yearly' ? 'year(s)' : 'month(s)'}
                                        </td>
                                        <td className="whitespace-nowrap px-3 py-2">{recurring.next_due_display || '--'}</td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(recurring.status)}`}
                                            >
                                                {String(recurring.status || '').charAt(0).toUpperCase() + String(recurring.status || '').slice(1)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex justify-end gap-3 text-xs font-semibold">
                                                <button
                                                    type="button"
                                                    className="text-emerald-600 hover:text-emerald-500"
                                                    onClick={() =>
                                                        setAdvanceModal({
                                                            open: true,
                                                            action: recurring.routes?.advance_store || '',
                                                            title: recurring.title || 'Recurring Expense',
                                                            currentAdvance: Number(recurring.advance_amount ?? 0).toFixed(2),
                                                        })
                                                    }
                                                >
                                                    Advance
                                                </button>
                                                <a
                                                    href={recurring.routes?.edit}
                                                    data-native="true"
                                                    className="text-teal-600 hover:text-teal-500"
                                                >
                                                    Edit
                                                </a>
                                                {recurring.can_resume ? (
                                                    <form method="POST" action={recurring.routes?.resume} data-native="true">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="text-emerald-600 hover:text-emerald-500">
                                                            Resume
                                                        </button>
                                                    </form>
                                                ) : null}
                                                <form method="POST" action={recurring.routes?.stop} data-native="true">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                        Stop
                                                    </button>
                                                </form>
                                                <a
                                                    href={recurring.routes?.show}
                                                    data-native="true"
                                                    className="text-slate-600 hover:text-teal-600"
                                                >
                                                    View
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={9} className="px-3 py-4 text-center text-slate-500">
                                        No recurring expenses yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    {(recurringExpenses?.links ?? []).map((link, idx) =>
                        link.url ? (
                            <a
                                key={`${idx}-${link.label}`}
                                href={link.url}
                                data-native="true"
                                className={`rounded-full border px-3 py-1 ${
                                    link.active
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={`${idx}-${link.label}`}
                                className="rounded-full border border-slate-200 px-3 py-1 text-slate-300"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </div>
            </div>

            {advanceModal.open ? (
                <div className="fixed inset-0 z-50">
                    <div
                        className="absolute inset-0 bg-slate-900/50"
                        onClick={() => setAdvanceModal((prev) => ({ ...prev, open: false }))}
                    />
                    <div className="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="section-label">Advance Payment</div>
                                <div className="text-lg font-semibold text-slate-900">{advanceModal.title}</div>
                                <div className="text-sm text-slate-500">
                                    Current advance: {advanceModal.currentAdvance} {currency?.code}
                                </div>
                            </div>
                            <button
                                type="button"
                                className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                                onClick={() => setAdvanceModal((prev) => ({ ...prev, open: false }))}
                            >
                                Close
                            </button>
                        </div>

                        <form method="POST" action={advanceModal.action} className="mt-5 grid gap-4 md:grid-cols-2" data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div>
                                <label htmlFor="recurringAdvanceMethod" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Payment Method
                                </label>
                                <select
                                    id="recurringAdvanceMethod"
                                    name="payment_method"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    defaultValue=""
                                    required
                                >
                                    <option value="">Select</option>
                                    {paymentMethods.map((method) => (
                                        <option key={method.code} value={method.code}>
                                            {method.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label htmlFor="recurringAdvanceDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Payment Date
                                </label>
                                <input
                                    id="recurringAdvanceDate"
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    name="paid_at"
                                    defaultValue={formatDate(new Date())}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label htmlFor="recurringAdvanceAmount" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Amount
                                </label>
                                <input
                                    id="recurringAdvanceAmount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label htmlFor="recurringAdvanceReference" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Reference
                                </label>
                                <input
                                    id="recurringAdvanceReference"
                                    name="payment_reference"
                                    type="text"
                                    maxLength={120}
                                    placeholder="Txn / note"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label htmlFor="recurringAdvanceNote" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Note
                                </label>
                                <textarea
                                    id="recurringAdvanceNote"
                                    name="note"
                                    rows={3}
                                    maxLength={500}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    placeholder="Optional note"
                                />
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2 md:col-span-2">
                                <button
                                    type="button"
                                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                    onClick={() => setAdvanceModal((prev) => ({ ...prev, open: false }))}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                                >
                                    Save Advance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}
