import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ payouts = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="My Payouts" />

            <div className="card space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="section-label">Commissions</div>
                        <h1 className="text-2xl font-semibold text-slate-900">Payout history</h1>
                        <div className="text-sm text-slate-500">Read-only view of your payouts.</div>
                    </div>
                    <a href={routes?.dashboard} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase text-slate-500">
                                    <th className="px-2 py-2">ID</th>
                                    <th className="px-2 py-2">Type</th>
                                    <th className="px-2 py-2">Amount</th>
                                    <th className="px-2 py-2">Status</th>
                                    <th className="px-2 py-2">Method</th>
                                    <th className="px-2 py-2">Paid at</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payouts.length === 0 ? (
                                    <tr><td colSpan={6} className="px-2 py-3 text-slate-500">No payouts yet.</td></tr>
                                ) : payouts.map((payout) => (
                                    <tr key={payout.id} className="border-t border-slate-200">
                                        <td className="px-2 py-2">#{payout.id}</td>
                                        <td className="px-2 py-2">{payout.type_label}</td>
                                        <td className="px-2 py-2">{Number(payout.total_amount || 0).toFixed(2)} {payout.currency}</td>
                                        <td className="px-2 py-2">{payout.status_label}</td>
                                        <td className="px-2 py-2">{payout.payout_method}</td>
                                        <td className="px-2 py-2">{payout.paid_at_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {pagination?.last_page > 1 ? (
                        <div className="mt-3 flex items-center justify-between text-xs">
                            <span className="text-slate-500">Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}</span>
                            <div className="flex items-center gap-2">
                                {pagination.prev_page_url ? <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Previous</a> : null}
                                {pagination.next_page_url ? <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Next</a> : null}
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
