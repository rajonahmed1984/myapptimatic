import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Leave Requests',
    leaveRequests = [],
    pagination = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Leave requests</div>
                </div>
            </div>

            <div className="card p-6">
                <div className="mt-2 overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">Employee</th>
                                <th className="py-2 px-3">Type</th>
                                <th className="py-2 px-3">Dates</th>
                                <th className="py-2 px-3">Days</th>
                                <th className="py-2 px-3">Status</th>
                                <th className="py-2 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leaveRequests.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="py-3 text-center text-slate-500">No leave requests.</td>
                                </tr>
                            ) : leaveRequests.map((leave) => (
                                <tr key={leave.id} className="border-b border-slate-100">
                                    <td className="py-2 px-3">{leave.employee_name}</td>
                                    <td className="py-2 px-3">{leave.leave_type_name}</td>
                                    <td className="py-2 px-3">{leave.start_date} - {leave.end_date}</td>
                                    <td className="py-2 px-3">{leave.total_days}</td>
                                    <td className="py-2 px-3">{leave.status}</td>
                                    <td className="py-2 px-3 text-right space-x-2">
                                        {leave.is_pending ? (
                                            <>
                                                <form method="POST" action={leave.routes.approve} data-native="true" className="inline">
                                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                                                    <button className="text-xs text-emerald-700 hover:underline">Approve</button>
                                                </form>
                                                <form method="POST" action={leave.routes.reject} data-native="true" className="inline">
                                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                                                    <button className="text-xs text-rose-600 hover:underline">Reject</button>
                                                </form>
                                            </>
                                        ) : <span className="text-xs text-slate-500">Locked</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

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
