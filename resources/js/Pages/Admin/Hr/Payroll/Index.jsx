import React from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

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
    const generatePeriodOptions = React.useMemo(() => generatePeriods.map((periodOption) => ({ value: String(periodOption.value), label: periodOption.label })), [generatePeriods]);
    const statusFilterOptions = [
        { value: '', label: 'All' },
        { value: 'draft', label: 'Draft' },
        { value: 'finalized', label: 'Finalized' },
    ];

    return (
        <>
            <Head title={pageTitle} />


            <div className="mb-6 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
                <Metric title="Draft Periods" value={summary?.draft_periods || 0} />
                <Metric title="Finalized Periods" value={summary?.finalized_periods || 0} />
                <Metric title="To Pay Items" value={summary?.approved_items_to_pay || 0} />
                <Metric title="Paid Items" value={summary?.paid_items || 0} />
                <Metric title="Work Log Days (Month)" value={workLogDaysThisMonth || 0} />
                <Metric title="Paid Holidays (Month)" value={paidHolidaysThisMonth || 0} />
            </div>

            <div className="card p-6">
                <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                    <form method="GET" action={routes?.index} data-native="true" className="flex flex-wrap items-end gap-3">
                        <div>
                            <label htmlFor="periodKeyFilter" className="text-xs uppercase tracking-[0.2em] text-slate-500">Period</label>
                            <input id="periodKeyFilter" type="month" name="period_key" defaultValue={selectedPeriodKey || ''} className="ui-input mt-1 w-40" />
                        </div>
                        <div>
                            <label htmlFor="periodStatusFilter" className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</label>
                            <SearchableSelect
                                name="status"
                                defaultValue={String(selectedStatus || '')}
                                options={statusFilterOptions}
                                className="mt-1 w-40"
                                placeholder="All"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Apply filter</button>
                            <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Reset</a>
                        </div>
                    </form>

                    <form method="POST" action={routes?.generate} data-native="true" className="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="_token" value={token} />
                        <div>
                            <label className="text-xs uppercase tracking-[0.2em] text-slate-500 block mb-1">Generate Period</label>
                            <SearchableSelect
                                name="period_key"
                                defaultValue={String(selectedGeneratePeriod || '')}
                                options={generatePeriodOptions}
                                className="w-40"
                                placeholder="Select period"
                            />
                        </div>
                        <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Generate</button>
                    </form>
                </div>

                <DataTable
                    rows={periods}
                    emptyMessage="No payroll periods."
                    columns={[
                        { key: 'id', header: 'ID', render: (period) => period.id },
                        { key: 'period', header: 'Period', render: (period) => period.period_key },
                        { key: 'dates', header: 'Dates', render: (period) => `${period.start_date} - ${period.end_date}` },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (period) => (
                                <span className={`rounded-full px-2 py-1 text-xs font-semibold ${period.status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                    {period.status.charAt(0).toUpperCase() + period.status.slice(1)}
                                </span>
                            ),
                        },
                        {
                            key: 'items',
                            header: 'Items',
                            render: (period) => (
                                <>
                                    <div>Total: {period.items_count}</div>
                                    <div className="text-xs text-slate-500">To Pay: {period.approved_items_count} | Paid: {period.paid_items_count}</div>
                                </>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right space-x-2',
                            render: (period) => (
                                <>
                                    <a href={period.routes.show} data-native="true" className="text-xs text-slate-700 hover:underline">View</a>{' '}
                                    <a href={period.routes.export} data-native="true" className="text-xs text-slate-700 hover:underline">Export CSV</a>
                                    {period.is_draft ? (
                                        <>
                                            {' '}<a href={period.routes.edit} data-native="true" className="text-xs text-slate-700 hover:underline">Edit</a>
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
                                </>
                            ),
                        },
                    ]}
                    renderMobileCard={(period) => (
                        <MobileCard
                            title={period.period_key}
                            subtitle={`${period.start_date} - ${period.end_date}`}
                            badge={period.status.charAt(0).toUpperCase() + period.status.slice(1)}
                            badgeColor={period.status === 'draft' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}
                            metrics={[
                                { label: 'Total Items', value: period.items_count },
                                { label: 'To Pay / Paid', value: `${period.approved_items_count} / ${period.paid_items_count}` },
                            ]}
                            actions={
                                <div className="flex flex-wrap gap-2 w-full">
                                    <a
                                        href={period.routes.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={period.routes.export}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        Export CSV
                                    </a>
                                    {period.is_draft ? (
                                        <a
                                            href={period.routes.edit}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                        >
                                            Edit
                                        </a>
                                    ) : null}
                                    {period.is_draft && period.month_closed ? (
                                        <form method="POST" action={period.routes.finalize} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={token} />
                                            <button className="w-full py-2 px-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition active:scale-95">Finalize</button>
                                        </form>
                                    ) : null}
                                </div>
                            }
                        >
                            {period.is_draft && !period.month_closed ? <div className="text-xs text-amber-700">Month not closed</div> : null}
                        </MobileCard>
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

function Metric({ title, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-1 text-xl font-semibold text-slate-900">{value}</div>
        </div>
    );
}
