import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

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

const inputClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-full border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-600';
const inputReadonlyClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-full border border-slate-200 bg-slate-100 text-slate-500';
const textareaClass = 'w-full rounded-2xl border border-slate-300 px-4 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700';
const btnPrimary = 'rounded-full bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500';
const btnSecondary = 'rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600';
const btnDanger = 'rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50';

const printStyles = `
    @media screen { .invoice-print-area { display: none !important; } }
    @media print {
        @page { size: auto; margin: 20px; padding: 0; }
        body * { visibility: hidden !important; }
        .invoice-print-area, .invoice-print-area * { visibility: visible !important; }
        .invoice-print-area {
            position: fixed; top: 0; left: 0; right: 0;
            padding: 20px; background: #fff;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 14px; line-height: 1.42857143; color: #333;
        }
        .inv-grid { display: table; width: 100%; table-layout: fixed; }
        .inv-col { display: table-cell; width: 50%; vertical-align: top; padding: 0 15px; }
        .inv-col-right { text-align: right; }
        .inv-logo { max-width: 300px; height: auto; }
        .inv-logo-fallback { font-size: 36px; font-weight: 800; color: #211f75; letter-spacing: -1px; }
        .inv-hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        address { font-style: normal; line-height: 1.5; margin: 8px 0 0; font-size: 0.92em; }
        .inv-status { font-size: 24px; font-weight: bold; text-transform: uppercase; }
        .inv-status-unpaid, .inv-status-overdue { color: #cc0000; }
        .inv-status-paid { color: #779500; }
        .inv-status-cancelled, .inv-status-refunded { color: #888888; }
        .inv-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .inv-table td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        .inv-tr { text-align: right; }
        .inv-tc { text-align: center; }
        .inv-footer { margin-top: 50px; text-align: center; font-size: 0.9em; }
    }
`;

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
    const salesRepCollectionOptions = useMemo(
        () => [
            { value: '', label: 'Select sales rep' },
            ...sales_rep_collection_options.map((item) => ({ value: String(item.id), label: item.label })),
        ],
        [sales_rep_collection_options],
    );
    const payoutMethodOptions = useMemo(
        () => [
            { value: '', label: 'Select' },
            ...payment_methods.map((method) => ({ value: String(method.code), label: method.name })),
        ],
        [payment_methods],
    );
    const paymentGatewayOptions = useMemo(
        () => [
            { value: '', label: 'Select' },
            ...payment_gateways.map((gateway) => ({ value: String(gateway.id), label: gateway.name })),
        ],
        [payment_gateways],
    );
    const statusSelectOptions = useMemo(
        () => status_options.map((option) => ({ value: String(option), label: option.charAt(0).toUpperCase() + option.slice(1) })),
        [status_options],
    );

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
            {/* eslint-disable-next-line react/no-danger */}
            <style dangerouslySetInnerHTML={{ __html: printStyles }} />

            <div className="space-y-6">
                {/* ── Page header ── */}
                <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h1 className="text-xl font-semibold text-slate-900">Invoice #{invoice.number_display}</h1>
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => setActiveTab('options')} className={btnSecondary}>Manage Invoice</button>
                            <a href={routes?.client_view} data-native="true" className={btnSecondary}>View as Client</a>
                            <button type="button" onClick={() => window.print()} className={btnSecondary}>Print</button>
                            <a href={routes?.download} data-native="true" className={btnSecondary}>Download PDF</a>
                            <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">Back to list</a>
                        </div>
                    </div>
                </div>

                {/* ── Tabs + content ── */}
                <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white">
                    {/* Tab bar */}
                    <div className="border-b border-slate-200 px-6 pt-4">
                        <div className="flex flex-wrap gap-2 pb-3">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.key}
                                    type="button"
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                                        activeTab === tab.key
                                            ? 'bg-teal-600 text-white'
                                            : 'border border-slate-300 text-slate-600 hover:border-teal-300 hover:text-teal-600'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="p-6">
                        {/* ── Summary ── */}
                        {activeTab === 'summary' ? (
                            <div className="grid gap-5 lg:grid-cols-2">
                                {/* Info table */}
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                                    <table className="w-full text-xs">
                                        <tbody>
                                            <tr className="border-b border-slate-200">
                                                <td className="w-40 py-2 pr-3 font-semibold text-slate-500">Client</td>
                                                <td className="py-2 text-slate-800">
                                                    {invoice.customer?.show_route ? (
                                                        <a href={invoice.customer.show_route} data-native="true" className="font-medium text-teal-600 hover:text-teal-500">
                                                            {invoice.customer?.name}
                                                        </a>
                                                    ) : invoice.customer?.name}
                                                </td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="py-2 pr-3 font-semibold text-slate-500">Invoice Date</td>
                                                <td className="py-2 text-slate-800">{invoice.issue_date_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="py-2 pr-3 font-semibold text-slate-500">Due Date</td>
                                                <td className="py-2 text-slate-800">{invoice.due_date_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="py-2 pr-3 font-semibold text-slate-500">Invoice Amount</td>
                                                <td className="py-2 text-slate-800">{invoice.totals?.total_display}</td>
                                            </tr>
                                            <tr className="border-b border-slate-200">
                                                <td className="py-2 pr-3 font-semibold text-slate-500">Credit</td>
                                                <td className="py-2 text-slate-800">{invoice.totals?.credit_display}</td>
                                            </tr>
                                            <tr>
                                                <td className="py-2 pr-3 font-semibold text-slate-700">Balance</td>
                                                <td className="py-2 font-semibold text-rose-600">{invoice.totals?.outstanding_display}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                {/* Status + actions */}
                                <div className="flex flex-col items-center justify-center gap-4 rounded-2xl border border-slate-200 bg-slate-50/50 p-6 text-center">
                                    <div className={`text-3xl font-bold uppercase tracking-wider ${statusTextClass(invoice.status)}`}>
                                        {invoice.status_label}
                                    </div>
                                    <div className="text-xs text-slate-500">
                                        Payment Method: <span className="font-semibold text-slate-700">{invoice.payment_method_display || '--'}</span>
                                    </div>
                                    <div className="flex flex-wrap justify-center gap-2">
                                        {invoice.can_record_payment ? (
                                            <form method="POST" action={routes?.mark_paid} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <button type="submit" className={btnPrimary}>Mark Paid</button>
                                            </form>
                                        ) : null}
                                        {invoice.status !== 'cancelled' ? (
                                            <form method="POST" action={routes?.update} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="PUT" />
                                                {hiddenInvoiceStateFields('cancelled')}
                                                <button type="submit" className={btnDanger}>Mark Cancelled</button>
                                            </form>
                                        ) : null}
                                        {invoice.status !== 'unpaid' ? (
                                            <form method="POST" action={routes?.update} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="PUT" />
                                                {hiddenInvoiceStateFields('unpaid')}
                                                <button type="submit" className={btnSecondary}>Mark Unpaid</button>
                                            </form>
                                        ) : null}
                                        {canCollection ? (
                                            <button type="button" onClick={() => setCollectionOpen((c) => !c)} className={btnSecondary}>
                                                {collectionOpen ? 'Hide Sales Rep Collection' : 'Collected by Sales Rep'}
                                            </button>
                                        ) : null}
                                    </div>
                                </div>

                                {/* Sales rep collection form */}
                                {collectionOpen ? (
                                    <div className="lg:col-span-2 rounded-2xl border border-teal-200 bg-teal-50/30 p-5">
                                        <div className="mb-3 text-sm font-semibold text-slate-800">Mark paid via sales representative</div>
                                        <form method="POST" action={routes?.collect_by_sales_rep} data-native="true" className="grid gap-4 md:grid-cols-2">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <div>
                                                <label className={labelClass}>Sales Representative</label>
                                                <SearchableSelect name="sales_rep_id" required defaultValue={String(sales_rep_collection_options[0]?.id || '')} options={salesRepCollectionOptions} className="mt-1" placeholder="Select sales rep" />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Collected Amount</label>
                                                <input name="collected_amount" type="number" min="0.01" step="0.01" defaultValue={invoice.totals?.outstanding_value || ''} required className={inputClass} />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Retained / Taken Amount</label>
                                                <input name="retained_amount" type="number" min="0" step="0.01" defaultValue="0.00" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Payout Method</label>
                                                <SearchableSelect name="payout_method" defaultValue="" options={payoutMethodOptions} className="mt-1" placeholder="Select" />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Reference</label>
                                                <input name="reference" className={inputClass} />
                                            </div>
                                            <div>
                                                <label className={labelClass}>Note</label>
                                                <textarea name="note" rows={1} className={textareaClass} />
                                            </div>
                                            <div className="md:col-span-2 flex justify-end">
                                                <button type="submit" disabled={sales_rep_collection_options.length === 0} className={`${btnPrimary} disabled:cursor-not-allowed disabled:opacity-50`}>
                                                    Save collected payment
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}

                        {/* ── Add Payment ── */}
                        {activeTab === 'add_payment' ? (
                            <form method="POST" action={routes?.add_payment} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className={labelClass}>Date</label>
                                    <input name="entry_date" type="text" placeholder="DD-MM-YYYY" defaultValue={invoice.issue_date_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Amount</label>
                                    <input name="amount" type="number" min="0.01" step="0.01" defaultValue={invoice.totals?.outstanding_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Payment Method</label>
                                    <SearchableSelect name="payment_gateway_id" defaultValue="" options={paymentGatewayOptions} className="mt-1" placeholder="Select" />
                                </div>
                                <div>
                                    <label className={labelClass}>Transaction ID</label>
                                    <input name="reference" className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Transaction Fees</label>
                                    <input name="transaction_fee" type="number" min="0" step="0.01" defaultValue="0.00" className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Description</label>
                                    <input name="description" defaultValue={`Payment for invoice #${invoice.number_display || invoice.id}`} className={inputClass} />
                                </div>
                                <div className="md:col-span-2 flex items-center justify-between">
                                    <label className="inline-flex items-center gap-2 text-xs text-slate-700">
                                        <input type="checkbox" name="send_email" value="1" defaultChecked />
                                        Send confirmation email
                                    </label>
                                    <button type="submit" className={btnPrimary}>Add Payment</button>
                                </div>
                            </form>
                        ) : null}

                        {/* ── Options ── */}
                        {activeTab === 'options' ? (
                            <form method="POST" action={routes?.update} data-native="true" className="grid gap-4">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className={labelClass}>Invoice Date</label>
                                        <input name="issue_date" type="text" placeholder="DD-MM-YYYY" defaultValue={invoice.issue_date_value || ''} required className={inputClass} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Due Date</label>
                                        <input name="due_date" type="text" placeholder="DD-MM-YYYY" defaultValue={invoice.due_date_value || ''} required className={inputClass} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Invoice #</label>
                                        <input value={invoice.number_display || ''} readOnly className={inputReadonlyClass} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Status</label>
                                        <SearchableSelect name="status" defaultValue={String(invoice.selected_status || '')} options={statusSelectOptions} className="mt-1" placeholder="Select status" />
                                    </div>
                                </div>

                                {/* Invoice items */}
                                <div className="rounded-2xl border border-slate-200 bg-slate-50/50 p-4">
                                    <div className="mb-3 text-sm font-semibold text-slate-800">Invoice Items</div>
                                    <div className="space-y-2">
                                        {(invoice.items || []).map((item) => (
                                            <div key={item.id} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                                <input name={`items[${item.id}][description]`} defaultValue={item.description} className={inputClass} placeholder="Description" />
                                                <input name={`items[${item.id}][amount]`} type="number" min="0" step="0.01" defaultValue={item.line_total_value} className={inputClass} placeholder="Amount" />
                                            </div>
                                        ))}
                                    </div>

                                    <div className="mt-4 border-t border-slate-200 pt-4">
                                        <div className="mb-3 flex items-center justify-between gap-3">
                                            <div className="text-sm font-semibold text-slate-800">Add Item</div>
                                            <button type="button" onClick={addNewItemRow} className={btnSecondary}>+ Add Item</button>
                                        </div>
                                        <div className="space-y-2">
                                            {newItemRows.map((rowKey) => (
                                                <div key={rowKey} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                                    <input name={`new_items[${rowKey}][description]`} className={inputClass} placeholder="Description" />
                                                    <input name={`new_items[${rowKey}][amount]`} type="number" min="0" step="0.01" className={inputClass} placeholder="Amount" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label className={labelClass}>Notes</label>
                                    <textarea name="notes" rows={2} defaultValue={invoice.notes_value} className={textareaClass} />
                                </div>

                                <div className="flex flex-wrap items-center gap-3 pt-1">
                                    <button type="submit" className={btnPrimary}>Save Changes</button>
                                    <a href={routes?.index} data-native="true" className={btnSecondary}>Cancel</a>
                                </div>
                            </form>
                        ) : null}

                        {/* ── Credit ── */}
                        {activeTab === 'credit' ? (
                            <form method="POST" action={routes?.add_credit} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className={labelClass}>Date</label>
                                    <input name="entry_date" type="text" placeholder="DD-MM-YYYY" defaultValue={invoice.issue_date_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Amount</label>
                                    <input name="amount" type="number" min="0.01" step="0.01" defaultValue={invoice.totals?.outstanding_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Reference</label>
                                    <input name="reference" className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Description</label>
                                    <input name="description" defaultValue={`Credit applied to invoice #${invoice.number_display || invoice.id}`} className={inputClass} />
                                </div>
                                <div className="md:col-span-2 flex justify-end">
                                    <button type="submit" className={btnPrimary}>Apply Credit</button>
                                </div>
                            </form>
                        ) : null}

                        {/* ── Refund ── */}
                        {activeTab === 'refund' ? (
                            <form method="POST" action={routes?.add_refund} data-native="true" className="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="_token" value={csrf} />
                                <div>
                                    <label className={labelClass}>Date</label>
                                    <input name="entry_date" type="text" placeholder="DD-MM-YYYY" defaultValue={invoice.issue_date_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Amount</label>
                                    <input name="amount" type="number" min="0.01" step="0.01" defaultValue={invoice.totals?.collected_value || ''} required className={inputClass} />
                                </div>
                                <div>
                                    <label className={labelClass}>Refund Type / Gateway</label>
                                    <SearchableSelect name="payment_gateway_id" defaultValue="" options={paymentGatewayOptions} className="mt-1" placeholder="Select" />
                                </div>
                                <div>
                                    <label className={labelClass}>Transaction ID</label>
                                    <input name="reference" className={inputClass} />
                                </div>
                                <div className="md:col-span-2">
                                    <label className={labelClass}>Description</label>
                                    <input name="description" defaultValue={`Refund for invoice #${invoice.number_display || invoice.id}`} className={inputClass} />
                                </div>
                                <div className="md:col-span-2 flex items-center justify-between">
                                    {!invoice.can_record_refund ? <p className="text-xs text-amber-600">Refund is usually used after invoice is marked paid.</p> : <span />}
                                    <button type="submit" className={btnDanger}>Refund</button>
                                </div>
                            </form>
                        ) : null}

                        {/* ── Notes ── */}
                        {activeTab === 'notes' ? (
                            <form method="POST" action={routes?.update} data-native="true" className="space-y-4">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />
                                {hiddenInvoiceStateFields(invoice.selected_status, '', false)}
                                <div>
                                    <label className={labelClass}>Notes</label>
                                    <textarea name="notes" rows={4} defaultValue={invoice.notes_value} className={textareaClass} placeholder="Internal note for this invoice" />
                                </div>
                                <div className="flex justify-end">
                                    <button type="submit" className={btnPrimary}>Save Notes</button>
                                </div>
                            </form>
                        ) : null}
                    </div>
                </div>

                {/* ── Invoice Items table ── */}
                <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-base font-semibold text-slate-800">Invoice Items</h2>
                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                        <table className="min-w-full text-xs">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left font-semibold text-slate-600">
                                    <th className="px-4 py-2">Description</th>
                                    <th className="w-48 px-4 py-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(invoice.items || []).length > 0 ? (
                                    (invoice.items || []).map((item) => (
                                        <tr key={item.id} className="border-t border-slate-100">
                                            <td className="px-4 py-2 text-slate-700">{item.description}</td>
                                            <td className="px-4 py-2 text-right text-slate-700">{item.line_total_display}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr><td colSpan={2} className="px-4 py-3 text-center text-slate-400">No invoice items found.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="mt-3 ml-auto max-w-xs space-y-1 text-xs">
                        <div className="flex items-center justify-between"><span className="font-semibold text-slate-500">Sub Total:</span><span className="text-slate-700">{invoice.totals?.subtotal_display}</span></div>
                        <div className="flex items-center justify-between"><span className="font-semibold text-slate-500">Credit:</span><span className="text-slate-700">{invoice.totals?.credit_display}</span></div>
                        <div className="flex items-center justify-between rounded-full bg-teal-600 px-4 py-1.5 font-bold text-white">
                            <span>Total Due:</span><span>{invoice.totals?.outstanding_display}</span>
                        </div>
                    </div>
                </div>

                {/* ── Transactions ── */}
                <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-base font-semibold text-slate-800">Transactions</h2>
                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                        <table className="min-w-full text-xs">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left font-semibold text-slate-600">
                                    <th className="px-4 py-2">Date</th>
                                    <th className="px-4 py-2">Payment Method</th>
                                    <th className="px-4 py-2">Transaction ID</th>
                                    <th className="px-4 py-2 text-right">Amount</th>
                                    <th className="px-4 py-2 text-right">Transaction Fees</th>
                                </tr>
                            </thead>
                            <tbody>
                                {paymentTransactions.length > 0 ? (
                                    paymentTransactions.map((entry) => (
                                        <tr key={entry.id} className="border-t border-slate-100">
                                            <td className="px-4 py-2 text-slate-600">{entry.entry_date_display}</td>
                                            <td className="px-4 py-2 text-slate-600">{entry.gateway_name || '--'}</td>
                                            <td className="px-4 py-2 font-mono text-slate-600">{entry.reference || '--'}</td>
                                            <td className="px-4 py-2 text-right font-semibold text-slate-800">{entry.amount_display}</td>
                                            <td className="px-4 py-2 text-right text-slate-600">{entry.transaction_fee_display || '--'}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr><td colSpan={5} className="px-4 py-3 text-center text-slate-400">No Records Found</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ── Transaction History ── */}
                <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-base font-semibold text-slate-800">Transaction History</h2>
                    <div className="overflow-x-auto rounded-xl border border-slate-200">
                        <table className="min-w-full text-xs">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50 text-left font-semibold text-slate-600">
                                    <th className="px-4 py-2">Date</th>
                                    <th className="px-4 py-2">Payment Method</th>
                                    <th className="px-4 py-2">Transaction ID</th>
                                    <th className="px-4 py-2">Status</th>
                                    <th className="px-4 py-2">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactionHistory.length > 0 ? (
                                    transactionHistory.map((entry) => (
                                        <tr key={entry.id} className="border-t border-slate-100">
                                            <td className="px-4 py-2 text-slate-600">{entry.entry_date_display}</td>
                                            <td className="px-4 py-2 text-slate-600">{entry.gateway_name || '--'}</td>
                                            <td className="px-4 py-2 font-mono text-slate-600">{entry.reference || '--'}</td>
                                            <td className="px-4 py-2 text-slate-600">{entry.status_label || entry.type_label}</td>
                                            <td className="px-4 py-2 text-slate-600">{entry.description || '--'}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr><td colSpan={5} className="px-4 py-3 text-center text-slate-400">No Records Found</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* ── Print-only invoice layout (matches PDF) ── */}
            <div className="invoice-print-area">
                {/* Header */}
                <div className="inv-grid">
                    <div className="inv-col">
                        {invoice.company?.logo_url
                            ? <img src={invoice.company.logo_url} alt={invoice.company?.name || ''} className="inv-logo" />
                            : <div className="inv-logo-fallback">{(invoice.company?.name || '').toLowerCase()}</div>
                        }
                    </div>
                    <div className="inv-col inv-col-right">
                        <div className={`inv-status inv-status-${invoice.status}`}>{invoice.status_label}</div>
                        <div style={{ fontSize: 18, fontWeight: 600, marginTop: 4 }}>Invoice: #{invoice.number_display}</div>
                        <div style={{ fontSize: 12, marginTop: 4 }}>Invoice Date: {invoice.issue_date_display}</div>
                        <div style={{ fontSize: 12 }}>Invoice Due Date: {invoice.due_date_display}</div>
                        {invoice.paid_at_display ? <div style={{ fontSize: 12 }}>Paid Date: {invoice.paid_at_display}</div> : null}
                    </div>
                </div>

                <hr className="inv-hr" />

                {/* Addresses */}
                <div className="inv-grid">
                    <div className="inv-col">
                        <strong>Invoiced To</strong>
                        <address>
                            {invoice.customer?.name}<br />
                            {invoice.customer?.email}<br />
                            {invoice.customer?.address}
                        </address>
                    </div>
                    <div className="inv-col inv-col-right">
                        <strong>Pay To</strong>
                        <address>
                            {invoice.company?.name}<br />
                            {invoice.company?.pay_to_text}<br />
                            {invoice.company?.email}
                        </address>
                    </div>
                </div>

                {/* Items table */}
                <table className="inv-table">
                    <thead>
                        <tr>
                            <td><strong>Description</strong></td>
                            <td width="20%" className="inv-tc"><strong>Amount</strong></td>
                        </tr>
                    </thead>
                    <tbody>
                        {(invoice.items || []).map((item) => (
                            <tr key={item.id}>
                                <td>{item.description}</td>
                                <td className="inv-tc">{item.line_total_display}</td>
                            </tr>
                        ))}
                        <tr>
                            <td className="inv-tr"><strong>Sub Total</strong></td>
                            <td className="inv-tc">{invoice.totals?.subtotal_display}</td>
                        </tr>
                        {invoice.totals?.has_tax ? (
                            <tr>
                                <td className="inv-tr">
                                    <strong>
                                        {invoice.totals.tax_mode === 'inclusive' ? 'Included Tax' : invoice.totals.tax_label}
                                        {' '}({invoice.totals.tax_rate_display}%)
                                    </strong>
                                </td>
                                <td className="inv-tc">{invoice.totals.tax_amount_display}</td>
                            </tr>
                        ) : null}
                        <tr>
                            <td className="inv-tr"><strong>Discount</strong></td>
                            <td className="inv-tc">{invoice.totals?.discount_display}</td>
                        </tr>
                        <tr>
                            <td className="inv-tr"><strong>Payable Amount</strong></td>
                            <td className="inv-tc">{invoice.totals?.payable_display}</td>
                        </tr>
                    </tbody>
                </table>

                {/* Footer */}
                <div className="inv-footer">
                    <p>This is system generated invoice no signature required</p>
                </div>
            </div>
        </>
    );
}

