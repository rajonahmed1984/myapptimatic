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
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const [manageOpen, setManageOpen] = React.useState(false);

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
                            onClick={() => setManageOpen((current) => !current)}
                            className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Manage Invoice
                        </button>
                    </div>
                </div>

                <div className="card p-6 space-y-6">
                    <div className="invoice-container">
                        <div className="flex flex-wrap items-start justify-between gap-6">
                            <div>
                                <div className="text-xl font-semibold text-slate-900">#{invoice.number_display}</div>
                                <div className={`mt-2 text-lg font-semibold ${statusTextClass(invoice.status)}`}>{invoice.status_label}</div>
                                <div className="mt-2 text-sm text-slate-500">Invoice Date: {invoice.issue_date_display}</div>
                                <div className="text-sm text-slate-500">Invoice Due Date: {invoice.due_date_display}</div>
                                {invoice.paid_at_display ? <div className="text-sm text-slate-500">Paid Date: {invoice.paid_at_display}</div> : null}
                            </div>
                            <div className="text-right">
                                <div className="text-sm font-semibold text-slate-900">Pay To</div>
                                <div className="text-sm text-slate-600">{invoice.company?.name}</div>
                                <div className="text-sm text-slate-600">{invoice.company?.pay_to_text}</div>
                                <div className="text-sm text-slate-600">{invoice.company?.email}</div>
                            </div>
                        </div>

                        <hr className="my-5 border-slate-200" />

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <div className="text-sm font-semibold text-slate-900">Invoiced To</div>
                                <div className="mt-2 text-sm text-slate-600">{invoice.customer?.name}</div>
                                <div className="text-sm text-slate-600">{invoice.customer?.email}</div>
                                <div className="text-sm text-slate-600">{invoice.customer?.address}</div>
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

                    {manageOpen ? (
                        <div className="rounded-2xl border border-slate-200 bg-white/90 p-4">
                            <div className="mb-3 text-sm font-semibold text-slate-800">Manage Invoice</div>
                            <form method="POST" action={routes?.update} data-native="true" className="grid gap-4 md:grid-cols-2">
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
                                        type="date"
                                        defaultValue={invoice.issue_date_value}
                                        required
                                        className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm text-slate-600">Due date</label>
                                    <input
                                        name="due_date"
                                        type="date"
                                        defaultValue={invoice.due_date_value}
                                        required
                                        className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                    />
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-sm text-slate-600">Notes</label>
                                    <textarea
                                        name="notes"
                                        rows={2}
                                        defaultValue={invoice.notes_value}
                                        className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                    />
                                    <p className="mt-2 text-xs text-slate-500">Use Recalculate to update totals after changing dates.</p>
                                </div>
                                <div className="md:col-span-2 flex justify-end">
                                    <button type="submit" className="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                                        Save invoice
                                    </button>
                                </div>
                            </form>
                        </div>
                    ) : null}

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
