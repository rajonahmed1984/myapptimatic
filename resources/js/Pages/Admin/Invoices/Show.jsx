import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const tabs = [
    { key: 'summary', label: 'Summary' },
    { key: 'add_payment', label: 'Add Payment' },
    { key: 'options', label: 'Options' },
    { key: 'credit', label: 'Credit' },
    { key: 'refund', label: 'Refund' },
    { key: 'notes', label: 'Notes' },
];

const statusTextClass = (status) => {
    if (status === 'unpaid' || status === 'overdue') {
        return 'text-rose-700';
    }

    if (status === 'paid') {
        return 'text-emerald-700';
    }

    return 'text-slate-600';
};

const rowButtonClass = 'rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600';
const actionPrimaryClass = 'rounded-md bg-[#2e6da4] px-4 py-2 text-sm font-semibold text-white hover:bg-[#245680]';
const topActionButtonClass = 'inline-flex h-10 min-w-[130px] items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600';
const summaryActionSizeClass = 'inline-flex h-10 min-w-[170px] items-center justify-center whitespace-nowrap rounded-md px-3 text-sm font-semibold';
const summaryActionPrimaryClass = `${summaryActionSizeClass} bg-[#2e6da4] text-white hover:bg-[#245680]`;
const summaryActionSecondaryClass = `${summaryActionSizeClass} border border-slate-300 bg-white text-slate-700 hover:border-teal-300 hover:text-teal-600`;

export default function Show({
    pageTitle = 'Invoice Details',
    invoice = {},
    routes = {},
    status_options = [],
    sales_rep_collection_options = [],
    payment_methods = [],
    payment_gateways = [],
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const [activeTab, setActiveTab] = useState('summary');
    const [collectionOpen, setCollectionOpen] = useState(false);
    const [newItemRows, setNewItemRows] = useState([0]);
    const [newItemSeed, setNewItemSeed] = useState(1);

    const paymentTransactions = useMemo(
        () => (invoice.accounting_entries || []).filter((entry) => String(entry.type || '').toLowerCase() === 'payment'),
        [invoice.accounting_entries],
    );

    const transactionHistory = useMemo(() => invoice.accounting_entries || [], [invoice.accounting_entries]);

    const addNewItemRow = () => {
        setNewItemRows((rows) => [...rows, newItemSeed]);
        setNewItemSeed((seed) => seed + 1);
    };

    const canCollection = Boolean(invoice.can_record_payment);

    const hiddenInvoiceStateFields = (
        statusOverride = invoice.selected_status,
        notesOverride = invoice.notes_value,
        includeNotes = true,
    ) => (
        <>
            <input type="hidden" name="status" value={statusOverride || 'unpaid'} />
            <input type="hidden" name="issue_date" value={invoice.issue_date_value || ''} />
            <input type="hidden" name="due_date" value={invoice.due_date_value || ''} />
            {includeNotes ? <input type="hidden" name="notes" value={notesOverride || ''} /> : null}
        </>
    );

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-3xl font-semibold tracking-tight text-slate-900">Invoice #{invoice.number_display}</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setActiveTab('options')}
                            className={topActionButtonClass}
                        >
                            Manage Invoice
                        </button>
                        <a href={routes?.client_view} data-native="true" className={topActionButtonClass}>
                            View as Client
                        </a>
                        <button type="button" onClick={() => window.print()} className={topActionButtonClass}>
                            Print
                        </button>
                        <a href={routes?.download} data-native="true" className={topActionButtonClass}>
                            Download PDF
                        </a>
                        <a href={routes?.index} data-native="true" className={topActionButtonClass}>
                            Back
                        </a>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-300 bg-white">
                    <div className="border-b border-slate-300 bg-slate-50 px-3 py-2">
                        <div className="flex flex-wrap items-center gap-2">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`rounded-md border px-3 py-1.5 text-sm font-medium transition ${
                                        activeTab === tab.key
                                            ? 'border-slate-900 bg-white text-slate-900'
                                            : 'border-slate-300 bg-slate-100 text-slate-700 hover:border-slate-400 hover:text-slate-900'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="p-4">
                        {activeTab === 'summary' ? (
                            <div className="grid gap-5 lg:grid-cols-2">
                                <div className="rounded-lg border border-slate-300 bg-slate-50/60 p-3">
                                    <table className="w-full text-sm">
                                        <tbody>
                                            <tr className="border-b border-slate-200">
                                                <td className="w-40 px-2 py-1.5 font-semibold text-slate-600">Client Name</td>
                                                <td className="px-2 py-1.5 text-slate-800">
                                                    {invoice.customer?.show_route ? (
                                                        <a href={invoice.customer.show_route} data-native="true" className="text-teal-700 hover:text-teal-600">
                                                            {invoice.customer?.name}
                                                        </a>
                                                    ) : (
                                                        invoice.customer?.name
                                                    )}
                                                </td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="px-2 py-1.5 font-semibold text-slate-600">Invoice Date</td>
                                                <td className="px-2 py-1.5 text-slate-800">{invoice.issue_date_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="px-2 py-1.5 font-semibold text-slate-600">Due Date</td>
                                                <td className="px-2 py-1.5 text-slate-800">{invoice.due_date_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="px-2 py-1.5 font-semibold text-slate-600">Invoice Amount</td>
                                                <td className="px-2 py-1.5 text-slate-800">{invoice.totals?.total_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="px-2 py-1.5 font-semibold text-slate-600">Credit</td>
                                                <td className="px-2 py-1.5 text-slate-800">{invoice.totals?.credit_display}</td>
                                            </tr>
                                            <tr>
                                                <td className="px-2 py-1.5 font-semibold text-slate-700">Balance</td>
                                                <td className="px-2 py-1.5 font-semibold text-rose-700">{invoice.totals?.outstanding_display}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div className="rounded-lg border border-slate-300 bg-slate-50/60 p-4 text-center">
                                    <div className={`text-4xl font-bold uppercase tracking-wide ${statusTextClass(invoice.status)}`}>
                                        {invoice.status_label}
                                    </div>
                                    <div className="mt-2 text-lg text-slate-700">
                                        Payment Method: <span className="font-semibold">{invoice.payment_method_display || '--'}</span>
                                    </div>

                                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                                        {invoice.can_record_payment ? (
                                            <form method="POST" action={routes?.mark_paid} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <button type="submit" className={summaryActionPrimaryClass}>Mark Paid</button>
                                            </form>
                                        ) : null}

                                        {invoice.status !== 'cancelled' ? (
                                            <form method="POST" action={routes?.update} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="PUT" />
                                                {hiddenInvoiceStateFields('cancelled')}
                                                <button type="submit" className={summaryActionSecondaryClass}>Mark Cancelled</button>
                                            </form>
                                        ) : null}

                                        {invoice.status !== 'unpaid' ? (
                                            <form method="POST" action={routes?.update} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="PUT" />
                                                {hiddenInvoiceStateFields('unpaid')}
                                                <button type="submit" className={summaryActionSecondaryClass}>Mark Unpaid</button>
                                            </form>
                                        ) : null}

                                        {canCollection ? (
                                            <button
                                                type="button"
                                                onClick={() => setCollectionOpen((current) => !current)}
                                                className={summaryActionSecondaryClass}
                                            >
                                                {collectionOpen ? 'Hide Sales Rep Collection' : 'Collected by Sales Rep'}
                                            </button>
                                        ) : null}
                                    </div>
                                </div>

                                {collectionOpen ? (
                                    <div className="lg:col-span-2 rounded-lg border border-emerald-200 bg-emerald-50/40 p-4">
                                        <div className="mb-2 text-sm font-semibold text-slate-800">Mark paid via sales representative</div>
                                        <form method="POST" action={routes?.collect_by_sales_rep} data-native="true" className="grid gap-4 md:grid-cols-2">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Sales Representative</label>
                                                <select
                                                    name="sales_rep_id"
                                                    required
                                                    defaultValue={sales_rep_collection_options[0]?.id || ''}
                                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                >
                                                    {sales_rep_collection_options.map((item) => (
                                                        <option key={item.id} value={item.id}>{item.label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Collected Amount</label>
                                                <input
                                                    name="collected_amount"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    defaultValue={invoice.totals?.outstanding_value || ''}
                                                    required
                                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Retained / Taken Amount</label>
                                                <input
                                                    name="retained_amount"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue="0.00"
                                                    className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payout Method</label>
                                                <select name="payout_method" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                                    <option value="">Select</option>
                                                    {payment_methods.map((method) => (
                                                        <option key={method.code} value={method.code}>{method.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                                                <input name="reference" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                            </div>
                                            <div>
                                                <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Note</label>
                                                <textarea name="note" rows={1} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                            </div>
                                            <div className="md:col-span-2 flex justify-end">
                                                <button
                                                    type="submit"
                                                    disabled={sales_rep_collection_options.length === 0}
                                                    className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-400"
                                                >
                                                    Save collected payment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {activeTab === 'add_payment' ? (
                            <form method="POST" action={routes?.add_payment} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Date</label>
                                    <input
                                        name="entry_date"
                                        type="text"
                                        placeholder="DD-MM-YYYY"
                                        defaultValue={invoice.issue_date_value || ''}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                                    <input
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        defaultValue={invoice.totals?.outstanding_value || ''}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment Method</label>
                                    <select name="payment_gateway_id" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Select</option>
                                        {payment_gateways.map((gateway) => (
                                            <option key={gateway.id} value={gateway.id}>{gateway.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Transaction ID</label>
                                    <input name="reference" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Transaction Fees</label>
                                    <input
                                        name="transaction_fee"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        defaultValue="0.00"
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Description</label>
                                    <input
                                        name="description"
                                        defaultValue={`Payment for invoice #${invoice.number_display || invoice.id}`}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="md:col-span-2 flex items-center justify-between">
                                    <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" name="send_email" value="1" defaultChecked />
                                        Check to send confirmation email
                                    </label>
                                    <button type="submit" className={actionPrimaryClass}>Add Payment</button>
                                </div>
                            </form>
                        ) : null}

                        {activeTab === 'options' ? (
                            <form method="POST" action={routes?.update} data-native="true" className="grid gap-4">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Invoice Date</label>
                                        <input
                                            name="issue_date"
                                            type="text"
                                            placeholder="DD-MM-YYYY"
                                            defaultValue={invoice.issue_date_value || ''}
                                            required
                                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Due Date</label>
                                        <input
                                            name="due_date"
                                            type="text"
                                            placeholder="DD-MM-YYYY"
                                            defaultValue={invoice.due_date_value || ''}
                                            required
                                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Invoice #</label>
                                        <input
                                            value={invoice.number_display || ''}
                                            readOnly
                                            className="mt-1 w-full rounded-md border border-slate-200 bg-slate-100 px-3 py-2 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                                        <select name="status" defaultValue={invoice.selected_status} className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                            {status_options.map((option) => (
                                                <option key={option} value={option}>{option.charAt(0).toUpperCase() + option.slice(1)}</option>
                                            ))}
                                        </select>
                                    </div>
                                </div>

                                <div className="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                    <div className="mb-3 text-sm font-semibold text-slate-800">Invoice Items</div>
                                    <div className="space-y-3">
                                        {(invoice.items || []).map((item) => (
                                            <div key={item.id} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                                <input
                                                    name={`items[${item.id}][description]`}
                                                    defaultValue={item.description}
                                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                    placeholder="Description"
                                                />
                                                <input
                                                    name={`items[${item.id}][amount]`}
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    defaultValue={item.line_total_value}
                                                    className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                    placeholder="Amount"
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    <div className="mt-4 border-t border-slate-200 pt-4">
                                        <div className="mb-3 flex items-center justify-between gap-3">
                                            <div className="text-sm font-semibold text-slate-800">Add Item</div>
                                            <button type="button" onClick={addNewItemRow} className={rowButtonClass}>Add Item</button>
                                        </div>
                                        <div className="space-y-3">
                                            {newItemRows.map((rowKey) => (
                                                <div key={rowKey} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                                    <input
                                                        name={`new_items[${rowKey}][description]`}
                                                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                        placeholder="Description"
                                                    />
                                                    <input
                                                        name={`new_items[${rowKey}][amount]`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                                        placeholder="Amount"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Notes</label>
                                    <textarea
                                        name="notes"
                                        rows={2}
                                        defaultValue={invoice.notes_value}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>

                                <div className="flex flex-wrap justify-center gap-2">
                                    <button type="submit" className={actionPrimaryClass}>Save Changes</button>
                                    <a href={routes?.index} data-native="true" className={rowButtonClass}>Cancel Changes</a>
                                </div>
                            </form>
                        ) : null}

                        {activeTab === 'credit' ? (
                            <form method="POST" action={routes?.add_credit} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Date</label>
                                    <input
                                        name="entry_date"
                                        type="text"
                                        placeholder="DD-MM-YYYY"
                                        defaultValue={invoice.issue_date_value || ''}
                                        required
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                                    <input
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        defaultValue={invoice.totals?.outstanding_value || ''}
                                        required
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Reference</label>
                                    <input name="reference" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Description</label>
                                    <input
                                        name="description"
                                        defaultValue={`Credit applied to invoice #${invoice.number_display || invoice.id}`}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="md:col-span-2 flex justify-center">
                                    <button type="submit" className={actionPrimaryClass}>Apply Credit</button>
                                </div>
                            </form>
                        ) : null}

                        {activeTab === 'refund' ? (
                            <form method="POST" action={routes?.add_refund} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Date</label>
                                    <input
                                        name="entry_date"
                                        type="text"
                                        placeholder="DD-MM-YYYY"
                                        defaultValue={invoice.issue_date_value || ''}
                                        required
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</label>
                                    <input
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        defaultValue={invoice.totals?.collected_value || ''}
                                        required
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Refund Type / Gateway</label>
                                    <select name="payment_gateway_id" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                        <option value="">Select</option>
                                        {payment_gateways.map((gateway) => (
                                            <option key={gateway.id} value={gateway.id}>{gateway.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Transaction ID</label>
                                    <input name="reference" className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Description</label>
                                    <input
                                        name="description"
                                        defaultValue={`Refund for invoice #${invoice.number_display || invoice.id}`}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="md:col-span-2 flex items-center justify-between">
                                    {!invoice.can_record_refund ? <div className="text-xs text-amber-700">Refund is usually used after invoice is marked paid.</div> : <div />}
                                    <button type="submit" className={actionPrimaryClass}>Refund</button>
                                </div>
                            </form>
                        ) : null}

                        {activeTab === 'notes' ? (
                            <form method="POST" action={routes?.update} data-native="true" className="space-y-4">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />
                                {hiddenInvoiceStateFields(invoice.selected_status, '', false)}
                                <div>
                                    <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Notes</label>
                                    <textarea
                                        name="notes"
                                        rows={4}
                                        defaultValue={invoice.notes_value}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                                        placeholder="Internal note for this invoice"
                                    />
                                </div>
                                <div className="flex justify-center">
                                    <button type="submit" className={actionPrimaryClass}>Save Notes</button>
                                </div>
                            </form>
                        ) : null}
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="text-2xl font-semibold text-slate-700">Invoice Items</div>
                    <div className="overflow-hidden rounded-xl border border-slate-300 bg-white">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="bg-[#1f4f82] text-left text-sm font-semibold text-white">
                                        <th className="px-3 py-2">Description</th>
                                        <th className="w-48 px-3 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(invoice.items || []).length > 0 ? (
                                        (invoice.items || []).map((item) => (
                                            <tr key={item.id} className="border-t border-slate-200">
                                                <td className="px-3 py-2 text-slate-800">{item.description}</td>
                                                <td className="px-3 py-2 text-right text-slate-800">{item.line_total_display}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={2} className="px-3 py-3 text-center text-slate-500">No invoice items found.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="border-t border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                            <div className="ml-auto max-w-sm space-y-1">
                                <div className="flex items-center justify-between"><span className="font-semibold text-slate-600">Sub Total:</span><span>{invoice.totals?.subtotal_display}</span></div>
                                <div className="flex items-center justify-between"><span className="font-semibold text-slate-600">Credit:</span><span>{invoice.totals?.credit_display}</span></div>
                                <div className="flex items-center justify-between rounded bg-[#1f4f82] px-3 py-2 font-bold text-white"><span>Total Due:</span><span>{invoice.totals?.outstanding_display}</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="text-2xl font-semibold text-slate-700">Transactions</div>
                    <div className="overflow-hidden rounded-xl border border-slate-300 bg-white">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="bg-[#1f4f82] text-left text-sm font-semibold text-white">
                                        <th className="px-3 py-2">Date</th>
                                        <th className="px-3 py-2">Payment Method</th>
                                        <th className="px-3 py-2">Transaction ID</th>
                                        <th className="px-3 py-2 text-right">Amount</th>
                                        <th className="px-3 py-2 text-right">Transaction Fees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {paymentTransactions.length > 0 ? (
                                        paymentTransactions.map((entry) => (
                                            <tr key={entry.id} className="border-t border-slate-200">
                                                <td className="px-3 py-2 text-slate-700">{entry.entry_date_display}</td>
                                                <td className="px-3 py-2 text-slate-700">{entry.gateway_name || '--'}</td>
                                                <td className="px-3 py-2 font-mono text-xs text-slate-700">{entry.reference || '--'}</td>
                                                <td className="px-3 py-2 text-right font-semibold text-slate-800">{entry.amount_display}</td>
                                                <td className="px-3 py-2 text-right text-slate-700">{entry.transaction_fee_display || '--'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-3 text-slate-500">No Records Found</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="text-2xl font-semibold text-slate-700">Transaction History</div>
                    <div className="overflow-hidden rounded-xl border border-slate-300 bg-white">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="bg-[#1f4f82] text-left text-sm font-semibold text-white">
                                        <th className="px-3 py-2">Date</th>
                                        <th className="px-3 py-2">Payment Method</th>
                                        <th className="px-3 py-2">Transaction ID</th>
                                        <th className="px-3 py-2">Status</th>
                                        <th className="px-3 py-2">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactionHistory.length > 0 ? (
                                        transactionHistory.map((entry) => (
                                            <tr key={entry.id} className="border-t border-slate-200">
                                                <td className="px-3 py-2 text-slate-700">{entry.entry_date_display}</td>
                                                <td className="px-3 py-2 text-slate-700">{entry.gateway_name || '--'}</td>
                                                <td className="px-3 py-2 font-mono text-xs text-slate-700">{entry.reference || '--'}</td>
                                                <td className="px-3 py-2 text-slate-700">{entry.status_label || entry.type_label}</td>
                                                <td className="px-3 py-2 text-slate-700">{entry.description || '--'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={5} className="px-3 py-3 text-slate-500">No Records Found</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
