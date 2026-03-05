import React from 'react';
import { Head } from '@inertiajs/react';

export default function Commissions({ commissions = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Commissions" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Commissions</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Description</th>
                                <th className="px-3 py-2">Invoice</th>
                                <th className="px-3 py-2">Order</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {commissions.map((commission) => (
                                <tr key={commission.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2">{commission.description || '--'}</td>
                                    <td className="px-3 py-2">{commission.invoice_label}</td>
                                    <td className="px-3 py-2">{commission.order_label}</td>
                                    <td className="px-3 py-2">${Number(commission.amount || 0).toFixed(2)}</td>
                                    <td className="px-3 py-2">{commission.status_label}</td>
                                    <td className="px-3 py-2">{commission.created_at_display}</td>
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
