import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { formatDate } from '@/react/utils/datetime';

function formatCurrency(code, amount) {
    const value = Number.parseFloat(amount ?? 0);
    const safe = Number.isFinite(value) ? value : 0;

    return `${code} ${safe.toFixed(2)}`;
}

export default function Create({
    pageTitle = 'Add Expense',
    currencyCode = 'BDT',
    categories = [],
    oneTimeExpenses = [],
    paymentMethods = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const [showAddModal, setShowAddModal] = React.useState(false);
    const [paymentModal, setPaymentModal] = React.useState({
        open: false,
        action: '',
        invoiceNo: '',
        total: 0,
        paid: 0,
        remaining: 0,
        currency: currencyCode,
        type: 'full',
    });
    const [paymentAmount, setPaymentAmount] = React.useState('0.00');

    React.useEffect(() => {
        const shouldOpen = Boolean(
            errors.category_id ||
                errors.title ||
                errors.amount ||
                errors.expense_date ||
                errors.notes ||
                errors.attachment ||
                errors.generate_invoice,
        );
        setShowAddModal(shouldOpen);
    }, [errors]);

    const openPayment = (item) => {
        const invoice = item?.invoice;
        if (!invoice) return;
        const remaining = Number(invoice.remaining || 0);
        setPaymentModal({
            open: true,
            action: invoice.routes?.pay || '',
            invoiceNo: invoice.invoice_no || 'Invoice',
            total: Number(invoice.total || 0),
            paid: Number(invoice.paid || 0),
            remaining,
            currency: item?.payment_currency || currencyCode,
            type: 'full',
        });
        setPaymentAmount(remaining.toFixed(2));
    };

    const closePayment = () => setPaymentModal((current) => ({ ...current, open: false }));

    const onPaymentTypeChange = (event) => {
        const nextType = event.target.value;
        const remaining = Number(paymentModal.remaining || 0);
        setPaymentModal((current) => ({ ...current, type: nextType }));
        if (nextType === 'full') {
            setPaymentAmount(remaining.toFixed(2));
            return;
        }
        const currentValue = Number(paymentAmount || 0);
        if (!Number.isFinite(currentValue) || currentValue <= 0 || currentValue > remaining) {
            setPaymentAmount(remaining.toFixed(2));
        }
    };

    const onPaymentAmountChange = (event) => {
        const value = event.target.value;
        if (paymentModal.type === 'full') {
            setPaymentAmount(Number(paymentModal.remaining || 0).toFixed(2));
            return;
        }
        setPaymentAmount(value);
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">New one-time expense</div>
                </div>
                <div className="flex items-center gap-3">
                    <a
                        href={routes?.index}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Back
                    </a>
                    <button
                        type="button"
                        onClick={() => setShowAddModal(true)}
                        className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Add expense
                    </button>
                </div>
            </div>

            <div className="card p-6">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">One-time expense list</div>
                        <div className="text-sm text-slate-500">Latest {oneTimeExpenses.length} entries</div>
                    </div>
                    <a
                        href={routes?.index}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        View all
                    </a>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-xs uppercase tracking-[0.18em] text-slate-400">
                            <tr>
                                <th className="pb-2 pr-3 font-medium">ID</th>
                                <th className="pb-2 pr-3 font-medium">Date</th>
                                <th className="pb-2 pr-3 font-medium">Title</th>
                                <th className="pb-2 pr-3 font-medium">Category</th>
                                <th className="pb-2 pr-3 text-right font-medium">Amount</th>
                                <th className="pb-2 pr-3 text-right font-medium">Paid</th>
                                <th className="pb-2 pr-3 text-center font-medium">Status</th>
                                <th className="pb-2 pr-3 font-medium">Invoice</th>
                                <th className="pb-2 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {oneTimeExpenses.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="py-6 text-center text-sm text-slate-500">
                                        No one-time expenses yet.
                                    </td>
                                </tr>
                            ) : (
                                oneTimeExpenses.map((item) => (
                                    <tr key={item.id}>
                                        <td className="whitespace-nowrap py-2 pr-3 font-semibold text-slate-900">#{item.id}</td>
                                        <td className="whitespace-nowrap py-2 pr-3">{item.date_label || '--'}</td>
                                        <td className="py-2 pr-3">{item.title}</td>
                                        <td className="whitespace-nowrap py-2 pr-3">{item.category_name}</td>
                                        <td className="whitespace-nowrap py-2 pr-3 text-right font-semibold text-slate-900">
                                            {formatCurrency(currencyCode, item.amount)}
                                        </td>
                                        <td className="whitespace-nowrap py-2 pr-3 text-right font-semibold text-slate-900">
                                            {formatCurrency(currencyCode, item.invoice?.paid ?? 0)}
                                        </td>
                                        <td className="py-2 pr-3 text-center">
                                            <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${item.invoice?.payment_status_class || 'bg-slate-100 text-slate-500'}`}>
                                                {item.invoice?.payment_status || 'Due'}
                                            </span>
                                        </td>
                                        <td className="py-2 pr-3">
                                            {item.invoice ? (
                                                <>
                                                    <div className="font-semibold text-slate-900">{item.invoice.invoice_no}</div>
                                                    {item.invoice.is_paid ? (
                                                        <div className="mt-1 text-[11px] font-semibold text-emerald-700">Paid</div>
                                                    ) : item.invoice.is_partial ? (
                                                        <div className="mt-1 text-[11px] text-slate-500">
                                                            Paid: {formatCurrency(currencyCode, item.invoice.paid)} | Left:{' '}
                                                            {formatCurrency(currencyCode, item.invoice.remaining)}
                                                        </div>
                                                    ) : (
                                                        <div className="mt-1 text-[11px] text-slate-500">Unpaid</div>
                                                    )}
                                                </>
                                            ) : (
                                                <span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">Not generated</span>
                                            )}
                                        </td>
                                        <td className="py-2 text-right">
                                            <div className="flex flex-wrap justify-end gap-2 text-xs font-semibold">
                                                {item.invoice ? (
                                                    item.invoice.is_paid ? (
                                                        <span className="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Paid</span>
                                                    ) : (
                                                        <button
                                                            type="button"
                                                            onClick={() => openPayment(item)}
                                                            className="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700 hover:border-emerald-300"
                                                        >
                                                            Add payment
                                                        </button>
                                                    )
                                                ) : (
                                                    <form method="POST" action={item.routes?.generate_invoice} data-native="true">
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="source_type" value="expense" />
                                                        <input type="hidden" name="source_id" value={item.id} />
                                                        <button
                                                            type="submit"
                                                            className="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700 hover:border-emerald-300"
                                                        >
                                                            Generate invoice
                                                        </button>
                                                    </form>
                                                )}

                                                <a
                                                    href={item.routes?.edit}
                                                    data-native="true"
                                                    className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                                >
                                                    Edit
                                                </a>

                                                <form
                                                    method="POST"
                                                    action={item.routes?.destroy}
                                                    data-native="true"
                                                    onSubmit={(event) => {
                                                        if (!window.confirm('Delete this one-time expense?')) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="_token" value={csrf} />
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <button
                                                        type="submit"
                                                        className="rounded-full border border-rose-200 px-3 py-1 text-rose-700 hover:border-rose-300"
                                                    >
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className={`fixed inset-0 z-50 ${showAddModal ? '' : 'hidden'}`}>
                <div className="absolute inset-0 bg-slate-900/50" onClick={() => setShowAddModal(false)} />
                <div className="relative mx-auto mt-16 w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="section-label">Add Expense</div>
                            <div className="text-lg font-semibold text-slate-900">Create a new one-time expense entry</div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowAddModal(false)}
                            className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                        >
                            Close
                        </button>
                    </div>

                    <form method="POST" action={routes?.store} encType="multipart/form-data" className="mt-5 space-y-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Category</label>
                                <select
                                    name="category_id"
                                    required
                                    defaultValue={form?.category_id ?? ''}
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="">Select category</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.category_id ? <div className="mt-1 text-xs text-rose-600">{errors.category_id}</div> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Title</label>
                                <input
                                    name="title"
                                    defaultValue={form?.title ?? ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                                {errors.title ? <div className="mt-1 text-xs text-rose-600">{errors.title}</div> : null}
                            </div>
                        </div>

                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Amount</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="amount"
                                    defaultValue={form?.amount ?? ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                                {errors.amount ? <div className="mt-1 text-xs text-rose-600">{errors.amount}</div> : null}
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Expense date</label>
                                <input
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    name="expense_date"
                                    defaultValue={form?.expense_date ?? ''}
                                    required
                                    className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                />
                                {errors.expense_date ? <div className="mt-1 text-xs text-rose-600">{errors.expense_date}</div> : null}
                            </div>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Notes</label>
                            <textarea
                                name="notes"
                                rows={3}
                                defaultValue={form?.notes ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors.notes ? <div className="mt-1 text-xs text-rose-600">{errors.notes}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Receipt (jpg/png/pdf)</label>
                            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" className="mt-1 block text-xs text-slate-600" />
                            {errors.attachment ? <div className="mt-1 text-xs text-rose-600">{errors.attachment}</div> : null}
                        </div>

                        <div className="flex items-center gap-2">
                            <input type="hidden" name="generate_invoice" value="0" />
                            <input
                                type="checkbox"
                                name="generate_invoice"
                                value="1"
                                defaultChecked={Boolean(form?.generate_invoice)}
                                className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            />
                            <span className="text-xs text-slate-600">Generate expense invoice</span>
                        </div>

                        <div className="flex justify-end">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Save Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div className={`fixed inset-0 z-50 ${paymentModal.open ? '' : 'hidden'}`}>
                <div className="absolute inset-0 bg-slate-900/50" onClick={closePayment} />
                <div className="relative mx-auto mt-16 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="section-label">Record Payment</div>
                            <div className="text-lg font-semibold text-slate-900">{paymentModal.invoiceNo}</div>
                            <div className="text-sm text-slate-500">
                                Total: {paymentModal.total.toFixed(2)} {paymentModal.currency} | Paid: {paymentModal.paid.toFixed(2)} {paymentModal.currency} |
                                Remaining: {paymentModal.remaining.toFixed(2)} {paymentModal.currency}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={closePayment}
                            className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                        >
                            Close
                        </button>
                    </div>

                    <form method="POST" action={paymentModal.action} className="mt-5 grid gap-4 md:grid-cols-2" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                            <select name="payment_method" className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required>
                                <option value="">Select</option>
                                {paymentMethods.map((method) => (
                                    <option key={method.code} value={method.code}>
                                        {method.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Type</label>
                            <select
                                name="payment_type"
                                value={paymentModal.type}
                                onChange={onPaymentTypeChange}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                required
                            >
                                <option value="full">Full Payment</option>
                                <option value="partial">Partial Payment</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                            <input
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={paymentModal.remaining.toFixed(2)}
                                value={paymentAmount}
                                onChange={onPaymentAmountChange}
                                readOnly={paymentModal.type === 'full'}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                required
                            />
                            <div className="mt-1 text-[11px] text-slate-500">
                                Paid: {paymentModal.paid.toFixed(2)} {paymentModal.currency} | Left: {paymentModal.remaining.toFixed(2)} {paymentModal.currency}
                            </div>
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="paid_at"
                                defaultValue={formatDate(new Date())}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                            <input
                                name="payment_reference"
                                type="text"
                                maxLength={120}
                                placeholder="Txn / note"
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Note</label>
                            <textarea
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
                                onClick={closePayment}
                                className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                            >
                                Cancel
                            </button>
                            <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                Confirm Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
