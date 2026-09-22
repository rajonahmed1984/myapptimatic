import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';
import Pagination from '../../../../Components/Table/Pagination';
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


            <div className="card overflow-hidden">
                <div className="border-b border-slate-200 p-4 space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <form method="GET" action={routes?.index} data-native="true" className="flex flex-wrap items-end gap-2">
                            <div>
                                <label htmlFor="paidHolidayMonth" className="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                                <input id="paidHolidayMonth" type="month" name="month" defaultValue={selectedMonth} className="ui-input mt-1 w-40" />
                            </div>
                            <button type="submit" className="inline-flex items-center justify-center rounded-[10px] bg-teal-600 px-4 h-9 text-xs sm:text-sm font-semibold text-white hover:bg-teal-500 shadow-sm transition-colors whitespace-nowrap">Load</button>
                            <a href={routes?.index} data-native="true" className="inline-flex items-center justify-center rounded-[10px] border border-slate-300 bg-white px-4 h-9 text-xs sm:text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-sm transition-colors whitespace-nowrap">Current month</a>
                        </form>
                        <span className="text-xs text-slate-500 whitespace-nowrap">
                            Showing {pagination?.from ?? (holidays.length > 0 ? 1 : 0)} – {pagination?.to ?? holidays.length} of {pagination?.total ?? holidays.length} holidays
                        </span>
                    </div>

                    <form method="POST" action={routes?.store} data-native="true" className="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="_token" value={csrf} />
                        <div className="w-full">
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500 block mb-1">Add Paid Holiday</label>
                            <div className="flex flex-wrap gap-2 items-center">
                                <input type="date" name="start_date" className="rounded-xl border border-slate-300 bg-white px-3 py-1.5 h-9 text-xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500" required />
                                <input type="date" name="end_date" className="rounded-xl border border-slate-300 bg-white px-3 py-1.5 h-9 text-xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500" placeholder="End date" />
                                <SearchableSelect
                                    name="name"
                                    defaultValue=""
                                    options={holidayTypeOptions}
                                    className="w-48 text-xs"
                                    placeholder="Select holiday type"
                                    required
                                />
                                <input name="note" placeholder="Optional note" className="rounded-xl border border-slate-300 bg-white px-3 py-1.5 h-9 text-xs focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 w-32" />
                                <button className="inline-flex items-center justify-center rounded-full bg-teal-600 px-4 h-9 text-xs font-semibold text-white hover:bg-teal-500 shadow-sm transition whitespace-nowrap">Save holiday</button>
                            </div>
                            <div className="text-[10px] text-slate-400 mt-1">
                                For one day, use only start date. For a range, every date from start to end is saved.
                            </div>
                        </div>
                    </form>
                </div>

                <div>
                    <DataTable
                        framed={false}
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

                <div className="m-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Month Summary ({selectedMonth})</div>
                    <div className="mt-3 grid gap-3 text-sm text-slate-700 md:grid-cols-5">
                        <div><span className="font-semibold text-slate-900">Total month days:</span> {summary?.totalDaysInMonth}</div>
                        <div><span className="font-semibold text-slate-900">Paid holidays:</span> {summary?.paidHolidayCount}</div>
                        <div><span className="font-semibold text-slate-900">Working days:</span> {summary?.workingDays}</div>
                        <div><span className="font-semibold text-slate-900">8 hrs/day:</span> {summary?.expectedHoursFullTime} hrs</div>
                        <div><span className="font-semibold text-slate-900">4 hrs/day:</span> {summary?.expectedHoursPartTime} hrs</div>
                    </div>
                </div>

                <Pagination
                    pagination={pagination}
                    label="holidays"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
