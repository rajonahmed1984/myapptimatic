import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Invoices',
    statusFilter = null,
    filters = {},
    project = null,
    invoices = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const tabs = [
        { key: null, label: 'All', href: routes?.index },
        { key: 'paid', label: 'Paid', href: routes?.paid },
        { key: 'unpaid', label: 'Unpaid', href: routes?.unpaid },
        { key: 'overdue', label: 'Overdue', href: routes?.overdue },
        { key: 'cancelled', label: 'Cancelled', href: routes?.cancelled },
        { key: 'refunded', label: 'Refunded', href: routes?.refunded },
    ];

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form method="GET" action={routes?.current || routes?.index} data-native="true" className="flex items-center gap-3">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={filters?.search ?? ''}
                                placeholder="Search invoices..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </form>
                </div>
                <a href={routes?.create} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    Create Invoice
                </a>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    if (!tab.href || project) {
                        return null;
                    }

                    const active = tab.key === statusFilter;

                    return (
                        <a
                            key={tab.label}
                            href={tab.href}
                            data-native="true"
                            className={`rounded-full border px-3 py-1 text-xs font-semibold ${
                                active
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 text-slate-600 hover:border-teal-300 hover:text-teal-600'
                            }`}
                        >
                            {tab.label}
                        </a>
                    );
                })}
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[1050px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Invoice</th>
                            <th className="px-4 py-3">Customer</th>
                            <th className="px-4 py-3">Total</th>
                            <th className="px-4 py-3">Paid date</th>
                            <th className="px-4 py-3">Due</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-slate-500">
                                    {statusFilter ? `No ${pageTitle} found.` : 'No invoices yet.'}
                                </td>
                            </tr>
                        ) : (
                            invoices.map((invoice) => (
                                <tr key={invoice.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        <a href={invoice.routes?.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            {invoice.number_display}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {invoice.customer_route ? (
                                            <a href={invoice.customer_route} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                {invoice.customer_name}
                                            </a>
                                        ) : (
                                            invoice.customer_name
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        {invoice.total_display}
                                        <div>
                                            {invoice.is_partial ? (
                                                <div className="mt-1 text-xs text-slate-500">
                                                    {invoice.paid_amount_display}{' '}
                                                    <span className="text-xs font-semibold text-amber-700">Partial paid</span>
                                                </div>
                                            ) : (
                                                '--'
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{invoice.paid_at_display}</td>
                                    <td className="px-4 py-3">{invoice.due_date_display}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${invoice.status_class}`}>{invoice.status_label}</span>
                                        {invoice.has_pending_proof ? (
                                            <div className="mt-1 text-xs text-amber-600">Manual payment pending review</div>
                                        ) : null}
                                        {!invoice.has_pending_proof && invoice.has_rejected_proof ? (
                                            <div className="mt-1 text-xs text-rose-600">Manual payment rejected</div>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3">
                                            <a href={invoice.routes?.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                View
                                            </a>
                                            <form method="POST" action={invoice.routes?.destroy} data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-rose-600 hover:text-rose-500">
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

            {pagination?.has_pages ? (
                <div className="mt-6 flex items-center justify-end gap-2 text-sm">
                    {pagination?.previous_url ? (
                        <a
                            href={pagination.previous_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Previous
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                    )}

                    {pagination?.next_url ? (
                        <a
                            href={pagination.next_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Next
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                    )}
                </div>
            ) : null}
        </>
    );
}
