import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ attendances = [], selected_month = '', status_summary = {}, pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Attendance" />

            <div className="card p-6">
                <div>
                    <div className="section-label">Employee</div>
                    <div className="text-2xl font-semibold text-slate-900">Attendance Details</div>
                    <div className="text-sm text-slate-500">Daily attendance recorded by HR.</div>
                </div>

                <form method="GET" action={routes?.index} className="mt-4 flex flex-wrap items-end gap-2" data-native="true">
                    <div>
                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                        <input type="month" name="month" defaultValue={selected_month} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Reset</a>
                </form>

                <div className="mt-4 grid gap-3 md:grid-cols-4">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div className="text-xs uppercase tracking-[0.2em] text-slate-500">Present</div><div className="mt-1 text-xl font-semibold text-slate-900">{status_summary?.present || 0}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div className="text-xs uppercase tracking-[0.2em] text-slate-500">Absent</div><div className="mt-1 text-xl font-semibold text-slate-900">{status_summary?.absent || 0}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div className="text-xs uppercase tracking-[0.2em] text-slate-500">Leave</div><div className="mt-1 text-xl font-semibold text-slate-900">{status_summary?.leave || 0}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3"><div className="text-xs uppercase tracking-[0.2em] text-slate-500">Half Day</div><div className="mt-1 text-xl font-semibold text-slate-900">{status_summary?.half_day || 0}</div></div>
                </div>

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2">Date</th>
                                <th className="py-2">Status</th>
                                <th className="py-2">Note</th>
                                <th className="py-2">Recorded By</th>
                                <th className="py-2">Updated At</th>
                            </tr>
                        </thead>
                        <tbody>
                            {attendances.length === 0 ? (
                                <tr><td colSpan={5} className="py-3 text-center text-slate-500">No attendance records for this month.</td></tr>
                            ) : attendances.map((attendance, index) => (
                                <tr key={`${attendance.date_display}-${index}`} className="border-b border-slate-100">
                                    <td className="py-2">{attendance.date_display}</td>
                                    <td className="py-2">{attendance.status_label}</td>
                                    <td className="py-2">{attendance.note}</td>
                                    <td className="py-2">{attendance.recorder_name}</td>
                                    <td className="py-2">{attendance.updated_at_display}</td>
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
