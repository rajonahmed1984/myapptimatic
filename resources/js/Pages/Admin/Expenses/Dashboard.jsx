import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import DateRangePickerField from '../../../Components/DateRangePickerField';

const CHART_FRAME = {
    width: 1120,
    height: 300,
    padLeft: 56,
    padRight: 24,
    padTop: 20,
    padBottom: 38,
    rows: 4,
};

const CHART_SERIES = {
    total: { label: 'Total Expenses', stroke: '#ef4444', pointFill: '#fecaca', pointStroke: '#dc2626', fill: 'rgba(239,68,68,0.16)', legend: 'bg-rose-400' },
    manual: { label: 'Manual', stroke: '#2563eb', pointFill: '#bfdbfe', pointStroke: '#1d4ed8', fill: 'rgba(37,99,235,0.08)', legend: 'bg-blue-400' },
    salary: { label: 'Salary', stroke: '#f59e0b', pointFill: '#fde68a', pointStroke: '#d97706', fill: 'rgba(245,158,11,0.08)', legend: 'bg-amber-400' },
    contract: { label: 'Contract Payout', stroke: '#6366f1', pointFill: '#c7d2fe', pointStroke: '#4f46e5', fill: 'rgba(99,102,241,0.08)', legend: 'bg-indigo-400' },
    sales: { label: 'Sales Rep Payout', stroke: '#14b8a6', pointFill: '#99f6e4', pointStroke: '#0f766e', fill: 'rgba(20,184,166,0.08)', legend: 'bg-teal-400' },
};

const PERIOD_OPTIONS = [
    { key: 'day', label: 'Daily' },
    { key: 'week', label: 'Weekly' },
    { key: 'month', label: 'Monthly' },
];

function money(amount, symbol = '', code = '') {
    return `${symbol}${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${code}`;
}

function asNumberList(values, expectedLength = null) {
    const list = Array.isArray(values) ? values.map((value) => Number(value || 0)) : [];
    if (expectedLength === null || expectedLength <= list.length) return list;
    return [...list, ...new Array(expectedLength - list.length).fill(0)];
}

function buildChartPoints(values, maxValue) {
    const list = asNumberList(values);
    if (list.length === 0) return [];

    const left = CHART_FRAME.padLeft;
    const right = CHART_FRAME.width - CHART_FRAME.padRight;
    const top = CHART_FRAME.padTop;
    const bottom = CHART_FRAME.height - CHART_FRAME.padBottom;
    const innerWidth = Math.max(1, right - left);
    const innerHeight = Math.max(1, bottom - top);
    const safeMax = Math.max(1, Number(maxValue || 0));

    if (list.length === 1) {
        const value = Number(list[0] || 0);
        const y = top + (1 - value / safeMax) * innerHeight;
        const x = left + innerWidth / 2;
        return [{ x, y, value, index: 0 }];
    }

    return list.map((value, index) => {
        const ratio = index / (list.length - 1);
        const x = left + ratio * innerWidth;
        const y = top + (1 - Number(value || 0) / safeMax) * innerHeight;
        return { x, y, value: Number(value || 0), index };
    });
}

function pointsPath(points) {
    if (!Array.isArray(points) || points.length === 0) return '';
    return points.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
}

function pointsAreaPath(points, baseY) {
    if (!Array.isArray(points) || points.length === 0) return '';
    const linePath = pointsPath(points);
    const first = points[0];
    const last = points[points.length - 1];
    return `${linePath} L${last.x.toFixed(2)} ${baseY.toFixed(2)} L${first.x.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

function yTicks(maxValue, count) {
    const safeMax = Math.max(1, Number(maxValue || 0));
    return Array.from({ length: count + 1 }, (_, idx) => safeMax * ((count - idx) / count));
}

function xTickIndexes(total, maxTicks = 9) {
    if (total <= 0) return [];
    if (total <= maxTicks) return Array.from({ length: total }, (_, idx) => idx);
    const step = Math.max(1, Math.floor((total - 1) / (maxTicks - 1)));
    const ticks = [];
    for (let idx = 0; idx < total; idx += step) ticks.push(idx);
    if (ticks[ticks.length - 1] !== total - 1) ticks.push(total - 1);
    return ticks;
}

function changeText(percent) {
    if (percent === null || percent === undefined) return 'N/A';
    const value = Number(percent);
    return `${value >= 0 ? '+' : ''}${value.toFixed(1)}%`;
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
                                <div className="min-w-0 pr-3 text-sm font-medium text-slate-700"><div className="truncate">{getLabel(item)}</div></div>
                                <div className="whitespace-nowrap text-sm font-semibold tabular-nums text-slate-900">{money(item?.total, currencySymbol, currencyCode)}</div>
                            </div>
                        ))}
                    </div>
                ) : <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">{emptyText}</div>}
            </div>
        </div>
    );
}

function StatusMetric({ data = {}, currencyCode, currencySymbol }) {
    const change = data?.change_percent;
    const changeNumber = change === null || change === undefined ? null : Number(change);
    const tone = changeNumber === null ? 'text-slate-500' : changeNumber >= 0 ? 'text-emerald-600' : 'text-rose-600';
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

export default function Dashboard({ pageTitle = 'Expense Dashboard', filters = {}, expenseTotal = 0, expenseBySource = {}, expenseStatus = {}, categoryTotals = [], employeeTotals = [], salesRepTotals = [], yearlyTotal = 0, topCategories = [], periodSeries = {}, categories = [], peopleOptions = [], currencyCode = 'BDT', currencySymbol = '', aiSummary = null, aiError = null, routes = {} }) {
    const [period, setPeriod] = useState('day');
    const [seriesVisible, setSeriesVisible] = useState({ total: true, manual: true, salary: true, contract: false, sales: false });
    const [hoveredIndex, setHoveredIndex] = useState(null);

    const activeSeries = periodSeries?.[period] || { labels: [], total: [], manual: [], salary: [], contract: [], sales: [] };
    const sources = Array.isArray(filters?.sources) ? filters.sources : [];
    const topCategory = topCategories?.[0] ?? categoryTotals?.[0] ?? null;

    const chartModel = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const seriesLength = labels.length;
        const totalSeries = asNumberList(activeSeries?.total, seriesLength);
        const manualSeries = asNumberList(activeSeries?.manual, seriesLength);
        const salarySeries = asNumberList(activeSeries?.salary, seriesLength);
        const contractSeries = asNumberList(activeSeries?.contract, seriesLength);
        const salesSeries = asNumberList(activeSeries?.sales, seriesLength);
        const maxValue = Math.max(1, ...totalSeries, ...manualSeries, ...salarySeries, ...contractSeries, ...salesSeries);
        const baseY = CHART_FRAME.height - CHART_FRAME.padBottom;
        return { labels, seriesLength, totalSeries, manualSeries, salarySeries, contractSeries, salesSeries, maxValue, ticks: yTicks(maxValue, CHART_FRAME.rows), xTickIndexes: xTickIndexes(seriesLength), points: { total: buildChartPoints(totalSeries, maxValue), manual: buildChartPoints(manualSeries, maxValue), salary: buildChartPoints(salarySeries, maxValue), contract: buildChartPoints(contractSeries, maxValue), sales: buildChartPoints(salesSeries, maxValue) }, baseY };
    }, [activeSeries]);

    const hoverRegions = useMemo(() => {
        const points = Array.isArray(chartModel.points?.total) ? chartModel.points.total : [];
        if (points.length === 0) return [];
        return points.map((point, index) => {
            const prevX = points[index - 1]?.x ?? CHART_FRAME.padLeft;
            const nextX = points[index + 1]?.x ?? (CHART_FRAME.width - CHART_FRAME.padRight);
            const leftEdge = index === 0 ? CHART_FRAME.padLeft : (prevX + point.x) / 2;
            const rightEdge = index === points.length - 1 ? (CHART_FRAME.width - CHART_FRAME.padRight) : (point.x + nextX) / 2;
            return { index, x: leftEdge, width: Math.max(1, rightEdge - leftEdge) };
        });
    }, [chartModel.points]);

    const hoverDetails = useMemo(() => {
        if (hoveredIndex === null || hoveredIndex < 0 || hoveredIndex >= chartModel.seriesLength) return null;
        const point = chartModel.points.total?.[hoveredIndex];
        if (!point) return null;
        const xPct = (point.x / CHART_FRAME.width) * 100;
        const yPct = (point.y / CHART_FRAME.height) * 100;
        return { label: chartModel.labels?.[hoveredIndex] || '--', total: Number(chartModel.totalSeries?.[hoveredIndex] || 0), manual: Number(chartModel.manualSeries?.[hoveredIndex] || 0), salary: Number(chartModel.salarySeries?.[hoveredIndex] || 0), contract: Number(chartModel.contractSeries?.[hoveredIndex] || 0), sales: Number(chartModel.salesSeries?.[hoveredIndex] || 0), xPct: Math.max(12, Math.min(88, xPct)), yPct: Math.max(8, Math.min(62, yPct - 6)), pointX: point.x };
    }, [hoveredIndex, chartModel]);

    const toggleSeries = (key) => setSeriesVisible((previous) => ({ ...previous, [key]: !previous[key] }));
    const hasChartData = chartModel.seriesLength > 0;

    return (
        <>
            <Head title={pageTitle} />
            <div className="card bg-gradient-to-br from-[#eef8fb] via-white to-[#f3f7ff] p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div><div className="section-label">Expense Controls</div><div className="mt-1 text-xs text-slate-500">Filter sources and period, then review expense status.</div></div>
                    <div className="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">Filtered total: {money(expenseStatus?.filtered?.amount, currencySymbol, currencyCode)}</div>
                </div>
                <form method="GET" action={routes?.index} data-native="true" className="mt-3 grid gap-2 text-sm md:grid-cols-4 lg:grid-cols-5">
                    <DateRangePickerField
                        startName="start_date"
                        endName="end_date"
                        startValue={filters?.start_date || ''}
                        endValue={filters?.end_date || ''}
                        submitFormat="iso"
                        startLabel="Start date"
                        endLabel="End date"
                        className="md:col-span-2 lg:col-span-2"
                        gridClassName="grid gap-2 md:grid-cols-2"
                        inputClassName="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm"
                    />
                    <div><label className="text-xs text-slate-500">Category</label><select name="category_id" defaultValue={filters?.category_id || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm"><option value="">All</option>{categories.map((category) => (<option key={category.id} value={category.id}>{category.name}</option>))}</select></div>
                    <div><label className="text-xs text-slate-500">Employee / person</label><select name="person" defaultValue={filters?.person || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm"><option value="">All</option>{peopleOptions.map((option) => (<option key={option.key} value={option.key}>{option.label}</option>))}</select></div>
                    <div className="mt-6 flex items-center gap-2"><button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-500">Apply</button><a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Reset</a></div>
                    <div className="md:col-span-4 lg:col-span-5"><div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Sources</div><div className="mt-1.5 flex flex-wrap gap-3 text-xs text-slate-600"><label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="manual" defaultChecked={sources.includes('manual')} /> Manual</label><label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="salary" defaultChecked={sources.includes('salary')} /> Salaries</label><label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="contract_payout" defaultChecked={sources.includes('contract_payout')} /> Contract Payouts</label><label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="sales_payout" defaultChecked={sources.includes('sales_payout')} /> Sales Rep Payouts</label></div></div>
                </form>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#edf8f7] via-white to-[#f3f7ff] p-5 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div><div className="section-label">Expense Trend</div><div className="mt-1 text-sm text-slate-500">Similar interaction pattern as Income Trend.</div></div>
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white/90 p-1 text-xs font-semibold shadow-sm">{PERIOD_OPTIONS.map((item) => (<button key={item.key} type="button" className={`rounded-md px-3 py-1 ${period === item.key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`} onClick={() => setPeriod(item.key)}>{item.label}</button>))}</div>
                </div>
                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.85fr)_minmax(260px,1fr)]">
                    <div className="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                        <div className="mb-2 flex flex-wrap gap-2">{Object.entries(CHART_SERIES).map(([key, config]) => (<button key={key} type="button" onClick={() => toggleSeries(key)} className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${seriesVisible[key] ? 'border-slate-300 bg-white text-slate-700' : 'border-slate-200 bg-slate-50 text-slate-400'}`}><span className={`h-2 w-2 rounded-full ${config.legend}`} />{config.label}</button>))}</div>
                        {!hasChartData ? <div className="py-16 text-center text-sm text-slate-500">No trend data available for selected filters.</div> : (
                            <div className="relative">
                                <svg viewBox={`0 0 ${CHART_FRAME.width} ${CHART_FRAME.height}`} className="h-auto w-full">
                                    <defs><linearGradient id="expenseTotalGradient" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stopColor={CHART_SERIES.total.fill} /><stop offset="100%" stopColor="rgba(239, 68, 68, 0.02)" /></linearGradient></defs>
                                    {chartModel.ticks.map((value, index) => { const ratio = index / CHART_FRAME.rows; const y = CHART_FRAME.padTop + ratio * (CHART_FRAME.height - CHART_FRAME.padTop - CHART_FRAME.padBottom); return (<g key={`y-grid-${index}`}><line x1={CHART_FRAME.padLeft} y1={y} x2={CHART_FRAME.width - CHART_FRAME.padRight} y2={y} stroke="#e2e8f0" strokeDasharray={index === CHART_FRAME.rows ? undefined : '4 4'} /><text x={CHART_FRAME.padLeft - 10} y={y + 4} textAnchor="end" fontSize="11" fill="#64748b">{Math.round(value)}</text></g>); })}
                                    <text x={CHART_FRAME.padLeft - 46} y={CHART_FRAME.padTop - 4} textAnchor="start" fontSize="12" fill="#334155">Expenses</text>
                                    {hoverDetails ? <line x1={hoverDetails.pointX} y1={CHART_FRAME.padTop} x2={hoverDetails.pointX} y2={chartModel.baseY} stroke="#0f172a" strokeOpacity="0.15" strokeDasharray="3 4" /> : null}
                                    {seriesVisible.total ? <><path d={pointsAreaPath(chartModel.points.total, chartModel.baseY)} fill="url(#expenseTotalGradient)" stroke="none" /><path d={pointsPath(chartModel.points.total)} fill="none" stroke={CHART_SERIES.total.stroke} strokeWidth="2.4" />{chartModel.points.total.map((point, idx) => (<circle key={`total-dot-${idx}`} cx={point.x} cy={point.y} r="3" fill={CHART_SERIES.total.pointFill} stroke={CHART_SERIES.total.pointStroke} strokeWidth="1.1" />))}</> : null}
                                    {seriesVisible.manual ? <><path d={pointsPath(chartModel.points.manual)} fill="none" stroke={CHART_SERIES.manual.stroke} strokeWidth="2" />{chartModel.points.manual.map((point, idx) => (<circle key={`manual-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.manual.pointFill} stroke={CHART_SERIES.manual.pointStroke} strokeWidth="1" />))}</> : null}
                                    {seriesVisible.salary ? <><path d={pointsPath(chartModel.points.salary)} fill="none" stroke={CHART_SERIES.salary.stroke} strokeWidth="2" />{chartModel.points.salary.map((point, idx) => (<circle key={`salary-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.salary.pointFill} stroke={CHART_SERIES.salary.pointStroke} strokeWidth="1" />))}</> : null}
                                    {seriesVisible.contract ? <><path d={pointsPath(chartModel.points.contract)} fill="none" stroke={CHART_SERIES.contract.stroke} strokeWidth="2" />{chartModel.points.contract.map((point, idx) => (<circle key={`contract-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.contract.pointFill} stroke={CHART_SERIES.contract.pointStroke} strokeWidth="1" />))}</> : null}
                                    {seriesVisible.sales ? <><path d={pointsPath(chartModel.points.sales)} fill="none" stroke={CHART_SERIES.sales.stroke} strokeWidth="2" />{chartModel.points.sales.map((point, idx) => (<circle key={`sales-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.sales.pointFill} stroke={CHART_SERIES.sales.pointStroke} strokeWidth="1" />))}</> : null}
                                    {hoverRegions.map((region) => (<rect key={`hover-zone-${region.index}`} x={region.x} y={CHART_FRAME.padTop} width={region.width} height={chartModel.baseY - CHART_FRAME.padTop} fill="transparent" onMouseEnter={() => setHoveredIndex(region.index)} onMouseMove={() => setHoveredIndex(region.index)} onClick={() => setHoveredIndex(region.index)} />))}
                                    {chartModel.xTickIndexes.map((index) => { const point = chartModel.points.total[index]; if (!point) return null; return (<text key={`x-label-${index}`} x={point.x} y={CHART_FRAME.height - 12} textAnchor="end" transform={`rotate(-35 ${point.x} ${CHART_FRAME.height - 12})`} fontSize="11" fill="#64748b">{chartModel.labels[index]}</text>); })}
                                </svg>
                                {hoverDetails ? <div className="pointer-events-none absolute z-10 -translate-x-1/2 rounded-2xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl" style={{ left: `${hoverDetails.xPct}%`, top: `${hoverDetails.yPct}%` }}><div className="text-[11px] font-semibold text-slate-200">{hoverDetails.label}</div><div className="mt-1">Total: {money(hoverDetails.total, currencySymbol, currencyCode)}</div><div className="text-slate-300">Manual: {money(hoverDetails.manual, currencySymbol, currencyCode)}</div><div className="text-slate-300">Salary: {money(hoverDetails.salary, currencySymbol, currencyCode)}</div><div className="text-slate-300">Contract: {money(hoverDetails.contract, currencySymbol, currencyCode)}</div><div className="text-slate-300">Sales rep: {money(hoverDetails.sales, currencySymbol, currencyCode)}</div></div> : null}
                            </div>
                        )}
                    </div>
                    <div className="space-y-3"><StatusMetric data={expenseStatus?.today} currencyCode={currencyCode} currencySymbol={currencySymbol} /><StatusMetric data={expenseStatus?.week} currencyCode={currencyCode} currencySymbol={currencySymbol} /><StatusMetric data={expenseStatus?.month} currencyCode={currencyCode} currencySymbol={currencySymbol} /><StatusMetric data={expenseStatus?.filtered} currencyCode={currencyCode} currencySymbol={currencySymbol} /></div>
                </div>
            </div>

            <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div className="card p-4"><div className="text-xs text-slate-500">Year-to-date</div><div className="mt-1 text-xl font-semibold text-slate-900">{money(yearlyTotal, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Manual</div><div className="mt-1 text-xl font-semibold text-blue-700">{money(expenseBySource?.manual, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Salary</div><div className="mt-1 text-xl font-semibold text-amber-700">{money(expenseBySource?.salary, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Contract</div><div className="mt-1 text-xl font-semibold text-indigo-700">{money(expenseBySource?.contract_payout, currencySymbol, currencyCode)}</div></div>
                <div className="card p-4"><div className="text-xs text-slate-500">Sales rep</div><div className="mt-1 text-xl font-semibold text-teal-700">{money(expenseBySource?.sales_payout, currencySymbol, currencyCode)}</div></div>
            </div>

            <div className="mt-5 card p-5 md:p-6"><div className="section-label">Expense Statement</div><div className="mt-4 grid gap-4 md:grid-cols-[minmax(0,1fr)_280px]"><div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Top Expense Category</div><div className="mt-2 truncate text-lg font-semibold text-slate-900">{topCategory?.name || 'No category data'}</div><div className="mt-1 text-sm text-slate-600">{topCategory ? money(topCategory.total, currencySymbol, currencyCode) : '-'}</div></div><div className="rounded-2xl border border-slate-200 bg-white p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total Expenses</div><div className="mt-2 whitespace-nowrap text-2xl font-semibold text-rose-600">{money(expenseTotal, currencySymbol, currencyCode)}</div></div></div></div>

            <div className="mt-5 grid gap-4 xl:grid-cols-3">
                <BreakdownCard title="Expense by category" items={categoryTotals} emptyText="No expenses found in this range." getKey={(item, index) => `${item?.category_id ?? 'category'}-${index}`} getLabel={(item) => item?.name || 'Uncategorized'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                <BreakdownCard title="Expense by employee" items={employeeTotals} emptyText="No employee payouts in this range." getKey={(item, index) => `${item?.label ?? 'employee'}-${index}`} getLabel={(item) => item?.label || 'Unknown employee'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                <BreakdownCard title="Expense by sales representatives" items={salesRepTotals} emptyText="No sales rep payouts in this range." getKey={(item, index) => `${item?.label ?? 'sales-rep'}-${index}`} getLabel={(item) => item?.label || 'Unknown sales rep'} currencyCode={currencyCode} currencySymbol={currencySymbol} />
            </div>

            <div className="mt-5 card p-5 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3"><div><div className="section-label">Google AI Summary</div><div className="mt-1 text-[11px] text-slate-500">Expense-only summary for this filtered period.</div></div><a href={routes?.refresh_ai} data-native="true" className="rounded-full border border-emerald-200 bg-white px-3 py-1 text-[11px] font-semibold text-emerald-700">Refresh AI</a></div>
                <div className="mt-4 rounded-2xl border border-white/60 bg-slate-50/80 p-4 text-[13px] leading-relaxed text-slate-600">{aiSummary ? <pre className="whitespace-pre-wrap font-sans">{aiSummary}</pre> : aiError ? <div className="text-xs text-slate-500">AI summary unavailable: {aiError}</div> : <div className="text-xs text-slate-500">AI summary is not available yet.</div>}</div>
            </div>
        </>
    );
}

Dashboard.title = 'Expenses Dashboard';
