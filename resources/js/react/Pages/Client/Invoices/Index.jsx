import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    title = 'Invoices',
    subtitle = 'Review invoices and complete payment for unpaid items.',
    statusFilter = null,
    invoices = [],
    routes = {},
}) {
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
            <Head title={title} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{title}</h1>
                    <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
                </div>
                <a href={routes?.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    if (!tab.href) {
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

            {invoices.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">
                    {statusFilter ? `No ${title} found.` : 'No invoices found.'}
                </div>
            ) : (
                <div className="card overflow-visible">
                    <table className="w-full min-w-[860px] text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Invoice</th>
                                <th className="px-4 py-3">Service/Project</th>
                                <th className="px-4 py-3">Total</th>
                                <th className="px-4 py-3">Issue</th>
                                <th className="px-4 py-3">Due</th>
                                <th className="px-4 py-3">Paid</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((invoice) => (
                                <tr key={invoice.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        <a href={invoice?.routes?.show} data-native="true" className="font-medium text-teal-600 hover:text-teal-500">
                                            #{invoice.number_display}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {invoice.service_or_project_url && invoice.service_or_project !== '--' ? (
                                            <a
                                                href={invoice.service_or_project_url}
                                                data-native="true"
                                                className="font-medium text-teal-600 hover:text-teal-500"
                                            >
                                                {invoice.service_or_project}
                                            </a>
                                        ) : (
                                            invoice.service_or_project
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{invoice.total_display}</td>
                                    <td className="px-4 py-3 text-slate-500">{invoice.issue_date_display}</td>
                                    <td className="px-4 py-3 text-slate-500">{invoice.due_date_display}</td>
                                    <td className="px-4 py-3 text-slate-500">{invoice.paid_at_display}</td>
                                    <td className="px-4 py-3 text-slate-600">
                                        <div>
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${invoice.status_class}`}>
                                                {invoice.status_label}
                                            </span>
                                        </div>
                                        {invoice.is_partial ? (
                                            <div className="mt-2">
                                                <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Partial</span>
                                                <div className="mt-1 text-xs text-slate-500">{invoice.paid_amount_display} paid</div>
                                            </div>
                                        ) : null}
                                        {invoice.has_pending_proof ? (
                                            <div className="mt-1 text-xs text-amber-600">Manual payment pending review</div>
                                        ) : null}
                                        {!invoice.has_pending_proof && invoice.has_rejected_proof ? (
                                            <div className="mt-1 text-xs text-rose-600">Manual payment rejected</div>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex flex-wrap items-center justify-end gap-3 text-xs">
                                            <a href={invoice?.routes?.show} data-native="true" className="text-slate-500 hover:text-teal-600">
                                                View
                                            </a>
                                            {invoice?.routes?.pay ? (
                                                <a href={invoice.routes.pay} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    Pay now
                                                </a>
                                            ) : null}
                                            {invoice?.routes?.manual ? (
                                                <a href={invoice.routes.manual} data-native="true" className="text-slate-500 hover:text-teal-600">
                                                    View submission
                                                </a>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
