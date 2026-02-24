import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    daily_logs = [],
    eligible = true,
    selected_month = '',
    subtotal_estimated = 0,
    subtotal_currency = 'BDT',
    pagination = {},
    routes = {},
}) {
    return (
        <>
            <Head title="Work Logs" />

            <div className="card p-6">
                <div className="flex items-center justify-between">
                    <form method="GET" action={routes?.index} className="mt-4 flex flex-wrap items-end gap-2" data-native="true">
                        <input type="month" name="month" defaultValue={selected_month} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Reset</a>
                    </form>
                </div>

                {!eligible ? (
                    <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Work session tracking is enabled for remote full-time/part-time employees only.
                    </div>
                ) : null}

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2">Date</th>
                                <th className="py-2">Sessions</th>
                                <th className="py-2">First Start</th>
                                <th className="py-2">Last Activity</th>
                                <th className="py-2 text-right">Active Time</th>
                                <th className="py-2 text-right">Required</th>
                                <th className="py-2 text-right">Coverage</th>
                                <th className="py-2 text-right">Est. Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            {daily_logs.length === 0 ? (
                                <tr><td colSpan={8} className="py-3 text-center text-slate-500">No work logs yet.</td></tr>
                            ) : daily_logs.map((log, index) => (
                                <React.Fragment key={`${log.work_date_display}-${index}`}>
                                    <tr className="border-b border-slate-100">
                                        <td className="py-2">{log.work_date_display}</td>
                                        <td className="py-2">{log.sessions_count}</td>
                                        <td className="py-2">{log.first_started_at}</td>
                                        <td className="py-2">{log.last_activity_at}</td>
                                        <td className="py-2 text-right">{log.active_time_label}</td>
                                        <td className="py-2 text-right">{log.required_time_label}</td>
                                        <td className="py-2 text-right">{log.coverage_percent}%</td>
                                        <td className="py-2 text-right">{log.currency} {Number(log.estimated_amount || 0).toFixed(2)}</td>
                                    </tr>
                                    {index === daily_logs.length - 1 ? (
                                        <tr className="border-t border-slate-300 bg-slate-50/70">
                                            <td colSpan={7} className="py-2 text-right font-semibold text-slate-800">Est. Salary Subtotal (This Page)</td>
                                            <td className="py-2 text-right font-semibold text-slate-900">{subtotal_currency} {Number(subtotal_estimated || 0).toFixed(2)}</td>
                                        </tr>
                                    ) : null}
                                </React.Fragment>
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
