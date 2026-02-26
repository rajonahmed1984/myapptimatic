import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DateTimeText from '../../../Components/DateTimeText';

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
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Orders</h1>
                    <p className="mt-1 text-sm text-slate-500">Review pending orders and approve or cancel them.</p>
                </div>
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[980px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Order number</th>
                            <th className="px-4 py-3">Customer</th>
                            <th className="px-4 py-3">Service</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Invoice</th>
                            <th className="px-4 py-3">Amount</th>
                            <th className="px-4 py-3">Created</th>
                            <th className="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.length > 0 ? (
                            orders.map((order) => (
                                <tr key={order.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 font-medium text-slate-900">
                                        <a href={order?.routes?.show} data-native="true" className="text-teal-500">
                                            #{order.order_number}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{order.customer_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{order.service}</td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusClass(order.status)}`}>
                                            {order.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">
                                        {order?.routes?.invoice_show ? (
                                            <a href={order.routes.invoice_show} data-native="true" className="hover:text-teal-600">
                                                {order.invoice_number}
                                            </a>
                                        ) : (
                                            '--'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{order.invoice_amount}</td>
                                    <td className="px-4 py-3 text-slate-500">
                                        <DateTimeText value={order.created_at_display} mode="datetime" />
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3">
                                            <a href={order?.routes?.show} data-native="true" className="text-teal-500">
                                                View
                                            </a>

                                            {order.can_process ? (
                                                <>
                                                    <form method="POST" action={order?.routes?.approve} data-native="true">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="rounded-full bg-emerald-500 px-4 py-2 text-xs font-semibold text-white">
                                                            Accept
                                                        </button>
                                                    </form>
                                                    <form method="POST" action={order?.routes?.cancel} data-native="true">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button
                                                            type="submit"
                                                            className="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300"
                                                        >
                                                            Cancel
                                                        </button>
                                                    </form>
                                                </>
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
                                                    className="rounded-full border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:border-rose-300"
                                                >
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                                    No orders yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

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
        </>
    );
}
