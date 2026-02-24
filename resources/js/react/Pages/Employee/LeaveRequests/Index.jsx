import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({ leave_requests = [], leave_types = [], pagination = {}, routes = {} }) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};

    return (
        <>
            <Head title="Leave Requests" />

            <div className="card p-6">
                <div className="section-label">Employee</div>
                <div className="text-2xl font-semibold text-slate-900">Request leave</div>
                <div className="text-sm text-slate-500">Submit a new request and track approvals.</div>

                <form method="POST" action={routes?.store} className="mt-4 grid gap-3 md:grid-cols-4 text-sm" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <div>
                        <label className="text-xs text-slate-500">Leave type</label>
                        <select name="leave_type_id" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                            {leave_types.map((type) => <option key={type.id} value={type.id}>{type.name}</option>)}
                        </select>
                        {errors?.leave_type_id ? <div className="mt-1 text-xs text-rose-600">{errors.leave_type_id}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="date" name="start_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                        {errors?.start_date ? <div className="mt-1 text-xs text-rose-600">{errors.start_date}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="date" name="end_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                        {errors?.end_date ? <div className="mt-1 text-xs text-rose-600">{errors.end_date}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Reason</label>
                        <input name="reason" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Optional" />
                    </div>
                    <div className="md:col-span-4">
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Submit</button>
                    </div>
                </form>

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2">Type</th>
                                <th className="py-2">Dates</th>
                                <th className="py-2">Days</th>
                                <th className="py-2">Status</th>
                                <th className="py-2">Approved at</th>
                            </tr>
                        </thead>
                        <tbody>
                            {leave_requests.length === 0 ? (
                                <tr><td colSpan={5} className="py-3 text-center text-slate-500">No leave requests yet.</td></tr>
                            ) : leave_requests.map((leave) => (
                                <tr key={leave.id} className="border-b border-slate-100">
                                    <td className="py-2">{leave.type_name}</td>
                                    <td className="py-2">{leave.start_date_display} - {leave.end_date_display}</td>
                                    <td className="py-2">{leave.total_days}</td>
                                    <td className="py-2">{leave.status_label}</td>
                                    <td className="py-2">{leave.approved_at_display}</td>
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
