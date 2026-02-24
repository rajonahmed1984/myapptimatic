import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-700';
    }

    return 'bg-amber-100 text-amber-700';
};

export default function Show({
    pageTitle = 'Affiliate Payout Details',
    payout = {},
    commissions = [],
    can_complete = false,
    can_delete = false,
    routes = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Payout Details</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">{payout.payout_number}</h1>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back to payouts
                </a>
            </div>

            <div className="card space-y-6 p-6">
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 p-4">
                        <div className="text-xs uppercase tracking-wider text-slate-500">Affiliate</div>
                        <div className="mt-1 text-lg font-semibold text-slate-900">{payout.affiliate_name}</div>
                        <div className="mt-2 text-sm text-slate-600">Amount: {payout.amount_display}</div>
                        <div className="mt-1 text-sm text-slate-600">Method: {payout.payment_method}</div>
                    </div>

                    <div className="rounded-xl border border-slate-200 p-4">
                        <div className="text-xs uppercase tracking-wider text-slate-500">Status</div>
                        <span
                            className={`mt-2 inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(
                                payout.status,
                            )}`}
                        >
                            {payout.status_label}
                        </span>
                        <div className="mt-2 text-sm text-slate-600">Created: {payout.created_at_display}</div>
                        <div className="mt-1 text-sm text-slate-600">Completed: {payout.completed_at_display}</div>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 p-4">
                    <div className="text-xs uppercase tracking-wider text-slate-500">Notes</div>
                    <div className="mt-2 whitespace-pre-line text-sm text-slate-700">{payout.notes}</div>
                </div>

                <div>
                    <div className="mb-2 text-sm font-semibold text-slate-900">Commissions</div>
                    {commissions.length === 0 ? (
                        <div className="rounded-xl border border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                            No commissions linked to this payout.
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-slate-200">
                            <table className="w-full min-w-[800px] text-sm">
                                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2">Description</th>
                                        <th className="px-3 py-2">Invoice</th>
                                        <th className="px-3 py-2">Order</th>
                                        <th className="px-3 py-2">Amount</th>
                                        <th className="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {commissions.map((commission) => (
                                        <tr key={commission.id} className="border-t border-slate-100">
                                            <td className="px-3 py-2">{commission.description}</td>
                                            <td className="px-3 py-2">{commission.invoice_number}</td>
                                            <td className="px-3 py-2">{commission.order_number}</td>
                                            <td className="px-3 py-2">{commission.amount_display}</td>
                                            <td className="px-3 py-2">{commission.status_label}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    {can_complete ? (
                        <form method="POST" action={routes?.complete} data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <button
                                type="submit"
                                className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                            >
                                Mark as completed
                            </button>
                        </form>
                    ) : null}

                    {can_delete ? (
                        <form
                            method="POST"
                            action={routes?.destroy}
                            data-native="true"
                            onSubmit={(event) => {
                                if (!window.confirm('Delete this payout?')) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="_method" value="DELETE" />
                            <button
                                type="submit"
                                className="rounded-full bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500"
                            >
                                Delete payout
                            </button>
                        </form>
                    ) : null}
                </div>
            </div>
        </>
    );
}
