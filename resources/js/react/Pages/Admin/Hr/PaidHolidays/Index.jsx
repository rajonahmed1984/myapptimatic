import React from 'react';
import { Head, usePage } from '@inertiajs/react';

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

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Paid Holiday Calendar</div>
                    <div className="text-sm text-slate-500">Marked days are treated as fully paid days in salary calculations.</div>
                </div>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-5 flex flex-wrap items-end gap-2">
                    <div>
                        <label htmlFor="paidHolidayMonth" className="text-xs uppercase tracking-[0.2em] text-slate-500">Month</label>
                        <input id="paidHolidayMonth" type="month" name="month" defaultValue={selectedMonth} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Load</button>
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Current month</a>
                </form>

                <div className="max-w-4xl">
                    <div className="section-label">Add paid holiday</div>
                    <form method="POST" action={routes?.store} data-native="true" className="mt-4 grid gap-3 text-sm md:grid-cols-4">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="date" name="holiday_date" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required />
                        <select name="name" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm md:col-span-2" required>
                            <option value="">Select holiday type</option>
                            {holidayTypes.map((holidayType) => (
                                <option key={holidayType} value={holidayType}>{holidayType}</option>
                            ))}
                        </select>
                        <input name="note" placeholder="Optional note" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <div className="md:col-span-4">
                            <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save holiday</button>
                        </div>
                    </form>
                </div>

                <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Note</th>
                                <th className="px-3 py-2">Type</th>
                                <th className="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {holidays.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-3 py-4 text-center text-slate-500">No paid holidays found for this month.</td>
                                </tr>
                            ) : (
                                holidays.map((holiday) => (
                                    <tr key={holiday.id} className="border-b border-slate-100">
                                        <td className="px-3 py-2">{holiday.holiday_date}</td>
                                        <td className="px-3 py-2 font-semibold text-slate-900">{holiday.name}</td>
                                        <td className="px-3 py-2">{holiday.note || '--'}</td>
                                        <td className="px-3 py-2">{holiday.is_paid ? 'Paid' : 'Unpaid'}</td>
                                        <td className="px-3 py-2 text-right">
                                            <form method="POST" action={holiday.routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete paid holiday ${holiday.name}?`) && e.preventDefault()}>
                                                <input type="hidden" name="_token" value={csrf} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
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
