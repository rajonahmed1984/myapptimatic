import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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
                        <input type="month" name="month" defaultValue={selected_month} className="ui-input mt-1" />
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Reset</a>
                    </form>
                </div>

                {!eligible ? (
                    <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Work session tracking is enabled for remote full-time/part-time employees only.
                    </div>
                ) : null}

                <div className="mt-4">
                    <DataTable
                        rows={daily_logs}
                        rowKey={(log, index) => `${log.work_date_display}-${index}`}
                        emptyMessage="No work logs yet."
                        columns={[
                            { key: 'date', header: 'Date', render: (log) => log.work_date_display },
                            { key: 'sessions', header: 'Sessions', render: (log) => log.sessions_count },
                            { key: 'first_start', header: 'First Start', render: (log) => log.first_started_at },
                            { key: 'last_activity', header: 'Last Activity', render: (log) => log.last_activity_at },
                            { key: 'active_time', header: 'Active Time', headerClassName: 'text-right', cellClassName: 'text-right', render: (log) => log.active_time_label },
                            { key: 'required', header: 'Required', headerClassName: 'text-right', cellClassName: 'text-right', render: (log) => log.required_time_label },
                            { key: 'coverage', header: 'Coverage', headerClassName: 'text-right', cellClassName: 'text-right', render: (log) => `${log.coverage_percent}%` },
                            {
                                key: 'estimated',
                                header: 'Est. Salary',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (log) => `${log.currency} ${Number(log.estimated_amount || 0).toFixed(2)}`,
                            },
                        ]}
                        renderMobileCard={(log) => (
                            <MobileCard
                                title={log.work_date_display}
                                subtitle={`Sessions: ${log.sessions_count} · ${log.first_started_at || '--'} to ${log.last_activity_at || '--'}`}
                                metrics={[
                                    { label: 'Active Time', value: log.active_time_label },
                                    { label: 'Required', value: log.required_time_label },
                                    { label: 'Coverage', value: `${log.coverage_percent}%` },
                                    { label: 'Est. Salary', value: `${log.currency} ${Number(log.estimated_amount || 0).toFixed(2)}` },
                                ]}
                            />
                        )}
                    />

                    {daily_logs.length > 0 ? (
                        <div className="mt-3 flex items-center justify-between rounded-xl border border-slate-300 bg-slate-50/70 px-4 py-2.5 text-sm">
                            <span className="font-semibold text-slate-800">Est. Salary Subtotal (This Page)</span>
                            <span className="font-semibold text-slate-900">{subtotal_currency} {Number(subtotal_estimated || 0).toFixed(2)}</span>
                        </div>
                    ) : null}
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
