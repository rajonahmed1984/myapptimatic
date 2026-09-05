import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const statusClass = (status) => {
    const key = String(status || '').toLowerCase();
    if (['approved', 'accepted'].includes(key)) return 'bg-emerald-100 text-emerald-700';
    if (['rejected', 'declined'].includes(key)) return 'bg-rose-100 text-rose-700';
    return 'bg-amber-100 text-amber-700';
};

export default function Index({
    pageTitle = 'Leave Requests',
    leaveRequests = [],
    pagination = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-4 sm:p-6">
                <DataTable
                    rows={leaveRequests}
                    emptyMessage="No leave requests."
                    columns={[
                        { key: 'employee', header: 'Employee', render: (leave) => leave.employee_name },
                        { key: 'type', header: 'Type', render: (leave) => leave.leave_type_name },
                        { key: 'dates', header: 'Dates', render: (leave) => `${leave.start_date} - ${leave.end_date}` },
                        { key: 'days', header: 'Days', render: (leave) => leave.total_days },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (leave) => (
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusClass(leave.status)}`}>
                                    {leave.status}
                                </span>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right space-x-2',
                            render: (leave) => (
                                leave.is_pending ? (
                                    <>
                                        <form method="POST" action={leave.routes.approve} data-native="true" className="inline">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button className="text-xs text-emerald-700 hover:underline">Approve</button>
                                        </form>
                                        <form method="POST" action={leave.routes.reject} data-native="true" className="inline">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button className="text-xs text-rose-600 hover:underline">Reject</button>
                                        </form>
                                    </>
                                ) : <span className="text-xs text-slate-500">Locked</span>
                            ),
                        },
                    ]}
                    renderMobileCard={(leave) => (
                        <MobileCard
                            title={leave.employee_name}
                            subtitle={leave.leave_type_name}
                            badge={leave.status}
                            badgeColor={statusClass(leave.status)}
                            metrics={[
                                { label: 'Dates', value: `${leave.start_date} - ${leave.end_date}` },
                                { label: 'Days', value: leave.total_days },
                            ]}
                            actions={
                                leave.is_pending ? (
                                    <>
                                        <form method="POST" action={leave.routes.approve} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button
                                                type="submit"
                                                className="w-full py-2 px-3 rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition active:scale-95"
                                            >
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action={leave.routes.reject} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button
                                                type="submit"
                                                className="w-full py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                            >
                                                Reject
                                            </button>
                                        </form>
                                    </>
                                ) : (
                                    <span className="text-xs text-slate-500">Locked</span>
                                )
                            }
                        />
                    )}
                />

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                        <a href={pagination?.previous_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.previous_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Previous</a>
                        <a href={pagination?.next_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.next_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Next</a>
                    </div>
                ) : null}
            </div>
        </>
    );
}
