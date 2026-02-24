import React from 'react';
import { Head } from '@inertiajs/react';

export default function Payouts({ payouts = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Payouts" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Payouts</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Payout #</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Method</th>
                                <th className="px-3 py-2">Processed</th>
                                <th className="px-3 py-2">Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payouts.map((payout) => (
                                <tr key={payout.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2">{payout.payout_number || '--'}</td>
                                    <td className="px-3 py-2">${Number(payout.amount || 0).toFixed(2)}</td>
                                    <td className="px-3 py-2">{payout.status_label}</td>
                                    <td className="px-3 py-2">{payout.payment_method || '--'}</td>
                                    <td className="px-3 py-2">{payout.processed_at_display}</td>
                                    <td className="px-3 py-2">{payout.completed_at_display}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination.last_page > 1 ? (
                    <div className="mt-4 flex items-center gap-2 text-xs">
                        {pagination.prev_page_url ? (
                            <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                Previous
                            </a>
                        ) : null}
                        {pagination.next_page_url ? (
                            <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                Next
                            </a>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </>
    );
}
