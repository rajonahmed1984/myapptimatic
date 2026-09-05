import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('present')) return 'bg-emerald-100 text-emerald-700';
    if (key.includes('absent')) return 'bg-rose-100 text-rose-700';
    if (key.includes('leave')) return 'bg-blue-100 text-blue-700';
    if (key.includes('half')) return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-600';
};

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
                        <input type="month" name="month" defaultValue={selected_month} className="ui-input mt-1" />
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

                <div className="mt-4">
                    <DataTable
                        rows={attendances}
                        rowKey={(attendance, index) => `${attendance.date_display}-${index}`}
                        emptyMessage="No attendance records for this month."
                        columns={[
                            { key: 'date', header: 'Date', render: (attendance) => attendance.date_display },
                            { key: 'status', header: 'Status', render: (attendance) => attendance.status_label },
                            { key: 'note', header: 'Note', render: (attendance) => attendance.note },
                            { key: 'recorded_by', header: 'Recorded By', render: (attendance) => attendance.recorder_name },
                            { key: 'updated_at', header: 'Updated At', render: (attendance) => attendance.updated_at_display },
                        ]}
                        renderMobileCard={(attendance) => (
                            <MobileCard
                                title={attendance.date_display}
                                subtitle={attendance.note}
                                badge={attendance.status_label}
                                badgeColor={statusClass(attendance.status_label)}
                                metrics={[
                                    { label: 'Recorded By', value: attendance.recorder_name || '--' },
                                    { label: 'Updated', value: attendance.updated_at_display || '--' },
                                ]}
                            />
                        )}
                    />
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
