import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
};

const statusClass = (status) => {
    if (status === 'accepted') {
        return 'bg-emerald-100 text-emerald-700';
    }
    if (status === 'cancelled') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-amber-100 text-amber-700';
};

export default function Index({
    pageTitle = 'Orders',
    routes = {},
    orders = [],
    pagination = {},
    search = '',
    status = '',
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
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Orders</h1>
                    <p className="mt-1 text-sm text-slate-500">Review pending orders and manage their status.</p>
                </div>
                <div className="w-full max-w-sm">
                    <form
                        id="ordersSearchForm"
                        method="GET"
                        action={routes?.index}
                        className="flex items-center gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <div className="relative w-full">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search orders..."
                                className="ui-input"
                            />
                        </div>
                    </form>
                </div>
            </div>

            <DataTable
                rows={orders}
                emptyMessage="No orders yet."
                columns={[
                    {
                        key: 'order_number',
                        header: 'Order number',
                        cellClassName: 'font-medium text-slate-900',
                        render: (order) => (
                            <a href={order?.routes?.show} data-native="true" className="text-teal-500">
                                #{order.order_number}
                            </a>
                        ),
                    },
                    { key: 'customer', header: 'Customer', cellClassName: 'text-slate-600', render: (order) => order.customer_name },
                    { key: 'service', header: 'Service', cellClassName: 'text-slate-600', render: (order) => order.service },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (order) => (
                            <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusClass(order.status)}`}>
                                {order.status_label}
                            </span>
                        ),
                    },
                    {
                        key: 'invoice',
                        header: 'Invoice',
                        cellClassName: 'text-slate-500',
                        render: (order) => (
                            order?.routes?.invoice_show ? (
                                <a href={order.routes.invoice_show} data-native="true" className="hover:text-teal-600">
                                    {order.invoice_number}
                                </a>
                            ) : (
                                '--'
                            )
                        ),
                    },
                    { key: 'invoice_amount', header: 'Invoice Amount', cellClassName: 'text-slate-500', render: (order) => order.invoice_amount },
                    {
                        key: 'created',
                        header: 'Created',
                        cellClassName: 'text-slate-500',
                        render: (order) => <DateTimeText value={order.created_at_display} mode="date" />,
                    },
                    {
                        key: 'actions',
                        header: 'Actions',
                        headerClassName: 'text-right',
                        cellClassName: 'text-right',
                        render: (order) => (
                            <div className="flex items-center justify-end gap-3">
                                <a href={order?.routes?.show} data-native="true" className="text-teal-500">
                                    View
                                </a>

                                {order.can_cancel ? (
                                    <form method="POST" action={order?.routes?.cancel} data-native="true">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                            Cancel
                                        </button>
                                    </form>
                                ) : null}

                                <form
                                    method="POST"
                                    action={order?.routes?.destroy}
                                    data-native="true"
                                    onSubmit={(event) => {
                                        if (!window.confirm(`Delete order #${order.order_number}?`)) {
                                            event.preventDefault();
                                        }
                                    }}
                                >
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        ),
                    },
                ]}
                renderMobileCard={(order) => (
                    <MobileCard
                        title={
                            <a href={order?.routes?.show} data-native="true" className="hover:text-teal-600">
                                {order.customer_name}
                            </a>
                        }
                        subtitle={`#${order.order_number} · ${order.service}`}
                        badge={order.status_label}
                        badgeColor={statusClass(order.status)}
                        metrics={[
                            {
                                label: 'Invoice',
                                value: order?.routes?.invoice_show ? (
                                    <a href={order.routes.invoice_show} data-native="true" className="hover:text-teal-600">
                                        {order.invoice_number}
                                    </a>
                                ) : '--',
                            },
                            { label: 'Amount', value: order.invoice_amount },
                        ]}
                        actions={
                            <>
                                <a
                                    href={order?.routes?.show}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                >
                                    View
                                </a>
                                {order.can_cancel ? (
                                    <form method="POST" action={order?.routes?.cancel} data-native="true">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <button
                                            type="submit"
                                            className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                        >
                                            Cancel
                                        </button>
                                    </form>
                                ) : null}
                                <form
                                    method="POST"
                                    action={order?.routes?.destroy}
                                    data-native="true"
                                    onSubmit={(event) => {
                                        if (!window.confirm(`Delete order #${order.order_number}?`)) {
                                            event.preventDefault();
                                        }
                                    }}
                                >
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button
                                        type="submit"
                                        className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </>
                        }
                    >
                        <div className="text-xs text-slate-500">
                            Created: <DateTimeText value={order.created_at_display} mode="date" />
                        </div>
                    </MobileCard>
                )}
            />

            {pagination?.has_pages ? (
                <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                    {pagination?.previous_url ? (
                        <a
                            href={pagination.previous_url}
                            data-native="true"
                            className={BTN.secondary}
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
                            className={BTN.secondary}
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
