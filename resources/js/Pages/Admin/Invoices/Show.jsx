import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusTextClass = (status) => {
    if (status === 'unpaid' || status === 'overdue') {
        return 'text-rose-700';
    }

    if (status === 'paid') {
        return 'text-emerald-700';
    }

    return 'text-slate-600';
};

export default function Show({
    pageTitle = 'Invoice Details',
    invoice = {},
    routes = {},
    status_options = [],
    sales_rep_collection_options = [],
    payment_methods = [],
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const [manageOpen, setManageOpen] = React.useState(false);
    const [collectionOpen, setCollectionOpen] = React.useState(false);
    const [newItemRows, setNewItemRows] = React.useState([0]);
    const [newItemSeed, setNewItemSeed] = React.useState(1);
    const managePanelRef = React.useRef(null);
    const collectionPanelRef = React.useRef(null);

    const scrollToPanel = (panelRef) => {
        window.requestAnimationFrame(() => {
            panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };

    const toggleManagePanel = () => {
        setManageOpen((current) => {
            const next = !current;
            if (next) {
                scrollToPanel(managePanelRef);
            }

            return next;
        });
    };

    const toggleCollectionPanel = () => {
        setCollectionOpen((current) => {
            const next = !current;
            if (next) {
                scrollToPanel(collectionPanelRef);
            }

            return next;
        });
    };

    const addNewItemRow = () => {
        setNewItemRows((rows) => [...rows, newItemSeed]);
        setNewItemSeed((seed) => seed + 1);
    };

    return (
        <>
            <Head title={pageTitle} />

            <div id="invoiceShowWrap">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="section-label text-2xl">Invoice</div>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Back to invoices
                        </a>
                        <a
                            href={routes?.client_view}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            View as client
                        </a>
                        <a
                            href={routes?.download}
                            data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Download
                        </a>
                        <button
                            type="button"
                            onClick={toggleManagePanel}
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Manage Invoice
                        </button>
                        {invoice.can_record_payment ? (
                            <button
                                type="button"
                                onClick={toggleCollectionPanel}
                                className="rounded-full border border-emerald-200 px-4 py-2 text-sm font-semibold text-emerald-700 hover:border-emerald-300 hover:text-emerald-600"
                            >
                                Collected by Sales Rep
                            </button>
                        ) : null}
                    </div>
                </div>

                {manageOpen ? (
                    <div ref={managePanelRef} className="mb-6 rounded-2xl border border-slate-200 bg-white/90 p-4">
                        <div className="mb-3 text-sm font-semibold text-slate-800">Manage Invoice</div>
                        <form method="POST" action={routes?.update} data-native="true" className="grid gap-4 md:grid-cols-3">
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="_method" value="PUT" />
                            <div>
                                <label className="text-sm text-slate-600">Status</label>
                                <select
                                    name="status"
                                    defaultValue={invoice.selected_status}
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                >
                                    {status_options.map((option) => (
                                        <option key={option} value={option}>
                                            {option.charAt(0).toUpperCase() + option.slice(1)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Issue date</label>
                                <input
                                    name="issue_date"
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    defaultValue={invoice.issue_date_value}
                                    required
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Due date</label>
                                <input
                                    name="due_date"
                                    type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                    defaultValue={invoice.due_date_value}
                                    required
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                            </div>
                            <div className="md:col-span-2">
                                <label className="text-sm text-slate-600">Notes</label>
                                <textarea
                                    name="notes"
                                    rows={1}
                                    defaultValue={invoice.notes_value}
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                <p className="mt-2 text-xs text-slate-500">Use Recalculate to update totals after changing dates.</p>
                            </div>
                            <div className="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                                <div className="mb-3 text-sm font-semibold text-slate-800">Line items</div>
                                <div className="space-y-3">
                                    {(invoice.items || []).map((item) => (
                                        <div key={item.id} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                            <input
                                                name={`items[${item.id}][description]`}
                                                defaultValue={item.description}
                                                placeholder="Description"
                                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                            />
                                            <input
                                                name={`items[${item.id}][amount]`}
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                defaultValue={item.line_total_value}
                                                placeholder="Amount"
                                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="md:col-span-2 rounded-2xl border border-dashed border-slate-300 bg-white p-4">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <div className="text-sm font-semibold text-slate-800">Add custom line item</div>
                                    <button
                                        type="button"
                                        onClick={addNewItemRow}
                                        className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                    >
                                        Add line
                                    </button>
                                </div>
                                <div className="space-y-3">
                                    {newItemRows.map((rowKey) => (
                                        <div key={rowKey} className="grid gap-2 md:grid-cols-[1fr_180px]">
                                            <input
                                                name={`new_items[${rowKey}][description]`}
                                                placeholder="Custom description"
                                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                            />
                                            <input
                                                name={`new_items[${rowKey}][amount]`}
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="Amount"
                                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div className="md:col-span-2 flex justify-end">
                                <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                                    Save invoice
                                </button>
                            </div>
                        </form>
                    </div>
                ) : null}

                {collectionOpen ? (
                    <div ref={collectionPanelRef} className="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
                        <div className="mb-1 text-sm font-semibold text-slate-800">Mark paid via sales representative</div>
                        <div className="text-xs text-slate-500">
                            Record the collected amount from the sales representative. Full outstanding collection marks the invoice paid. A smaller amount saves a partial collection only. Retained / Taken Amount is the portion of that collected cash kept by the sales rep.
                        </div>
                        <form method="POST" action={routes?.collect_by_sales_rep} data-native="true" className="mt-4 grid gap-4 md:grid-cols-2">
                            <input type="hidden" name="_token" value={csrf} />
                            <div>
                                <label className="text-sm text-slate-600">Sales Representative</label>
                                <select
                                    name="sales_rep_id"
                                    required
                                    defaultValue={sales_rep_collection_options[0]?.id || ''}
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                >
                                    {sales_rep_collection_options.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                {sales_rep_collection_options.length === 0 ? (
                                    <div className="mt-2 text-xs text-amber-700">No active sales representative is linked to this invoice.</div>
                                ) : null}
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Collected Amount</label>
                                <input
                                    name="collected_amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    required
                                    defaultValue={invoice.totals?.outstanding_value || ''}
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                <div className="mt-2 text-xs text-slate-500">
                                    Outstanding now: {invoice.totals?.outstanding_display || invoice.totals?.payable_display || '--'}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Retained / Taken Amount</label>
                                <input
                                    name="retained_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    defaultValue="0.00"
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                />
                                <div className="mt-2 text-xs text-slate-500">
                                    Optional. This amount must be less than or equal to the collected amount.
                                </div>
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Payout Method</label>
                                <select name="payout_method" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                                    <option value="">Select</option>
                                    {payment_methods.map((method) => (
                                        <option key={method.code} value={method.code}>
                                            {method.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="text-sm text-slate-600">Reference</label>
                                <input name="reference" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-2">
                                <label className="text-sm text-slate-600">Note</label>
                                <textarea
                                    name="note"
                                    rows={1}
                                    className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                    placeholder="Optional note about the collection or retained amount"
                                />
                            </div>
                            <div className="md:col-span-2 flex justify-end">
                                <button
                                    type="submit"
                                    disabled={sales_rep_collection_options.length === 0}
                                    className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-400"
                                >
                                    Save collected payment
                                </button>
                            </div>
                        </form>
                    </div>
                ) : null}

                <div className="card p-6 space-y-6">
                    <div className="invoice-container">
                        <div className="flex flex-wrap items-start justify-between gap-6">
                            <div className="flex min-h-[84px] items-center">
                                {invoice.company?.logo_url ? (
                                    <img
                                        src={invoice.company.logo_url}
                                        alt={`${invoice.company?.name || 'Company'} logo`}
                                        className="h-auto max-h-16 w-auto max-w-[260px] object-contain"
                                    />
                                ) : (
                                    <div className="text-2xl font-extrabold tracking-tight text-slate-900">{invoice.company?.name || 'Company'}</div>
                                )}
                            </div>
                            <div className="text-left sm:text-right">
                                <div className="text-xl font-semibold text-slate-900">#{invoice.number_display}</div>
                                <div className={`mt-2 text-lg font-semibold ${statusTextClass(invoice.status)}`}>{invoice.status_label}</div>
                                <div className="mt-2 text-sm text-slate-500">Invoice Date: {invoice.issue_date_display}</div>
                                <div className="text-sm text-slate-500">Invoice Due Date: {invoice.due_date_display}</div>
                                {invoice.paid_at_display ? <div className="text-sm text-slate-500">Paid Date: {invoice.paid_at_display}</div> : null}
                                {invoice.sales_rep_collection?.sales_rep_name ? (
                                    <div className="mt-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-left text-xs text-emerald-700 sm:text-right">
                                        <div>Paid via Sales Rep: {invoice.sales_rep_collection.sales_rep_name}</div>
                                        <div>Collected: {invoice.sales_rep_collection.collected_amount_display}</div>
                                        <div>Retained / Taken: {invoice.sales_rep_collection.retained_amount_display}</div>
                                        <div>Reference: {invoice.sales_rep_collection.reference}</div>
                                        {invoice.sales_rep_collection.note ? <div>{invoice.sales_rep_collection.note}</div> : null}
                                    </div>
                                ) : null}
                            </div>
                        </div>

                        <hr className="my-5 border-slate-200" />

                        <div className="grid gap-6 md:grid-cols-2">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Invoiced To</div>
                                <div className="mt-2 text-sm text-slate-600">{invoice.customer?.name}</div>
                                <div className="text-sm text-slate-600">{invoice.customer?.email}</div>
                                <div className="text-sm text-slate-600">{invoice.customer?.address}</div>
                            </div>
                            <div className="md:text-right">
                                <div className="text-sm font-semibold text-slate-900">Pay To</div>
                                <div className="mt-2 text-sm text-slate-600">{invoice.company?.name}</div>
                                <div className="text-sm text-slate-600">{invoice.company?.pay_to_text}</div>
                                <div className="text-sm text-slate-600">{invoice.company?.email}</div>
                            </div>
                        </div>

                        <div className="mt-6 overflow-x-auto">
                            <table className="w-full border-collapse">
                                <thead>
                                    <tr className="text-left text-sm text-slate-600">
                                        <th className="border border-slate-200 px-3 py-2">Description</th>
                                        <th className="border border-slate-200 px-3 py-2 text-center">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(invoice.items || []).map((item) => (
                                        <tr key={item.id}>
                                            <td className="border border-slate-200 px-3 py-2">{item.description}</td>
                                            <td className="border border-slate-200 px-3 py-2 text-center">{item.line_total_display}</td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <td className="border border-slate-200 px-3 py-2 text-right font-semibold">Sub Total</td>
                                        <td className="border border-slate-200 px-3 py-2 text-center">{invoice.totals?.subtotal_display}</td>
                                    </tr>
                                    {invoice.totals?.has_tax ? (
                                        <tr>
                                            <td className="border border-slate-200 px-3 py-2 text-right font-semibold">
                                                {(invoice.totals?.tax_mode === 'inclusive' ? 'Included Tax' : invoice.totals?.tax_label) ||
                                                    'Tax'}{' '}
                                                ({invoice.totals?.tax_rate_display}%)
                                            </td>
                                            <td className="border border-slate-200 px-3 py-2 text-center">{invoice.totals?.tax_amount_display}</td>
                                        </tr>
                                    ) : null}
                                    <tr>
                                        <td className="border border-slate-200 px-3 py-2 text-right font-semibold">Discount</td>
                                        <td className="border border-slate-200 px-3 py-2 text-center">{invoice.totals?.discount_display}</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-200 px-3 py-2 text-right font-semibold">Payable Amount</td>
                                        <td className="border border-slate-200 px-3 py-2 text-center">{invoice.totals?.payable_display}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {(invoice.accounting_entries || []).length > 0 ? (
                        <div className="mt-6">
                            <div className="section-label">Accounting entries</div>
                            <div className="mt-3 space-y-2 text-sm">
                                {invoice.accounting_entries.map((entry) => (
                                    <div key={entry.id} className="flex items-center justify-between border-b border-slate-200 pb-2">
                                        <div>
                                            <div className="font-semibold text-slate-900">{entry.type_label}</div>
                                            <div className="text-xs text-slate-500">
                                                {entry.entry_date_display}
                                                {entry.gateway_name ? ` - ${entry.gateway_name}` : ''}
                                            </div>
                                            {entry.description ? <div className="text-xs text-slate-500">{entry.description}</div> : null}
                                            {entry.sales_rep_collection ? (
                                                <div className="mt-1 text-xs text-emerald-700">
                                                    Paid via {entry.sales_rep_collection.sales_rep_name} | Retained: {entry.sales_rep_collection.retained_amount_display} | Ref: {entry.sales_rep_collection.reference}
                                                </div>
                                            ) : null}
                                            {entry.sales_rep_collection?.note ? <div className="text-xs text-slate-500">{entry.sales_rep_collection.note}</div> : null}
                                        </div>
                                        <div className={`font-semibold ${entry.amount_class}`}>{entry.amount_display}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}

                    {(invoice.payment_proofs || []).length > 0 ? (
                        <div className="mt-6">
                            <div className="section-label">Manual payment submissions</div>
                            <div className="mt-3 space-y-3 text-sm">
                                {invoice.payment_proofs.map((proof) => (
                                    <div key={proof.id} className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="font-semibold text-slate-900">{proof.gateway_name}</div>
                                                <div className="text-xs text-slate-500">
                                                    Amount: {proof.amount_display} - Reference: {proof.reference} - Status: {proof.status_label}
                                                </div>
                                                {proof.paid_at_display ? <div className="text-xs text-slate-500">Paid at: {proof.paid_at_display}</div> : null}
                                                {proof.notes ? <div className="mt-2 text-xs text-slate-500">{proof.notes}</div> : null}
                                                {proof.reviewer_name ? (
                                                    <div className="mt-2 text-xs text-slate-400">Reviewed by {proof.reviewer_name}</div>
                                                ) : null}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {proof.attachment_url ? (
                                                    <a
                                                        href={proof.attachment_url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                                    >
                                                        View receipt
                                                    </a>
                                                ) : proof.attachment_path ? (
                                                    <span className="text-xs font-semibold text-slate-400">Receipt unavailable</span>
                                                ) : null}
                                                {proof.can_review ? (
                                                    <>
                                                        <form method="POST" action={proof.routes?.approve} data-native="true">
                                                            <input type="hidden" name="_token" value={csrf} />
                                                            <button type="submit" className="rounded-full bg-emerald-500 px-4 py-2 text-xs font-semibold text-white">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <form method="POST" action={proof.routes?.reject} data-native="true">
                                                            <input type="hidden" name="_token" value={csrf} />
                                                            <button
                                                                type="submit"
                                                                className="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300"
                                                            >
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : null}

                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        {invoice.can_record_payment ? (
                            <>
                                <a
                                    href={routes?.record_payment}
                                    data-native="true"
                                    className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white"
                                >
                                    Record payment
                                </a>
                                <form method="POST" action={routes?.recalculate} data-native="true">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <button
                                        type="submit"
                                        className="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                    >
                                        Recalculate
                                    </button>
                                </form>
                            </>
                        ) : null}
                        {invoice.can_record_refund ? (
                            <a
                                href={routes?.record_refund}
                                data-native="true"
                                className="rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                            >
                                Record refund
                            </a>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}
