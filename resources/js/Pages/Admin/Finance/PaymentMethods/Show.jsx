import React from 'react';
import { Head } from '@inertiajs/react';

export default function Show({ pageTitle = 'Payment Method Ledger', payment_method = {}, summary = {}, rows = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-6xl space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">{payment_method?.name || '--'}</h1>
                            <p className="text-sm text-slate-500">Code: {payment_method?.code || '--'}</p>
                        </div>
                        <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                            Back to methods
                        </a>
                    </div>
                    <p className="text-sm text-slate-700">Total Entries: {summary?.total_entries || 0}</p>
                    <p className="text-sm text-slate-700">Total Amount: {summary?.total_amount || '0.00'}</p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-4">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2">Type</th>
                                    <th className="px-3 py-2">Party</th>
                                    <th className="px-3 py-2">Reference</th>
                                    <th className="px-3 py-2">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, idx) => (
                                    <tr key={`${row.date_display}-${idx}`} className="border-t border-slate-100">
                                        <td className="px-3 py-2">{row.date_display}</td>
                                        <td className="px-3 py-2">{row.type}</td>
                                        <td className="px-3 py-2">{row.party}</td>
                                        <td className="px-3 py-2">{row.reference}</td>
                                        <td className="px-3 py-2 font-semibold">{row.amount_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {pagination?.has_pages ? (
                        <div className="mt-4 flex items-center justify-between text-sm">
                            <span>
                                {pagination?.previous_url ? (
                                    <a href={pagination.previous_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                        Previous
                                    </a>
                                ) : (
                                    <span className="text-slate-400">Previous</span>
                                )}
                            </span>
                            <span>
                                {pagination?.next_url ? (
                                    <a href={pagination.next_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                        Next
                                    </a>
                                ) : (
                                    <span className="text-slate-400">Next</span>
                                )}
                            </span>
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
