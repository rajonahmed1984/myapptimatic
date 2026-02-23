import React from 'react';
import { Head, usePage } from '@inertiajs/react';

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

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form id="subscriptionsSearchForm" method="GET" action={routes?.index} data-native="true" className="flex items-center gap-3">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search subscriptions..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                onInput={(event) => {
                                    const input = event.currentTarget;
                                    clearTimeout(input.__searchTimer);
                                    input.__searchTimer = setTimeout(() => input.form?.requestSubmit(), 300);
                                }}
                            />
                        </div>
                    </form>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
                >
                    New Subscription
                </a>
            </div>

            <div id="subscriptionsTable">
                <div className="card overflow-x-auto">
                    <table className="w-full min-w-[1100px] text-left text-sm">
                        <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Customer</th>
                                <th className="px-4 py-3">Product & Plan</th>
                                <th className="px-4 py-3">Amount</th>
                                <th className="px-4 py-3">Interval</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Next invoice</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.length > 0 ? (
                                subscriptions.map((subscription) => (
                                    <tr key={subscription.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 text-slate-500">{subscription.id}</td>
                                        <td className="px-4 py-3">
                                            {subscription.customer_url ? (
                                                <a href={subscription.customer_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    {subscription.customer_name}
                                                </a>
                                            ) : (
                                                <span className="text-slate-500">--</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{subscription.product_plan}</td>
                                        <td className="px-4 py-3 text-slate-600">{subscription.amount_display}</td>
                                        <td className="px-4 py-3 text-slate-500">{subscription.interval_label}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(subscription.status)}`}>
                                                {subscription.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{subscription.next_invoice_display}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <a
                                                    href={subscription?.routes?.edit}
                                                    data-native="true"
                                                    className="text-teal-600 hover:text-teal-500"
                                                >
                                                    Manage
                                                </a>
                                                <form
                                                    method="POST"
                                                    action={subscription?.routes?.destroy}
                                                    data-native="true"
                                                    onSubmit={(event) => {
                                                        if (!window.confirm('Delete this subscription?')) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={10} className="px-4 py-6 text-center text-slate-500">
                                        No subscriptions yet.
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
            </div>
        </>
    );
}