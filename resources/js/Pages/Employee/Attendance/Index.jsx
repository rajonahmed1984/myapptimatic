import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('present')) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (key.includes('absent')) return 'border-rose-200 bg-rose-50 text-rose-700';
    if (key.includes('leave')) return 'border-blue-200 bg-blue-50 text-blue-700';
    if (key.includes('half')) return 'border-amber-200 bg-amber-50 text-amber-700';
    return 'border-slate-200 bg-slate-100 text-slate-600';
};

export default function Index({ attendances = [], selected_month = '', status_summary = {}, pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Attendance" />

            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Employee</div>
                        <h1 className="text-2xl font-semibold text-slate-900">Attendance Details</h1>
                        <div className="text-sm text-slate-500">Daily attendance recorded by HR.</div>
                    </div>

                    <form method="GET" action={routes?.index} className="flex flex-wrap items-center gap-2" data-native="true">
                        <div className="w-56 sm:w-64 max-w-full">
                            <input type="month" name="month" defaultValue={selected_month} className="ui-input h-9" />
                        </div>
                        <button type="submit" className="inline-flex items-center justify-center rounded-[10px] bg-emerald-600 px-4 h-9 text-xs sm:text-sm font-semibold text-white hover:bg-emerald-500 shadow-sm transition-colors whitespace-nowrap">Apply filter</button>
                        <a href={routes?.index} data-native="true" className="inline-flex items-center justify-center rounded-[10px] border border-slate-300 bg-white px-4 h-9 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors whitespace-nowrap">Reset</a>
                    </form>
                </div>

                <div className="grid gap-3 grid-cols-2 md:grid-cols-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase tracking-wider text-slate-500 font-semibold">Present</div>
                        <div className="mt-1 text-2xl font-bold text-emerald-600">{status_summary?.present || 0}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase tracking-wider text-slate-500 font-semibold">Absent</div>
                        <div className="mt-1 text-2xl font-bold text-rose-600">{status_summary?.absent || 0}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase tracking-wider text-slate-500 font-semibold">Leave</div>
                        <div className="mt-1 text-2xl font-bold text-blue-600">{status_summary?.leave || 0}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="text-xs uppercase tracking-wider text-slate-500 font-semibold">Half Day</div>
                        <div className="mt-1 text-2xl font-bold text-amber-600">{status_summary?.half_day || 0}</div>
                    </div>
                </div>

                <DataTable
                    rows={attendances}
                    rowKey={(attendance, index) => `${attendance.date_display}-${index}`}
                    emptyMessage="No attendance records for this month."
                    columns={[
                        { key: 'date', header: 'Date', render: (attendance) => attendance.date_display },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (attendance) => (
                                <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(attendance.status_label)}`}>
                                    {attendance.status_label}
                                </span>
                            ),
                        },
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

                {pagination?.last_page > 1 ? (
                    <div className="mt-4 flex items-center justify-between text-xs px-2">
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
