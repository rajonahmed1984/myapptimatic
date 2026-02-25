import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { formatDate } from '@/react/utils/datetime';

const formatCurrency = (amount, symbol, code) => {
    const numeric = Number.parseFloat(String(amount ?? 0));
    const safe = Number.isFinite(numeric) ? numeric : 0;

    return `${symbol}${safe.toFixed(2)} ${code}`;
};

const statusClass = (status) => {
    if (status === 'paid') {
        return 'border-emerald-200 text-emerald-700 bg-emerald-50';
    }

    if (status === 'overdue') {
        return 'border-rose-200 text-rose-700 bg-rose-50';
    }

    if (status === 'partial') {
        return 'border-sky-200 text-sky-700 bg-sky-50';
    }

    return 'border-amber-200 text-amber-700 bg-amber-50';
};

const Pagination = ({ links }) => (
    <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
        {(links ?? []).map((link, idx) =>
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
);

export default function Show({
    pageTitle = 'Recurring Expense',
    recurringExpense = {},
    stats = {},
    currency = { symbol: '', code: '' },
    routes = {},
    paymentMethods = [],
    advances = { data: [], links: [] },
    invoices = { data: [], links: [] },
}) {
    const { csrf_token: csrfToken } = usePage().props;
    const [paymentModal, setPaymentModal] = useState({
        open: false,
        action: '',
        invoiceNo: '',
        total: 0,
        paid: 0,
        remaining: 0,
        paymentType: 'full',
        paymentMethod: '',
        amount: '',
    });

    const hasAdvanceMethod = useMemo(
        () => paymentMethods.some((method) => String(method.code) === 'advance'),
        [paymentMethods],
    );
    const advanceBalance = Number(stats?.advance_balance ?? 0);

    useEffect(() => {
        if (!paymentModal.open) {
            return undefined;
        }

        const onEscape = (event) => {
            if (event.key === 'Escape') {
                setPaymentModal((prev) => ({ ...prev, open: false }));
            }
        };

        window.addEventListener('keydown', onEscape);

        return () => {
            window.removeEventListener('keydown', onEscape);
        };
    }, [paymentModal.open]);

    const onOpenPayment = (invoice) => {
        const remaining = Number(invoice.remaining_amount ?? 0);

        setPaymentModal({
            open: true,
            action: invoice.routes?.pay || '',
            invoiceNo: invoice.invoice_no || '',
            total: Number(invoice.amount ?? 0),
            paid: Number(invoice.paid_amount ?? 0),
            remaining,
            paymentType: 'full',
            paymentMethod: advanceBalance > 0 ? 'advance' : '',
            amount: remaining.toFixed(2),
        });
    };

    const onPaymentTypeChange = (nextType) => {
        setPaymentModal((prev) => ({
            ...prev,
            paymentType: nextType,
            amount: nextType === 'full' ? prev.remaining.toFixed(2) : prev.amount || prev.remaining.toFixed(2),
        }));
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Recurring Expense</div>
                    <div className="text-2xl font-semibold text-slate-900">{recurringExpense.title}</div>
                    <div className="mt-1 text-sm text-slate-500">
                        {recurringExpense.category_name} | {formatCurrency(recurringExpense.amount, currency.symbol, currency.code)} | Every{' '}
                        {recurringExpense.recurrence_interval}{' '}
                        {recurringExpense.recurrence_type === 'yearly' ? 'year(s)' : 'month(s)'}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <a
                        href={routes.edit}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Edit
                    </a>
                    <a
                        href={routes.back}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Back
                    </a>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Next due date</div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">{recurringExpense.next_run_display || '--'}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Next due</div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">{stats.next_due_display || '--'}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total invoices</div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">{stats.total_invoices ?? 0}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Unpaid</div>
                    <div className="mt-2 text-lg font-semibold text-amber-600">{stats.unpaid_count ?? 0}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Overdue</div>
                    <div className="mt-2 text-lg font-semibold text-rose-600">{stats.overdue_count ?? 0}</div>
                </div>
                <div className="card px-4 py-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Advance balance</div>
                    <div className="mt-2 text-lg font-semibold text-emerald-600">
                        {formatCurrency(stats.advance_balance, currency.symbol, currency.code)}
                    </div>
                    <div className="mt-1 text-[11px] text-slate-500">
                        Total: {formatCurrency(stats.advance_total, currency.symbol, currency.code)} | Used:{' '}
                        {formatCurrency(stats.advance_used, currency.symbol, currency.code)}
                    </div>
                </div>
            </div>

            <div className="mt-6 overflow-hidden">
                <div className="mb-2 section-label">Advance Details</div>
                <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white/80 px-3 py-3">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Method</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Reference</th>
                                <th className="px-3 py-2">Note</th>
                                <th className="px-3 py-2">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(advances.data ?? []).length > 0 ? (
                                advances.data.map((advance) => (
                                    <tr key={advance.id} className="border-b border-slate-100">
                                        <td className="px-3 py-2">{advance.paid_at_display}</td>
                                        <td className="px-3 py-2">{advance.payment_method}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">
                                            {formatCurrency(advance.amount, currency.symbol, currency.code)}
                                        </td>
                                        <td className="px-3 py-2">{advance.payment_reference}</td>
                                        <td className="px-3 py-2">{advance.note}</td>
                                        <td className="px-3 py-2">{advance.creator_name}</td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={6} className="px-3 py-4 text-center text-slate-500">
                                        No advance payment yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={advances.links} />
            </div>

            <div className="mt-6 overflow-hidden">
                <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white/80 px-3 py-3">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">ID</th>
                                <th className="px-3 py-2">Invoice</th>
                                <th className="px-3 py-2">Due date</th>
                                <th className="px-3 py-2">Paid date</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(invoices.data ?? []).length > 0 ? (
                                invoices.data.map((invoice) => (
                                    <tr key={invoice.id} className="border-b border-slate-100">
                                        <td className="px-3 py-2 font-semibold text-slate-700">#{invoice.id}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">{invoice.invoice_no}</td>
                                        <td className="px-3 py-2">{invoice.due_date_display}</td>
                                        <td className="px-3 py-2">{invoice.paid_date_display}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">
                                            {formatCurrency(invoice.amount, currency.symbol, currency.code)}
                                            {invoice.is_partially_paid ? (
                                                <div className="mt-1 text-[11px] font-normal text-slate-500">
                                                    Paid: {formatCurrency(invoice.paid_amount, currency.symbol, currency.code)} | Left:{' '}
                                                    {formatCurrency(invoice.remaining_amount, currency.symbol, currency.code)}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(invoice.status)}`}>
                                                {invoice.status_label}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {!invoice.is_paid ? (
                                                <button
                                                    type="button"
                                                    className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                    onClick={() => onOpenPayment(invoice)}
                                                >
                                                    Payment
                                                </button>
                                            ) : (
                                                <span className="text-xs text-slate-400">Paid</span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={7} className="px-3 py-4 text-center text-slate-500">
                                        No expense invoices found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={invoices.links} />
            </div>

            {paymentModal.open ? (
                <div className="fixed inset-0 z-50">
                    <div
                        className="absolute inset-0 bg-slate-900/50"
                        onClick={() => setPaymentModal((prev) => ({ ...prev, open: false }))}
                    />
                    <div className="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <div className="section-label">Record Payment</div>
                                <div className="text-lg font-semibold text-slate-900">{paymentModal.invoiceNo || 'Invoice'}</div>
                                <div className="text-sm text-slate-500">
                                    Total: {paymentModal.total.toFixed(2)} {currency.code} | Paid: {paymentModal.paid.toFixed(2)} {currency.code} | Remaining:{' '}
                                    {paymentModal.remaining.toFixed(2)} {currency.code} | Advance: {advanceBalance.toFixed(2)} {currency.code}
                                </div>
                            </div>
                            <button
                                type="button"
                                className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                                onClick={() => setPaymentModal((prev) => ({ ...prev, open: false }))}
                            >
                                Close
                            </button>
                        </div>

                        <form method="POST" action={paymentModal.action} className="mt-5 grid gap-4 md:grid-cols-2" data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div>
                                <label htmlFor="expensePaymentMethod" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Payment Method
                                </label>
                                <select
                                    id="expensePaymentMethod"
                                    name="payment_method"
                                    value={paymentModal.paymentMethod}
                                    onChange={(event) => setPaymentModal((prev) => ({ ...prev, paymentMethod: event.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">Select</option>
                                    {!hasAdvanceMethod ? <option value="advance">Advance</option> : null}
                                    {paymentMethods.map((method) => (
                                        <option key={method.code} value={method.code}>
                                            {method.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label htmlFor="expensePaymentType" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Payment Type
                                </label>
                                <select
                                    id="expensePaymentType"
                                    name="payment_type"
                                    value={paymentModal.paymentType}
                                    onChange={(event) => onPaymentTypeChange(event.target.value)}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="full">Full Payment</option>
                                    <option value="partial">Partial Payment</option>
                                </select>
                            </div>
                            <div>
                                <label htmlFor="expensePaymentAmount" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Amount
                                </label>
                                <input
                                    id="expensePaymentAmount"
                                    name="amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    max={paymentModal.remaining.toFixed(2)}
                                    value={paymentModal.amount}
                                    readOnly={paymentModal.paymentType === 'full'}
                                    onChange={(event) => setPaymentModal((prev) => ({ ...prev, amount: event.target.value }))}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                />
                                <div className="mt-1 text-[11px] text-slate-500">
                                    Paid: {paymentModal.paid.toFixed(2)} {currency.code} | Left: {paymentModal.remaining.toFixed(2)} {currency.code}
                                </div>
                            </div>
                            <div>
                                <label htmlFor="expensePaymentDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Payment Date
                                </label>
                                <input
                                    id="expensePaymentDate"
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    name="paid_at"
                                    defaultValue={formatDate(new Date())}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label htmlFor="expensePaymentReference" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Reference
                                </label>
                                <input
                                    id="expensePaymentReference"
                                    name="payment_reference"
                                    type="text"
                                    maxLength={120}
                                    placeholder="Txn / note"
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label htmlFor="expensePaymentNote" className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    Note
                                </label>
                                <textarea
                                    id="expensePaymentNote"
                                    name="note"
                                    rows={2}
                                    maxLength={500}
                                    className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                    placeholder="Optional note"
                                />
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2 md:col-span-2">
                                <button
                                    type="button"
                                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                    onClick={() => setPaymentModal((prev) => ({ ...prev, open: false }))}
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                                >
                                    Confirm Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            ) : null}
        </>
    );
}
