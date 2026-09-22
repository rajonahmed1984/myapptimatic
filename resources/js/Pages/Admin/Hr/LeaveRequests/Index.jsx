import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../../Components/Table/DataTable';
import Pagination from '../../../../Components/Table/Pagination';
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
    const [searchTerm, setSearchTerm] = useState('');

    const filteredRequests = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();
        if (!query) return leaveRequests;
        return leaveRequests.filter(
            (r) =>
                String(r.employee_name || '').toLowerCase().includes(query) ||
                String(r.leave_type_name || '').toLowerCase().includes(query) ||
                String(r.status || '').toLowerCase().includes(query)
        );
    }, [leaveRequests, searchTerm]);

    const total = Number(pagination?.total ?? filteredRequests.length);

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Search leave requests..."
                            className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {pagination?.from ?? (total > 0 ? 1 : 0)} – {pagination?.to ?? total} of {total} leave requests
                        </span>
                    </div>
                </div>

                <DataTable
                    framed={false}
                    rows={filteredRequests}
                    emptyMessage="No leave requests found."
                    columns={[
                        { key: 'employee', header: 'Employee', cellClassName: 'font-semibold text-slate-900', render: (leave) => leave.employee_name },
                        { key: 'type', header: 'Type', render: (leave) => leave.leave_type_name },
                        { key: 'dates', header: 'Dates', cellClassName: 'text-slate-600', render: (leave) => `${leave.start_date} - ${leave.end_date}` },
                        { key: 'days', header: 'Days', cellClassName: 'font-medium text-slate-700', render: (leave) => leave.total_days },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (leave) => (
                                <span className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ${statusClass(leave.status)}`}>
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
                                    <div className="inline-flex items-center justify-end gap-2 whitespace-nowrap">
                                        <form method="POST" action={leave.routes?.approve} data-native="true" className="inline">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button className="text-xs font-semibold text-emerald-700 hover:underline">Approve</button>
                                        </form>
                                        <span className="text-slate-300">·</span>
                                        <form method="POST" action={leave.routes?.reject} data-native="true" className="inline">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button className="text-xs font-semibold text-rose-600 hover:underline">Reject</button>
                                        </form>
                                    </div>
                                ) : <span className="text-xs text-slate-400">Locked</span>
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
                                        <form method="POST" action={leave.routes?.approve} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={csrfToken()} />
                                            <button
                                                type="submit"
                                                className="w-full py-2 px-3 rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition active:scale-95"
                                            >
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action={leave.routes?.reject} data-native="true" className="flex-1">
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

                <Pagination
                    pagination={pagination}
                    label="leave requests"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
