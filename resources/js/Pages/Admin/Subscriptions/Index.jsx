import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (status) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }

    if (status === 'cancelled') {
        return 'bg-rose-100 text-rose-700 border-rose-200';
    }

    if (status === 'suspended') {
        return 'bg-amber-100 text-amber-700 border-amber-200';
    }

    return 'bg-slate-100 text-slate-600 border-slate-200';
};

export default function Index({
    pageTitle = 'Subscriptions',
    search = '',
    routes = {},
    subscriptions = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form
                        id="subscriptionsSearchForm"
                        method="GET"
                        action={routes?.index}
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
                                placeholder="Search subscriptions..."
                                className="ui-input"
                            />
                        </div>
                    </form>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="ui-btn-primary"
                >
                    New Subscription
                </a>
            </div>

            <div id="subscriptionsTable">
                <DataTable
                    rows={subscriptions}
                    emptyMessage="No subscriptions yet."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'text-slate-500', render: (s) => s.id },
                        {
                            key: 'customer',
                            header: 'Customer & Product/Plan',
                            render: (s) => (
                                <>
                                    <div className="font-medium text-slate-900">
                                        {s.customer_url ? (
                                            <a href={s.customer_url} data-native="true" className="text-teal-600 hover:text-teal-500">{s.customer_name}</a>
                                        ) : <span className="text-slate-500">--</span>}
                                    </div>
                                    {s.customer_company_name ? <div className="mt-0.5 text-xs font-medium text-slate-600">{s.customer_company_name}</div> : null}
                                    <div className="mt-0.5 text-xs text-slate-500">{s.product_plan}</div>
                                </>
                            ),
                        },
                        {
                            key: 'interval',
                            header: 'Interval & Amount',
                            cellClassName: 'text-slate-600',
                            render: (s) => (
                                <>
                                    <div>{s.interval_label} - {s.amount_display}</div>
                                    {s.open_invoices_total_display ? <div className="mt-1 text-xs font-semibold text-rose-600">Due: {s.open_invoices_total_display}</div> : null}
                                </>
                            ),
                        },
                        {
                            key: 'next_invoice',
                            header: 'Next invoice',
                            cellClassName: 'text-slate-500',
                            render: (s) => (
                                <>
                                    <div>{s.next_invoice_display}</div>
                                    {Number(s.open_invoices_count || 0) > 0 ? (
                                        <div className="mt-1 text-xs text-amber-700">
                                            Stacked: {s.open_invoices_count}
                                            {Number(s.overdue_invoices_count || 0) > 0 ? ` (Overdue ${s.overdue_invoices_count})` : ''}
                                        </div>
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (s) => <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(s.status)}`}>{s.status_label}</span>,
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            render: (s) => (
                                <div className="flex items-center gap-3">
                                    <a href={s?.routes?.show} data-native="true" className="text-slate-700 hover:text-teal-600">View</a>
                                    <a href={s?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">Manage</a>
                                    <form
                                        method="POST"
                                        action={s?.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this subscription?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(subscription) => (
                        <MobileCard
                            title={subscription.customer_url ? (
                                <a href={subscription.customer_url} data-native="true" className="hover:text-teal-600">{subscription.customer_name}</a>
                            ) : (subscription.customer_name || '--')}
                            subtitle={subscription.product_plan}
                            badge={subscription.status_label}
                            badgeColor={statusClass(subscription.status)}
                            metrics={[
                                { label: 'Amount', value: `${subscription.interval_label} - ${subscription.amount_display}` },
                                { label: 'Next Invoice', value: subscription.next_invoice_display || '--' },
                            ]}
                            actions={
                                <>
                                    <a
                                        href={subscription?.routes?.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={subscription?.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Manage
                                    </a>
                                    <form
                                        method="POST"
                                        action={subscription?.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this subscription?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        >
                            {subscription.open_invoices_total_display ? (
                                <div className="text-xs font-semibold text-rose-600">Due: {subscription.open_invoices_total_display}</div>
                            ) : null}
                        </MobileCard>
                    )}
                />

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-end gap-2 text-sm">
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
            </div>
        </>
    );
}
