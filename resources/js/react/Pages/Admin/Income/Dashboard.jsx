import React from 'react';
import { Head } from '@inertiajs/react';

const money = (amount, symbol = '', code = '') => `${symbol}${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${code}`;

export default function Dashboard({
    pageTitle = 'Income Dashboard',
    categories = [],
    filters = {},
    totals = {},
    category_totals = [],
    currency = {},
    ai = {},
    trend = {},
    whmcs_errors = [],
    routes = {},
}) {
    const sources = Array.isArray(filters?.sources) ? filters.sources : [];

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6">
                <div className="section-label">Trend overview</div>
                <div className="mt-4 grid gap-4 md:grid-cols-4">
                    <Metric title="Total Income" value={money(totals?.total_amount, currency?.symbol, currency?.code)} />
                    <Metric title="Manual Income" value={money(totals?.manual_total, currency?.symbol, currency?.code)} />
                    <Metric title="System Income" value={money(totals?.system_total, currency?.symbol, currency?.code)} />
                    <Metric title="Credit Settlement" value={money(totals?.credit_settlement_total, currency?.symbol, currency?.code)} />
                </div>
                <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                    Trend points: {Array.isArray(trend?.labels) ? trend.labels.length : 0}
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Filters</div>
                <form method="GET" action={routes?.dashboard} data-native="true" className="mt-4 grid gap-3 text-sm md:grid-cols-4">
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={filters?.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="end_date" defaultValue={filters?.end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Category</label>
                        <select name="category_id" defaultValue={filters?.category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
                            <option value="">All</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>{category.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-7 flex items-center gap-2">
                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Apply</button>
                        <a href={routes?.dashboard} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white">Reset</a>
                    </div>
                    <div className="md:col-span-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Sources</div>
                        <div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="manual" defaultChecked={sources.includes('manual')} /> Manual</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="system" defaultChecked={sources.includes('system')} /> System</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="credit_settlement" defaultChecked={sources.includes('credit_settlement')} /> Credit Settlement</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="carrothost" defaultChecked={sources.includes('carrothost')} /> CarrotHost (WHMCS)</label>
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div className="card p-6">
                    <div className="section-label">Category totals</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        {category_totals.length === 0 ? <div className="text-sm text-slate-500">No income entries yet.</div> : category_totals.map((row) => (
                            <div key={row.category_id ?? row.name} className="flex items-center justify-between">
                                <div>{row.name}</div>
                                <div className="font-semibold text-slate-900">{money(row.total, currency?.symbol, currency?.code)}</div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">Google AI Summary</div>
                    <a href={`${routes?.dashboard}?ai=refresh`} data-native="true" className="mt-2 inline-flex text-xs font-semibold text-emerald-700">Refresh AI</a>
                    <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 whitespace-pre-line">
                        {ai?.summary || (ai?.error ? `AI summary unavailable: ${ai.error}` : 'AI summary is not available yet.')}
                    </div>
                </div>
            </div>

            {whmcs_errors.length > 0 ? (
                <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <div className="font-semibold text-amber-900">WHMCS warnings</div>
                    <ul className="mt-2 list-disc pl-5">
                        {whmcs_errors.map((error, index) => <li key={index}>{error}</li>)}
                    </ul>
                </div>
            ) : null}
        </>
    );
}

function Metric({ title, value }) {
    return (
        <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
            <div>
                <div className="text-xs text-slate-500">{title}</div>
                <div className="text-2xl font-semibold text-slate-900">{value}</div>
            </div>
        </div>
    );
}
