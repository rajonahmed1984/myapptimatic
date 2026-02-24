import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Payroll',
    summary = {},
    workLogDaysThisMonth = 0,
    paidHolidaysThisMonth = 0,
    selectedPeriodKey = '',
    selectedStatus = '',
    selectedGeneratePeriod = '',
    generatePeriods = [],
    periods = [],
    pagination = {},
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Payroll periods</div>
                    <div className="text-sm text-slate-500">Generate, review, and finalize payroll based on work logs and compensation rules.</div>
                </div>
                <form method="POST" action={routes?.generate} data-native="true" className="flex items-center gap-2">
                    <input type="hidden" name="_token" value={token} />
                    <select name="period_key" defaultValue={selectedGeneratePeriod} className="w-40 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        {generatePeriods.map((periodOption) => (
                            <option key={periodOption.value} value={periodOption.value}>{periodOption.label}</option>
                        ))}
                    </select>
                    <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Generate</button>
                </form>
            </div>

            <div className="mb-6 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                <Metric title="Draft Periods" value={summary?.draft_periods || 0} />
                <Metric title="Finalized Periods" value={summary?.finalized_periods || 0} />
                <Metric title="To Pay Items" value={summary?.approved_items_to_pay || 0} />
                <Metric title="Paid Items" value={summary?.paid_items || 0} />
                <Metric title="Work Log Days (Month)" value={workLogDaysThisMonth || 0} />
                <Metric title="Paid Holidays (Month)" value={paidHolidaysThisMonth || 0} />
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-5 grid gap-3 md:grid-cols-4">
                    <div>
                        <label htmlFor="periodKeyFilter" className="text-xs uppercase tracking-[0.2em] text-slate-500">Period</label>
                        <input id="periodKeyFilter" type="month" name="period_key" defaultValue={selectedPeriodKey || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label htmlFor="periodStatusFilter" className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                        <select id="periodStatusFilter" name="status" defaultValue={selectedStatus || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            <option value="draft">Draft</option>
                            <option value="finalized">Finalized</option>
                        </select>
                    </div>
                    <div className="md:col-span-2 flex items-end gap-2">
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
                    </div>
                </form>

                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">Period</th>
                                <th className="py-2 px-3">Dates</th>
                                <th className="py-2 px-3">Status</th>
                                <th className="py-2 px-3">Items</th>
                                <th className="py-2 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {periods.length === 0 ? (
                                <tr><td colSpan={5} className="py-3 px-3 text-center text-slate-500">No payroll periods.</td></tr>
                            ) : periods.map((period) => (
                                <tr key={period.id} className="border-b border-slate-100">
                                    <td className="py-2 px-3">{period.period_key}</td>
                                    <td className="py-2 px-3">{period.start_date} - {period.end_date}</td>
                                    <td className="py-2 px-3">
                                        <span className={`rounded-full px-2 py-1 text-xs font-semibold ${period.status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                            {period.status.charAt(0).toUpperCase() + period.status.slice(1)}
                                        </span>
                                    </td>
                                    <td className="py-2 px-3">
                                        <div>Total: {period.items_count}</div>
                                        <div className="text-xs text-slate-500">To Pay: {period.approved_items_count} | Paid: {period.paid_items_count}</div>
                                    </td>
                                    <td className="py-2 px-3 text-right space-x-2">
                                        <a href={period.routes.show} data-native="true" className="text-xs text-slate-700 hover:underline">View</a>
                                        <a href={period.routes.export} data-native="true" className="text-xs text-slate-700 hover:underline">Export CSV</a>
                                        {period.is_draft ? (
                                            <>
                                                <a href={period.routes.edit} data-native="true" className="text-xs text-slate-700 hover:underline">Edit</a>
                                                <form method="POST" action={period.routes.destroy} data-native="true" className="inline">
                                                    <input type="hidden" name="_token" value={token} />
                                                    <input type="hidden" name="_method" value="DELETE" />
                                                    <button type="submit" className="text-xs text-rose-700 hover:underline">Delete</button>
                                                </form>
                                            </>
                                        ) : null}
                                        {period.is_draft && period.month_closed ? (
                                            <form method="POST" action={period.routes.finalize} data-native="true" className="inline">
                                                <input type="hidden" name="_token" value={token} />
                                                <button className="text-xs text-emerald-700 hover:underline">Finalize</button>
                                            </form>
                                        ) : null}
                                        {period.is_draft && !period.month_closed ? <span className="text-xs text-amber-700">Month not closed</span> : null}
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

function Metric({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-1 text-xl font-semibold text-slate-900">{value}</div>
        </div>
    );
}
