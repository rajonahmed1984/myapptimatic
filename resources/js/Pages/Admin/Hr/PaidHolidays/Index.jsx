import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

export default function Index({
    pageTitle = 'Paid Holidays',
    selectedMonth = '',
    holidayTypes = [],
    holidays = [],
    summary = {},
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const holidayTypeOptions = [
        { value: '', label: 'Select holiday type' },
        ...holidayTypes.map((holidayType) => ({ value: String(holidayType), label: holidayType })),
    ];

    return (
        <>
            <Head title={pageTitle} />


            <div className="card p-6">
                <div className="flex flex-wrap items-end justify-between gap-6 mb-5">
                    <form method="GET" action={routes?.index} data-native="true" className="flex flex-wrap items-end gap-2">
                        <div>
                            <label htmlFor="paidHolidayMonth" className="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                            <input id="paidHolidayMonth" type="month" name="month" defaultValue={selectedMonth} className="ui-input mt-1 w-40" />
                        </div>
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Current month</a>
                    </form>

                    <form method="POST" action={routes?.store} data-native="true" className="flex flex-wrap items-end gap-2 flex-1 justify-end">
                        <input type="hidden" name="_token" value={csrf} />
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500 block mb-1">Add Paid Holiday</label>
                            <div className="flex flex-wrap gap-2 items-center">
                                <input type="date" name="start_date" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" required />
                                <input type="date" name="end_date" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" placeholder="End date" />
                                <SearchableSelect
                                    name="name"
                                    defaultValue=""
                                    options={holidayTypeOptions}
                                    className="w-48 text-xs"
                                    placeholder="Select holiday type"
                                    required
                                />
                                <input name="note" placeholder="Optional note" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600 w-32" />
                                <button className="rounded-full bg-emerald-600 px-4 py-1.5 h-8 text-xs font-semibold text-white hover:bg-emerald-500">Save holiday</button>
                            </div>
                            <div className="text-[10px] text-slate-500 mt-1 text-right">
                                For one day, use only start date. For a range, every date from start to end is saved.
                            </div>
                        </div>
                    </form>
                </div>

                <div className="mt-6">
                    <DataTable
                        rows={holidays}
                        emptyMessage="No paid holidays found for this month."
                        columns={[
                            { key: 'date', header: 'Date', render: (holiday) => holiday.holiday_date },
                            { key: 'name', header: 'Name', cellClassName: 'font-semibold text-slate-900', render: (holiday) => holiday.name },
                            { key: 'note', header: 'Note', render: (holiday) => holiday.note || '--' },
                            { key: 'type', header: 'Type', render: (holiday) => (holiday.is_paid ? 'Paid' : 'Unpaid') },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (holiday) => (
                                    <form method="POST" action={holiday.routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete paid holiday ${holiday.name}?`) && e.preventDefault()}>
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                    </form>
                                ),
                            },
                        ]}
                        renderMobileCard={(holiday) => (
                            <MobileCard
                                title={holiday.name}
                                subtitle={holiday.holiday_date}
                                badge={holiday.is_paid ? 'Paid' : 'Unpaid'}
                                badgeColor={holiday.is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}
                                actions={
                                    <form method="POST" action={holiday.routes?.destroy} data-native="true" className="flex-1">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            className="w-full py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                            onClick={(e) => { if (!window.confirm(`Delete paid holiday ${holiday.name}?`)) e.preventDefault(); }}
                                        >
                                            Delete
                                        </button>
                                    </form>
                                }
                            >
                                {holiday.note ? <div className="text-xs text-slate-500">{holiday.note}</div> : null}
                            </MobileCard>
                        )}
                    />
                </div>

                <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Month Summary ({selectedMonth})</div>
                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-5">
                        <div><span className="font-semibold text-slate-900">Total month days:</span> {summary?.totalDaysInMonth}</div>
                        <div><span className="font-semibold text-slate-900">Paid holidays:</span> {summary?.paidHolidayCount}</div>
                        <div><span className="font-semibold text-slate-900">Working days:</span> {summary?.workingDays}</div>
                        <div><span className="font-semibold text-slate-900">8 hrs/day:</span> {summary?.expectedHoursFullTime} hrs</div>
                        <div><span className="font-semibold text-slate-900">4 hrs/day:</span> {summary?.expectedHoursPartTime} hrs</div>
                    </div>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                        {pagination.previous_url ? <a href={pagination.previous_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Previous</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>}
                        {pagination.next_url ? <a href={pagination.next_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Next</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>}
                    </div>
                ) : null}
            </div>
        </>
    );
}
