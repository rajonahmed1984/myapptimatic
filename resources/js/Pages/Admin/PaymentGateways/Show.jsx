import React from 'react';
import { Head } from '@inertiajs/react';

const statusClass = (active) =>
    active ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200';

export default function Show({ pageTitle = 'Gateway Ledger', gateway = {}, entries = [], routes = {} }) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">{gateway?.name || 'Payment Gateway'}</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {gateway?.details_display || '--'}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(
                            gateway?.is_active,
                        )}`}
                    >
                        {gateway?.is_active ? 'Active' : 'Inactive'}
                    </span>
                    <a href={routes?.index} data-native="true" className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700">
                        Back
                    </a>
                    <a href={routes?.edit} data-native="true" className="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white">
                        Edit
                    </a>
                </div>
            </div>

            <div className="grid gap-3 md:grid-cols-4">
                <div className="rounded-xl border border-slate-200 bg-white p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Transactions</div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">{gateway?.financial_summary?.transactions_count || 0}</div>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Tk In</div>
                    <div className="mt-2 text-lg font-semibold text-emerald-700">{gateway?.financial_summary?.tk_in_display || '0.00'}</div>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Tk Out</div>
                    <div className="mt-2 text-lg font-semibold text-rose-700">{gateway?.financial_summary?.tk_out_display || '0.00'}</div>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Last Activity</div>
                    <div className="mt-2 text-lg font-semibold text-slate-900">{gateway?.financial_summary?.last_activity_display || '--'}</div>
                </div>
            </div>

            <div className="mt-4 card overflow-x-auto">
                <table className="w-full min-w-[980px] text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Date</th>
                            <th className="px-4 py-3">Source</th>
                            <th className="px-4 py-3">Type</th>
                            <th className="px-4 py-3">Party</th>
                            <th className="px-4 py-3">Reference</th>
                            <th className="px-4 py-3">Description</th>
                            <th className="px-4 py-3">Tk In</th>
                            <th className="px-4 py-3">Tk Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        {entries.length > 0 ? (
                            entries.map((entry) => (
                                <tr key={entry.id} className="border-b border-slate-100">
                                    <td className="whitespace-nowrap px-4 py-3">{entry.date_display || '--'}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                entry.source === 'Gateway' ? 'bg-indigo-100 text-indigo-700' : 'bg-amber-100 text-amber-700'
                                            }`}
                                        >
                                            {entry.source}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">{entry.type_label || '--'}</td>
                                    <td className="px-4 py-3">{entry.party || '--'}</td>
                                    <td className="px-4 py-3">{entry.reference || '--'}</td>
                                    <td className="px-4 py-3">{entry.description || '--'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 font-medium text-emerald-700">{entry.in_display || '-'}</td>
                                    <td className="whitespace-nowrap px-4 py-3 font-medium text-rose-700">{entry.out_display || '-'}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                                    No ledger entries found for this gateway.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
