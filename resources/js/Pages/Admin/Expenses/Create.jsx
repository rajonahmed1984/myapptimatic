import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { formatDate } from '@/utils/datetime';
import SearchableSelect from '../../../Components/SearchableSelect';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';
import MobileStickyAction from '../../../Components/Mobile/MobileStickyAction';

function formatCurrency(code, amount) {
    const value = Number.parseFloat(amount ?? 0);
    const safe = Number.isFinite(value) ? value : 0;

    return `${code} ${safe.toFixed(2)}`;
}

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500',
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
    danger: 'bg-red-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-red-500',
};

export default function Create({
    pageTitle = 'Add Expense',
    currencyCode = 'BDT',
    categories = [],
    oneTimeExpenses = [],
    paymentMethods = [],
    form = {},
    routes = {},
    pagination = {},
    pagination_links = [],
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
    const categoryOptions = [
        { value: '', label: 'Select category' },
        ...categories.map((category) => ({ value: String(category.id), label: category.name })),
    ];
    const paymentMethodOptions = [
        { value: '', label: 'Select' },
        ...paymentMethods.map((method) => ({ value: String(method.code), label: method.name })),
    ];
    const paymentTypeOptions = [
        { value: 'full', label: 'Full Payment' },
        { value: 'partial', label: 'Partial Payment' },
    ];

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

    const onPaymentTypeChange = (nextType) => {
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
                        className={BTN.secondary}
                    >
                        Back
                    </a>
                    <button
                        type="button"
                        onClick={() => setShowAddModal(true)}
                        className={BTN.primary}
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
                        href={routes?.index_one_time || routes?.index}
                        data-native="true"
                        className={BTN.secondary}
                    >
                        View all
                    </a>
                </div>

                <DataTable
                    rows={oneTimeExpenses}
                    emptyMessage="No one-time expenses yet."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (item) => `#${item.id}` },
                        { key: 'date', header: 'Date', render: (item) => item.date_label || '--' },
                        {
                            key: 'title',
                            header: 'Title / Category',
                            render: (item) => (
                                <>
                                    <div className="font-medium text-slate-900">{item.title}</div>
                                    <div className="mt-1 text-xs text-slate-500">{item.category_name || '--'}</div>
                                </>
                            ),
                        },
                        {
                            key: 'amount',
                            header: 'Amount / Paid',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (item) => (
                                <>
                                    <div className="font-semibold text-slate-900">{formatCurrency(currencyCode, item.amount)}</div>
                                    <div className="mt-1 text-xs text-slate-500">Paid: {formatCurrency(currencyCode, item.invoice?.paid ?? 0)}</div>
                                </>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            headerClassName: 'text-center',
                            cellClassName: 'text-center',
                            render: (item) => <span className={`rounded-full px-2 py-1 text-[11px] font-semibold ${item.invoice?.payment_status_class || 'bg-slate-100 text-slate-500'}`}>{item.invoice?.payment_status || 'Due'}</span>,
                        },
                        {
                            key: 'invoice',
                            header: 'Invoice',
                            render: (item) => (
                                item.invoice ? (
                                    <>
                                        <div className="font-semibold text-slate-900">{item.invoice.invoice_no}</div>
                                        {item.invoice.is_partial ? (
                                            <div className="mt-1 text-[11px] text-slate-500">Paid: {formatCurrency(currencyCode, item.invoice.paid)} | Left: {formatCurrency(currencyCode, item.invoice.remaining)}</div>
                                        ) : item.invoice.is_paid ? (
                                            <div className="mt-1 text-[11px] text-slate-400">Settled in full</div>
                                        ) : (
                                            <div className="mt-1 text-[11px] text-slate-500">Unpaid</div>
                                        )}
                                    </>
                                ) : <span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">Not generated</span>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (item) => (
                                <div className="flex flex-wrap justify-end gap-2 text-xs font-semibold">
                                    {item.invoice ? (
                                        item.invoice.is_paid ? null : (
                                            <button type="button" onClick={() => openPayment(item)} className="text-xs font-semibold text-teal-600 hover:text-teal-500">Add payment</button>
                                        )
                                    ) : (
                                        <form method="POST" action={item.routes?.generate_invoice} data-native="true">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="source_type" value="expense" />
                                            <input type="hidden" name="source_id" value={item.id} />
                                            <button type="submit" className="text-xs font-semibold text-teal-600 hover:text-teal-500">Generate invoice</button>
                                        </form>
                                    )}
                                    <a href={item.routes?.edit} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-teal-600">Edit</a>
                                    <form
                                        method="POST"
                                        action={item.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this one-time expense?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(item) => (
                        <MobileCard
                            title={item.title}
                            subtitle={item.category_name}
                            badge={item.invoice?.payment_status || 'Due'}
                            badgeColor={item.invoice?.payment_status_class}
                            metrics={[
                                { label: 'Amount', value: formatCurrency(currencyCode, item.amount) },
                                { label: 'Paid', value: formatCurrency(currencyCode, item.invoice?.paid ?? 0) },
                            ]}
                            actions={
                                <div className="flex flex-wrap gap-2 w-full">
                                    {item.invoice ? (
                                        item.invoice.is_paid ? null : (
                                            <button
                                                type="button"
                                                onClick={() => openPayment(item)}
                                                className="flex-1 py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                            >
                                                Add payment
                                            </button>
                                        )
                                    ) : (
                                        <form method="POST" action={item.routes?.generate_invoice} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="source_type" value="expense" />
                                            <input type="hidden" name="source_id" value={item.id} />
                                            <button type="submit" className="w-full py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95">Generate invoice</button>
                                        </form>
                                    )}
                                    <a
                                        href={item.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action={item.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this one-time expense?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </div>
                            }
                        >
                            {item.invoice ? (
                                <div className="text-xs text-slate-500">
                                    Invoice {item.invoice.invoice_no}
                                    {item.invoice.is_partial ? ` · Left: ${formatCurrency(currencyCode, item.invoice.remaining)}` : item.invoice.is_paid ? ' · Settled in full' : ' · Unpaid'}
                                </div>
                            ) : (
                                <div className="text-xs text-slate-500">Invoice not generated</div>
                            )}
                        </MobileCard>
                    )}
                />

                {pagination_links.length > 0 ? (
                    <div className="mt-6 flex flex-wrap items-center justify-end gap-2 text-sm">
                        {pagination_links.map((link, index) =>
                            link.url ? (
                                <a
                                    key={`${index}-${link.label}`}
                                    href={link.url}
                                    data-native="true"
                                    className={`rounded-full border px-3 py-1 text-xs ${
                                        link.active
                                            ? 'border-slate-900 bg-slate-900 text-white font-semibold'
                                            : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={`${index}-${link.label}`}
                                    className="rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-300"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>

            <div className={`fixed inset-0 z-50 ${showAddModal ? '' : 'hidden'}`}>
                <div className="absolute inset-0 bg-slate-900/50 md:block" onClick={() => setShowAddModal(false)} />
                <div className="relative flex h-full w-full flex-col overflow-y-auto bg-white p-5 pb-28 shadow-2xl md:mx-auto md:mt-16 md:h-auto md:max-w-3xl md:rounded-2xl md:border md:border-slate-200 md:p-6 md:pb-6">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="section-label">Add Expense</div>
                            <div className="text-lg font-semibold text-slate-900">Create a new one-time expense entry</div>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowAddModal(false)}
                            className={BTN.secondary}
                        >
                            Close
                        </button>
                    </div>

                    <form id="createExpenseForm" method="POST" action={routes?.store} encType="multipart/form-data" className="mt-5 space-y-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <div className="grid gap-3 md:grid-cols-2">
                            <div>
                                <label className="text-xs text-slate-500">Category</label>
                                <SearchableSelect
                                    name="category_id"
                                    required
                                    defaultValue={String(form?.category_id ?? '')}
                                    options={categoryOptions}
                                    className="mt-1"
                                    placeholder="Select category"
                                    error={errors.category_id}
                                />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Title</label>
                                <input
                                    name="title"
                                    defaultValue={form?.title ?? ''}
                                    required
                                    className="mt-1 ui-input"
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
                                    className="mt-1 ui-input"
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
                                    className="mt-1 ui-input"
                                />
                                {errors.expense_date ? <div className="mt-1 text-xs text-rose-600">{errors.expense_date}</div> : null}
                            </div>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Notes</label>
                            <textarea
                                name="notes"
                                rows={1}
                                defaultValue={form?.notes ?? ''}
                                className="mt-1 ui-input"
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

                        <div className="hidden justify-end md:flex">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Save Expense
                            </button>
                        </div>
                    </form>

                    <MobileStickyAction className="md:hidden">
                        <button type="submit" form="createExpenseForm" className="w-full rounded-xl bg-slate-900 py-3 text-sm font-bold text-white active:scale-[0.99] transition">
                            Save Expense
                        </button>
                    </MobileStickyAction>
                </div>
            </div>

            <div className={`fixed inset-0 z-50 ${paymentModal.open ? '' : 'hidden'}`}>
                <div className="absolute inset-0 bg-slate-900/50" onClick={closePayment} />
                <div className="relative flex h-full w-full flex-col overflow-y-auto bg-white p-5 pb-28 shadow-2xl md:mx-auto md:mt-16 md:h-auto md:max-w-lg md:rounded-2xl md:border md:border-slate-200 md:p-6 md:pb-6">
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

                    <form id="recordPaymentForm" method="POST" action={paymentModal.action} className="mt-5 grid gap-4 md:grid-cols-2" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                            <SearchableSelect
                                name="payment_method"
                                defaultValue=""
                                options={paymentMethodOptions}
                                className="mt-1"
                                placeholder="Select"
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Type</label>
                            <SearchableSelect
                                name="payment_type"
                                value={paymentModal.type}
                                onChange={onPaymentTypeChange}
                                options={paymentTypeOptions}
                                className="mt-1"
                                placeholder="Select payment type"
                                required
                            />
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
                                className="mt-1 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
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
                                className="mt-1 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
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
                                className="mt-1 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
                            />
                        </div>
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Note</label>
                            <textarea
                                name="note"
                                rows={1}
                                maxLength={500}
                                className="mt-1 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
                                placeholder="Optional note"
                            />
                        </div>
                        <div className="hidden items-center justify-end gap-3 pt-2 md:col-span-2 md:flex">
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

                    <MobileStickyAction className="md:hidden">
                        <button type="button" onClick={closePayment} className="flex-1 rounded-xl border border-slate-300 py-3 text-sm font-semibold text-slate-700 active:scale-[0.99] transition">
                            Cancel
                        </button>
                        <button type="submit" form="recordPaymentForm" className="flex-1 rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white active:scale-[0.99] transition">
                            Confirm Payment
                        </button>
                    </MobileStickyAction>
                </div>
            </div>
        </>
    );
}
