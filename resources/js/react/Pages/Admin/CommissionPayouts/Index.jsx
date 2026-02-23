import React from 'react';
import { Head } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'Paid') {
        return 'border-emerald-200 bg-emerald-100 text-emerald-700';
    }

    if (status === 'Reversed') {
        return 'border-rose-200 bg-rose-100 text-rose-700';
    }

    return 'border-slate-200 bg-slate-100 text-slate-700';
};

export default function Index({
    pageTitle = 'Commission Payouts',
    routes = {},
    payable_by_rep = [],
    payouts = [],
    pagination = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Commissions</div>
                    <h1 className="text-2xl font-semibold text-slate-900">Payouts</h1>
                    <p className="text-sm text-slate-500">Review payouts and create new ones from payable earnings.</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <a
                        href={routes.export_payouts}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                    >
                        Export payouts CSV
                    </a>
                    <a
                        href={routes.export_earnings}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                    >
                        Export earnings CSV
                    </a>
                    <a
                        href={routes.create}
                        data-native="true"
                        className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                        New payout
                    </a>
                </div>
            </div>

            <div className="card space-y-6 p-6">
                <div className="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div className="mb-2 text-sm font-semibold text-slate-800">Payable by sales rep</div>
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {payable_by_rep.length > 0 ? (
                            payable_by_rep.map((rep) => (
                                <a
                                    key={rep.id}
                                    href={rep?.routes?.create}
                                    data-native="true"
                                    className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 transition hover:border-emerald-300 hover:shadow-sm"
                                >
                                    <div className="font-semibold text-slate-900">{rep.name}</div>
                                    <div className="text-xs text-slate-500">Status: {rep.status}</div>
                                    <div className="mt-1 text-xs text-slate-600">Payable: {rep.earnings_count} items</div>
                                    <div className="text-xs text-slate-600">Total: {rep.total_amount_display}</div>
                                </a>
                            ))
                        ) : (
                            <div className="text-sm text-slate-500">No payable earnings.</div>
                        )}
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-300 bg-white/80 p-4">
                    <div className="text-sm font-semibold text-slate-800">Payout history</div>
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase text-slate-500">
                                    <th className="px-3 py-2">ID</th>
                                    <th className="px-3 py-2">Sales rep</th>
                                    <th className="px-3 py-2">Project</th>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Paid at</th>
                                    <th className="px-3 py-2">Updated</th>
                                    <th className="px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {payouts.length > 0 ? (
                                    payouts.map((payout) => (
                                        <tr key={payout.id} className="border-t border-slate-300">
                                            <td className="px-3 py-2">#{payout.id}</td>
                                            <td className="px-3 py-2">{payout.sales_rep_name}</td>
                                            <td className="px-3 py-2">{payout.project_name}</td>
                                            <td className="px-3 py-2">{payout.type_label}</td>
                                            <td className="px-3 py-2">{payout.total_amount_display}</td>
                                            <td className="px-3 py-2">
                                                <span
                                                    className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusBadgeClass(
                                                        payout.status_label,
                                                    )}`}
                                                >
                                                    {payout.status_label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2">{payout.paid_at_display}</td>
                                            <td className="px-3 py-2">{payout.updated_at_display}</td>
                                            <td className="px-3 py-2">
                                                <a
                                                    href={payout?.routes?.show}
                                                    data-native="true"
                                                    className="font-semibold text-emerald-700 hover:underline"
                                                >
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={9} className="px-3 py-3 text-slate-500">
                                            No payouts yet.
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
                                    className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
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
                                    className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                                >
                                    Next
                                </a>
                            ) : (
                                <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                            )}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
