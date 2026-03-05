import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'paused') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    if (status === 'cancelled') {
        return 'border-slate-200 bg-slate-50 text-slate-600';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
};

const invoiceStatusClass = (status) => {
    if (status === 'paid') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'overdue') {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }

    if (status === 'cancelled') {
        return 'border-slate-200 bg-slate-50 text-slate-600';
    }

    return 'border-amber-200 bg-amber-50 text-amber-700';
};

export default function Show({ pageTitle = 'Maintenance', maintenance = null, invoices = [] }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Projects</div>
                    <div className="text-2xl font-semibold text-slate-900">{maintenance?.title || '--'}</div>
                    <div className="text-sm text-slate-500">Maintenance #{maintenance?.id || '--'}</div>
                </div>
                <div className="flex items-center gap-3">
                    <a
                        href={maintenance?.routes?.index}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                    >
                        Back to maintenance
                    </a>
                    <a
                        href={maintenance?.routes?.edit}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                    >
                        Edit
                    </a>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Status</div>
                    <div className="mt-2">
                        <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(maintenance?.status)}`}>
                            {maintenance?.status_label || '--'}
                        </span>
                    </div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Amount</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{maintenance?.amount_display || '--'}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Next Billing</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{maintenance?.next_billing_date || '--'}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.28em] text-slate-500">Cycle</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-900">{maintenance?.billing_cycle_label || '--'}</div>
                </div>
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-3">
                <div className="card p-6 md:col-span-2">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance Summary</div>
                    <div className="mt-4 grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Project</div>
                            <div className="mt-2 font-semibold text-slate-900">
                                {maintenance?.project_route ? (
                                    <a href={maintenance.project_route} data-native="true" className="text-teal-700 hover:text-teal-600">
                                        {maintenance.project_name}
                                    </a>
                                ) : (
                                    maintenance?.project_name || '--'
                                )}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                            <div className="mt-2 font-semibold text-slate-900">
                                {maintenance?.customer_route ? (
                                    <a href={maintenance.customer_route} data-native="true" className="text-teal-700 hover:text-teal-600">
                                        {maintenance.customer_name}
                                    </a>
                                ) : (
                                    maintenance?.customer_name || '--'
                                )}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Start Date</div>
                            <div className="mt-2 text-slate-700">{maintenance?.start_date || '--'}</div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Last Billed</div>
                            <div className="mt-2 text-slate-700">{maintenance?.last_billed_at || '--'}</div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Auto Invoice</div>
                            <div className="mt-2 text-slate-700">{maintenance?.auto_invoice ? 'Yes' : 'No'}</div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Sales Rep Visible</div>
                            <div className="mt-2 text-slate-700">{maintenance?.sales_rep_visible ? 'Yes' : 'No'}</div>
                        </div>
                        <div>
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Created By</div>
                            <div className="mt-2 text-slate-700">{maintenance?.creator_name || '--'}</div>
                        </div>
                    </div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Quick Actions</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-700">
                        {maintenance?.project_route ? (
                            <a
                                href={maintenance.project_route}
                                data-native="true"
                                className="inline-flex w-full items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                            >
                                Open project
                            </a>
                        ) : null}
                        <a
                            href={maintenance?.routes?.invoices}
                            data-native="true"
                            className="inline-flex w-full items-center justify-center rounded-full border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                        >
                            View invoices
                        </a>
                    </div>
                </div>
            </div>

            <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Invoices</div>
                        <div className="text-xs text-slate-500">Maintenance billing history.</div>
                    </div>
                </div>

                {invoices.length === 0 ? (
                    <div className="mt-4 text-xs text-slate-500">No invoices yet.</div>
                ) : (
                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="px-3 py-2">Invoice</th>
                                    <th className="px-3 py-2">Issue</th>
                                    <th className="px-3 py-2">Due</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2 text-right">Total</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map((invoice) => (
                                    <tr key={invoice.id} className="border-t border-slate-100">
                                        <td className="px-3 py-2 font-semibold text-slate-900">
                                            <a href={invoice.routes?.show} data-native="true" className="text-teal-700 hover:text-teal-600">
                                                #{invoice.number}
                                            </a>
                                        </td>
                                        <td className="px-3 py-2 text-xs text-slate-600">{invoice.issue_date}</td>
                                        <td className="px-3 py-2 text-xs text-slate-600">{invoice.due_date}</td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${invoiceStatusClass(
                                                    invoice.status,
                                                )}`}
                                            >
                                                {invoice.status_label}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right font-semibold">{invoice.total_display}</td>
                                        <td className="px-3 py-2 text-right">
                                            <div className="flex items-center justify-end gap-2 text-xs font-semibold">
                                                <a href={invoice.routes?.show} data-native="true" className="text-slate-700 hover:text-teal-600">
                                                    View
                                                </a>
                                                {invoice.status !== 'paid' ? (
                                                    <form method="POST" action={invoice.routes?.mark_paid} data-native="true">
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <button type="submit" className="text-emerald-700 hover:text-emerald-600">
                                                            Mark paid
                                                        </button>
                                                    </form>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
