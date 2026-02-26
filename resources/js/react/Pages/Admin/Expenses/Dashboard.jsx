import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

const PERIOD_OPTIONS = [
    { key: 'day', label: 'Daily' },
    { key: 'week', label: 'Weekly' },
    { key: 'month', label: 'Monthly' },
];

const SERIES_META = {
    total: { label: 'Total Expenses', color: '#ef4444', dot: '#dc2626' },
    manual: { label: 'Manual', color: '#0ea5e9', dot: '#0284c7' },
    salary: { label: 'Salary', color: '#f59e0b', dot: '#d97706' },
    contract: { label: 'Contract', color: '#6366f1', dot: '#4f46e5' },
    sales: { label: 'Sales Rep', color: '#14b8a6', dot: '#0f766e' },
};

function money(amount, symbol = '', code = '') {
    return `${symbol}${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${code}`;
}

function buildPolyline(values, maxValue) {
    const list = Array.isArray(values) ? values.map((value) => Number(value || 0)) : [];
    if (list.length < 2) {
        return '';
    }
    const max = Math.max(1, Number(maxValue || 0));
    return list.map((value, index) => {
        const x = (index / (list.length - 1)) * 100;
        const y = 56 - (Number(value || 0) / max) * 52;
        return `${x},${y}`;
    }).join(' ');
}

function changeText(value) {
    if (value === null || value === undefined) return 'N/A';
    const n = Number(value);
    return `${n >= 0 ? '+' : ''}${n.toFixed(1)}%`;
}

function BreakdownCard({ title, items = [], emptyText, getKey, getLabel, currencyCode, currencySymbol }) {
    return (
        <div className="card min-h-[300px] p-5">
            <div className="section-label">{title}</div>
            <div className="mt-4 space-y-2">
                {items.length > 0 ? (
                    <div className="max-h-[220px] space-y-2 overflow-auto pr-1">
                        {items.map((item, index) => (
                            <div key={getKey(item, index)} className="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2.5">
                                <div className="min-w-0 pr-3 text-sm font-medium text-slate-700">
                                    <div className="truncate">{getLabel(item)}</div>
                                </div>
                                <div className="whitespace-nowrap text-sm font-semibold tabular-nums text-slate-900">{money(item?.total, currencySymbol, currencyCode)}</div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">{emptyText}</div>
                )}
            </div>
        </div>
    );
}

function StatusMetric({ data = {}, currencyCode, currencySymbol }) {
    const change = data?.change_percent;
    const tone = change === null || change === undefined ? 'text-slate-500' : Number(change) >= 0 ? 'text-emerald-600' : 'text-rose-600';
    const icon = (data?.label || 'E').slice(0, 1).toUpperCase();

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-700 text-xs font-semibold text-white">{icon}</div>
                <div className="min-w-0">
                    <div className="truncate text-xs text-slate-500">{data?.label || '--'}</div>
                    <div className="whitespace-nowrap text-2xl font-semibold leading-7 text-slate-900">{money(data?.amount, currencySymbol, currencyCode)}</div>
                    <div className={`truncate text-xs font-semibold ${tone}`}>{changeText(change)} {data?.comparison_label || ''}</div>
                </div>
            </div>
        </div>
    );
}

export default function Dashboard({
    pageTitle = 'Expense Dashboard',
    filters = {},
    expenseTotal = 0,
    expenseBySource = {},
    expenseStatus = {},
    categoryTotals = [],
    employeeTotals = [],
    salesRepTotals = [],
    yearlyTotal = 0,
    topCategories = [],
    periodSeries = {},
    categories = [],
    peopleOptions = [],
    currencyCode = 'BDT',
    currencySymbol = '',
    aiSummary = null,
    aiError = null,
    routes = {},
}) {
    const [period, setPeriod] = useState('day');
    const [visible, setVisible] = useState({ total: true, manual: true, salary: true, contract: false, sales: false });
    const sourceSelections = Array.isArray(filters?.sources) ? filters.sources : [];
    const topCategory = (topCategories?.[0] ?? categoryTotals?.[0]) || null;

    const activeSeries = periodSeries?.[period] || { labels: [], total: [], manual: [], salary: [], contract: [], sales: [] };
    const seriesMax = useMemo(() => {
        const keys = ['total', 'manual', 'salary', 'contract', 'sales'];
        const all = keys.flatMap((key) => (Array.isArray(activeSeries?.[key]) ? activeSeries[key].map((v) => Number(v || 0)) : []));
        return Math.max(1, ...all, 0);
    }, [activeSeries]);
    const hasChartData = Array.isArray(activeSeries?.labels) && activeSeries.labels.length > 0;

    const toggleSeries = (key) => setVisible((prev) => ({ ...prev, [key]: !prev[key] }));

    return (
        <>
            <Head title={pageTitle} />

            <div className="card bg-gradient-to-br from-[#eef8fb] via-white to-[#f3f7ff] p-4 md:p-5">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Expense Controls</div>
                        <div className="mt-1 text-xs text-slate-500">Filter expense statement by date, category, and person.</div>
                    </div>
                    <div className="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">
                        Filtered total: {money(expenseTotal, currencySymbol, currencyCode)}
                    </div>
                </div>

                <form method="GET" action={routes?.index} data-native="true" className="mt-3 grid gap-2 text-sm md:grid-cols-4 lg:grid-cols-5">
                    <div>
                        <label className="text-xs text-slate-500">Start date</label>
                        <input type="date" name="start_date" defaultValue={filters?.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">End date</label>
                        <input type="date" name="end_date" defaultValue={filters?.end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Category</label>
                        <select name="category_id" defaultValue={filters?.category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="">All</option>
                            {categories.map((category) => (<option key={category.id} value={category.id}>{category.name}</option>))}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Employee / person</label>
                        <select name="person" defaultValue={filters?.person || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                            <option value="">All</option>
                            {peopleOptions.map((option) => (<option key={option.key} value={option.key}>{option.label}</option>))}
                        </select>
                    </div>
                    <div className="mt-6 flex items-center gap-2">
                        <button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-500">Apply</button>
                        <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Reset</a>
                    </div>
                    <div className="md:col-span-4 lg:col-span-5">
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Sources</div>
                        <div className="mt-1.5 flex flex-wrap gap-3 text-xs text-slate-600">
                            {['manual', 'salary', 'contract_payout', 'sales_payout'].map((source) => (
                                <label key={source} className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/80 px-3 py-1">
                                    <input type="checkbox" name="sources[]" value={source} defaultChecked={sourceSelections.includes(source)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                    {source === 'manual' ? 'Manual' : source === 'salary' ? 'Salaries' : source === 'contract_payout' ? 'Contract Payouts' : 'Sales Rep Payouts'}
                                </label>
                            ))}
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#edf8f7] via-white to-[#f3f7ff] p-5 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Expense Trend</div>
                        <div className="mt-1 text-sm text-slate-500">Interactive expense-only trend with period and source toggles.</div>
                    </div>
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white/90 p-1 text-xs font-semibold shadow-sm">
                        {PERIOD_OPTIONS.map((item) => (
                            <button key={item.key} type="button" className={`rounded-md px-3 py-1 ${period === item.key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`} onClick={() => setPeriod(item.key)}>
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.85fr)_minmax(260px,1fr)]">
                    <div className="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                        <div className="mb-2 flex flex-wrap gap-2">
                            {Object.entries(SERIES_META).map(([key, meta]) => (
                                <button key={key} type="button" onClick={() => toggleSeries(key)} className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${visible[key] ? 'border-slate-300 bg-white text-slate-700' : 'border-slate-200 bg-slate-50 text-slate-400'}`}>
                                    <span className="h-2 w-2 rounded-full" style={{ backgroundColor: meta.color }} />
                                    {meta.label}
                                </button>
                            ))}
                        </div>

                        {!hasChartData ? (
                            <div className="py-16 text-center text-sm text-slate-500">No trend data available for selected filters.</div>
                        ) : (
                            <svg viewBox="0 0 100 62" className="h-72 w-full">
                                <line x1="0" y1="56" x2="100" y2="56" stroke="#e2e8f0" />
                                <line x1="0" y1="43" x2="100" y2="43" stroke="#e2e8f0" strokeDasharray="1 1.5" />
                                <line x1="0" y1="30" x2="100" y2="30" stroke="#e2e8f0" strokeDasharray="1 1.5" />
                                <line x1="0" y1="17" x2="100" y2="17" stroke="#e2e8f0" strokeDasharray="1 1.5" />
                                <text x="0" y="8" fill="#475569" fontSize="2.9">Expense</text>
                                {Object.entries(SERIES_META).map(([key, meta]) => {
                                    if (!visible[key]) return null;
                                    const points = buildPolyline(activeSeries?.[key], seriesMax);
                                    if (!points) return null;
                                    return <polyline key={key} points={points} fill="none" stroke={meta.color} strokeWidth={key === 'total' ? 0.9 : 0.7} />;
                                })}
                            </svg>
                        )}
                    </div>

                    <div className="space-y-3">
                        <StatusMetric data={expenseStatus?.today} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.week} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.month} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.filtered} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                    </div>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div className="card p-4"><div className="text-xs text-slate-500">Year-to-date</div><div className="mt-1 text-xl font-semibold text-slate-900">{money(yearlyTotal, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Manual</div><div className="mt-1 text-xl font-semibold text-sky-700">{money(expenseBySource?.manual, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Salary</div><div className="mt-1 text-xl font-semibold text-amber-700">{money(expenseBySource?.salary, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Contract</div><div className="mt-1 text-xl font-semibold text-indigo-700">{money(expenseBySource?.contract_payout, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Sales Rep</div><div className="mt-1 text-xl font-semibold text-teal-700">{money(expenseBySource?.sales_payout, currencySymbol, currencyCode)}</div></div>
            </div>

            <div className="mt-5 card p-5 md:p-6">
                <div className="section-label">Expense Statement</div>
                <div className="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_280px]">
                    <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Top Expense Category</div>
                        <div className="mt-2 truncate text-lg font-semibold text-slate-900">{topCategory?.name || 'No category data'}</div>
                        <div className="mt-1 text-sm text-slate-600">{topCategory ? money(topCategory.total, currencySymbol, currencyCode) : '-'}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total Expenses</div>
                        <div className="mt-2 whitespace-nowrap text-2xl font-semibold text-rose-600">{money(expenseTotal, currencySymbol, currencyCode)}</div>
                    </div>
                </div>
            </div>

            <div className="mt-5 grid gap-4 xl:grid-cols-3">
                <BreakdownCard title="Expense by category" items={categoryTotals} emptyText="No expenses found in this range." getKey={(item, index) => `${item?.category_id ?? 'category'}-${index}`} getLabel={(item) => item?.name || 'Uncategorized'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                <BreakdownCard title="Expense by employee" items={employeeTotals} emptyText="No employee payouts in this range." getKey={(item, index) => `${item?.label ?? 'employee'}-${index}`} getLabel={(item) => item?.label || 'Unknown employee'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                <BreakdownCard title="Expense by sales representatives" items={salesRepTotals} emptyText="No sales rep payouts in this range." getKey={(item, index) => `${item?.label ?? 'sales-rep'}-${index}`} getLabel={(item) => item?.label || 'Unknown sales rep'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
            </div>

            <div className="mt-5 card p-5 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Google AI Summary</div>
                        <div className="mt-1 text-[11px] text-slate-500">Expense-only summary for this filtered period.</div>
                    </div>
                    <a href={routes?.refresh_ai} data-native="true" className="rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700">Refresh AI</a>
                </div>
                <div className="mt-4 rounded-2xl border border-white/60 bg-slate-50/80 p-4 text-[13px] leading-relaxed text-slate-600">
                    {aiSummary ? <pre className="whitespace-pre-wrap font-sans">{aiSummary}</pre> : aiError ? <div className="text-xs text-slate-500">AI summary unavailable: {aiError}</div> : <div className="text-xs text-slate-500">AI summary is not available yet.</div>}
                </div>
            </div>
        </>
    );
}
