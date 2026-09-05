import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('approved') || key.includes('accepted')) return 'bg-emerald-100 text-emerald-700';
    if (key.includes('rejected') || key.includes('declined')) return 'bg-rose-100 text-rose-700';
    return 'bg-amber-100 text-amber-700';
};

export default function Index({ leave_requests = [], leave_types = [], pagination = {}, routes = {} }) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const leaveTypeOptions = leave_types.map((type) => ({ value: String(type.id), label: type.name }));

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
                        <SearchableSelect
                            name="leave_type_id"
                            defaultValue={String(leaveTypeOptions[0]?.value || '')}
                            options={leaveTypeOptions}
                            className="mt-1"
                            placeholder="Select leave type"
                            error={errors?.leave_type_id}
                        />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" required className="ui-input mt-1" />
                        {errors?.start_date ? <div className="mt-1 text-xs text-rose-600">{errors.start_date}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="end_date" required className="ui-input mt-1" />
                        {errors?.end_date ? <div className="mt-1 text-xs text-rose-600">{errors.end_date}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Reason</label>
                        <input name="reason" className="ui-input mt-1" placeholder="Optional" />
                    </div>
                    <div className="md:col-span-4">
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Submit</button>
                    </div>
                </form>

                <div className="mt-4">
                    <DataTable
                        rows={leave_requests}
                        emptyMessage="No leave requests yet."
                        columns={[
                            { key: 'type', header: 'Type', render: (leave) => leave.type_name },
                            { key: 'dates', header: 'Dates', render: (leave) => `${leave.start_date_display} - ${leave.end_date_display}` },
                            { key: 'days', header: 'Days', render: (leave) => leave.total_days },
                            { key: 'status', header: 'Status', render: (leave) => leave.status_label },
                            { key: 'approved_at', header: 'Approved at', render: (leave) => leave.approved_at_display },
                        ]}
                        renderMobileCard={(leave) => (
                            <MobileCard
                                title={leave.type_name}
                                subtitle={`${leave.start_date_display} - ${leave.end_date_display}`}
                                badge={leave.status_label}
                                badgeColor={statusClass(leave.status_label)}
                                metrics={[
                                    { label: 'Days', value: leave.total_days },
                                    { label: 'Approved at', value: leave.approved_at_display || '--' },
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
