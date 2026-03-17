import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

export default function Index({
    pageTitle = 'Invoices',
    statusFilter = null,
    filters = {},
    invoiceInsights = {},
    project = null,
    invoices = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const hidePaidDate = statusFilter === 'unpaid' || statusFilter === 'overdue';
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: filters?.search ?? '',
        url: routes?.current || routes?.index,
    });

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
                    <form
                        method="GET"
                        action={routes?.current || routes?.index}
                        className="flex items-center gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
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

            <div className="mb-6 grid gap-4 xl:grid-cols-3">
                <SummaryCard
                    title="Invoice Overview"
                    stats={[
                        { label: 'Invoices', value: Number(invoiceInsights?.overview?.count || 0) },
                        { label: 'Billed', value: invoiceInsights?.overview?.billed_display || '--', tone: 'sky' },
                        { label: 'Collected', value: invoiceInsights?.overview?.collected_display || '--', tone: 'emerald' },
                        { label: 'Outstanding', value: invoiceInsights?.overview?.outstanding_display || '--', tone: 'amber' },
                    ]}
                />
                <SummaryCard
                    title="Status Breakdown"
                    stats={[
                        { label: 'Paid', value: Number(invoiceInsights?.statuses?.paid || 0), tone: 'emerald' },
                        { label: 'Unpaid', value: Number(invoiceInsights?.statuses?.unpaid || 0), tone: 'amber' },
                        { label: 'Overdue', value: Number(invoiceInsights?.statuses?.overdue || 0), tone: 'rose' },
                        { label: 'Partial', value: Number(invoiceInsights?.statuses?.partial || 0), tone: 'sky' },
                        { label: 'Cancelled', value: Number(invoiceInsights?.statuses?.cancelled || 0) },
                        { label: 'Refunded', value: Number(invoiceInsights?.statuses?.refunded || 0) },
                    ]}
                />
                <SummaryCard
                    title="Watchlist"
                    stats={[
                        { label: 'Overdue Invoices', value: Number(invoiceInsights?.watchlist?.overdue_count || 0), tone: 'rose' },
                        { label: 'Overdue Amount', value: invoiceInsights?.watchlist?.overdue_amount_display || '--', tone: 'rose' },
                        { label: 'Pending Proof', value: Number(invoiceInsights?.watchlist?.pending_proof_count || 0), tone: 'amber' },
                        { label: 'Rejected Proof', value: Number(invoiceInsights?.watchlist?.rejected_proof_count || 0) },
                    ]}
                />
            </div>

            <div className="card overflow-x-auto">
                <table className={`w-full text-left text-sm ${hidePaidDate ? 'min-w-[930px]' : 'min-w-[1050px]'}`}>
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Invoice</th>
                            <th className="px-4 py-3">Customer</th>
                            <th className="px-4 py-3">Total</th>
                            {!hidePaidDate ? <th className="px-4 py-3">Paid date</th> : null}
                            <th className="px-4 py-3">Due</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 ? (
                            <tr>
                                <td colSpan={hidePaidDate ? 6 : 7} className="px-4 py-6 text-center text-slate-500">
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
                                    {!hidePaidDate ? (
                                        <td className="px-4 py-3 text-slate-500">
                                            <DateTimeText value={invoice.paid_at_display} mode="datetime" />
                                        </td>
                                    ) : null}
                                    <td className="px-4 py-3">
                                        <DateTimeText value={invoice.due_date_display} mode="date" />
                                    </td>
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

function SummaryCard({ title, stats = [] }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <div className="text-sm font-semibold text-slate-800">{title}</div>
            <div className="mt-4 flex flex-wrap gap-2">
                {stats.map((stat) => (
                    <SummaryStat key={stat.label} label={stat.label} value={stat.value} tone={stat.tone} />
                ))}
            </div>
        </div>
    );
}

function SummaryStat({ label, value, tone = 'slate' }) {
    const toneClass =
        {
            slate: 'border-slate-200 bg-white text-slate-700',
            sky: 'border-sky-200 bg-sky-50 text-sky-700',
            emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            amber: 'border-amber-200 bg-amber-50 text-amber-700',
            rose: 'border-rose-200 bg-rose-50 text-rose-700',
        }[tone] || 'border-slate-200 bg-white text-slate-700';

    return (
        <div className={`rounded-xl border px-3 py-2 ${toneClass}`}>
            <div className="text-[11px] uppercase tracking-[0.18em] opacity-70">{label}</div>
            <div className="mt-1 text-base font-semibold">{value}</div>
        </div>
    );
}
