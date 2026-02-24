import React from 'react';
import { Head } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-700';
    }

    return 'bg-amber-100 text-amber-700';
};

export default function Index({
    pageTitle = 'Affiliate Payouts',
    filters = {},
    status_options = [],
    routes = {},
    payouts = [],
    pagination = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Payout Management</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Affiliate payouts</h1>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-500"
                >
                    Create payout
                </a>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-6 flex flex-wrap gap-4">
                    <select
                        name="status"
                        defaultValue={filters?.status ?? ''}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    >
                        {status_options.map((option) => (
                            <option key={option.value || 'all'} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <button
                        type="submit"
                        className="rounded-full bg-slate-900 px-6 py-2 text-sm font-semibold text-white"
                    >
                        Filter
                    </button>
                    {filters?.status ? (
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-600"
                        >
                            Clear
                        </a>
                    ) : null}
                </form>

                {payouts.length === 0 ? (
                    <div className="rounded-xl border border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-600">
                        No payouts found.
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px]">
                                <thead className="border-b border-slate-300 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="pb-3 font-semibold">Payout #</th>
                                        <th className="pb-3 font-semibold">Affiliate</th>
                                        <th className="pb-3 font-semibold">Amount</th>
                                        <th className="pb-3 font-semibold">Status</th>
                                        <th className="pb-3 font-semibold">Created</th>
                                        <th className="pb-3 font-semibold">Completed</th>
                                        <th className="pb-3 font-semibold">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {payouts.map((payout) => (
                                        <tr key={payout.id}>
                                            <td className="py-4 font-semibold text-slate-900">{payout.payout_number}</td>
                                            <td className="py-4">{payout.affiliate_name}</td>
                                            <td className="py-4">{payout.amount_display}</td>
                                            <td className="py-4">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(
                                                        payout.status,
                                                    )}`}
                                                >
                                                    {payout.status_label}
                                                </span>
                                            </td>
                                            <td className="py-4 text-slate-600">{payout.created_at_display}</td>
                                            <td className="py-4 text-slate-600">{payout.completed_at_display}</td>
                                            <td className="py-4">
                                                <a
                                                    href={payout?.routes?.show}
                                                    data-native="true"
                                                    className="font-semibold text-teal-600 hover:text-teal-500"
                                                >
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
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
                )}
            </div>
        </>
    );
}
