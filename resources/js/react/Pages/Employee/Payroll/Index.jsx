import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ items = [], pagination = {} }) {
    return (
        <>
            <Head title="Payroll" />

            <div className="card p-6">
                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">Period</th>
                                <th className="px-3 py-2 text-right">Gross</th>
                                <th className="px-3 py-2 text-right">Bonus</th>
                                <th className="px-3 py-2 text-right">Penalty</th>
                                <th className="px-3 py-2 text-right">Advance</th>
                                <th className="px-3 py-2 text-right">Deduction</th>
                                <th className="px-3 py-2 text-right">Net Payable</th>
                                <th className="px-3 py-2 text-right">Paid</th>
                                <th className="px-3 py-2 text-right">Remaining</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Paid at</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.length === 0 ? (
                                <tr><td colSpan={11} className="py-3 text-center text-slate-500">No payroll items yet.</td></tr>
                            ) : items.map((item) => (
                                <tr key={item.id} className="border-b border-slate-100">
                                    <td className="px-3 py-2">{item.period_key}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.gross_pay || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.bonus || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.penalty || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.advance || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.deduction || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right font-semibold text-slate-900">{Number(item.net_payable || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2 text-right">{Number(item.paid || 0).toFixed(2)} {item.currency}</td>
                                    <td className={`px-3 py-2 text-right font-semibold ${Number(item.remaining || 0) > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>{Number(item.remaining || 0).toFixed(2)} {item.currency}</td>
                                    <td className="px-3 py-2">{item.status_label}</td>
                                    <td className="px-3 py-2">{item.paid_at_display}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {pagination?.last_page > 1 ? (
                    <div className="mt-4 flex items-center justify-between text-xs">
                        <span className="text-slate-500">Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}</span>
                        <div className="flex items-center gap-2">
                            {pagination.prev_page_url ? <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Previous</a> : null}
                            {pagination.next_page_url ? <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Next</a> : null}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
