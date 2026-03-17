import React, { useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';

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
    total: {
        label: 'Total Expenses',
        stroke: '#ef4444',
        pointFill: '#fecaca',
        pointStroke: '#dc2626',
        fill: 'rgba(239, 68, 68, 0.16)',
        legend: 'bg-rose-400',
    },
    manual: {
        label: 'Manual',
        stroke: '#2563eb',
        pointFill: '#bfdbfe',
        pointStroke: '#1d4ed8',
        fill: 'rgba(37, 99, 235, 0.08)',
        legend: 'bg-blue-400',
    },
    salary: {
        label: 'Salary',
        stroke: '#f59e0b',
        pointFill: '#fde68a',
        pointStroke: '#d97706',
        fill: 'rgba(245, 158, 11, 0.08)',
        legend: 'bg-amber-400',
    },
    contract: {
        label: 'Contract Payout',
        stroke: '#6366f1',
        pointFill: '#c7d2fe',
        pointStroke: '#4f46e5',
        fill: 'rgba(99, 102, 241, 0.08)',
        legend: 'bg-indigo-400',
    },
    sales: {
        label: 'Sales Rep Payout',
        stroke: '#14b8a6',
        pointFill: '#99f6e4',
        pointStroke: '#0f766e',
        fill: 'rgba(20, 184, 166, 0.08)',
        legend: 'bg-teal-400',
    },
};

const PERIOD_OPTIONS = [
    { key: 'day', label: 'Daily' },
    { key: 'week', label: 'Weekly' },
    { key: 'month', label: 'Monthly' },
];

const QUICK_RANGE_OPTIONS = [
    { key: 'today', label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'last7', label: 'Last 7 days' },
    { key: 'last30', label: 'Last 30 days' },
    { key: 'thisMonth', label: 'This month' },
    { key: 'lastMonth', label: 'Last month' },
    { key: 'thisYear', label: 'This year' },
    { key: 'lastYear', label: 'Last year' },
];

function money(amount, symbol = '', code = '') {
    return `${symbol}${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${code}`;
}

function asNumberList(values, expectedLength = null) {
    const list = Array.isArray(values) ? values.map((value) => Number(value || 0)) : [];
    if (expectedLength === null || expectedLength <= list.length) {
        return list;
    }

    return [...list, ...new Array(expectedLength - list.length).fill(0)];
}

function buildChartPoints(values, maxValue) {
    const list = asNumberList(values);
    if (list.length === 0) {
        return [];
    }

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
    if (!Array.isArray(points) || points.length === 0) {
        return '';
    }

    return points
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x.toFixed(2)} ${point.y.toFixed(2)}`)
        .join(' ');
}

function pointsAreaPath(points, baseY) {
    if (!Array.isArray(points) || points.length === 0) {
        return '';
    }

    const first = points[0];
    const last = points[points.length - 1];
    return `${pointsPath(points)} L${last.x.toFixed(2)} ${baseY.toFixed(2)} L${first.x.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

function yTicks(maxValue, count) {
    const safeMax = Math.max(1, Number(maxValue || 0));
    return Array.from({ length: count + 1 }, (_, idx) => safeMax * ((count - idx) / count));
}

function xTickIndexes(total, maxTicks = 9) {
    if (total <= 0) {
        return [];
    }

    if (total <= maxTicks) {
        return Array.from({ length: total }, (_, idx) => idx);
    }

    const step = Math.max(1, Math.floor((total - 1) / (maxTicks - 1)));
    const ticks = [];
    for (let idx = 0; idx < total; idx += step) {
        ticks.push(idx);
    }
    if (ticks[ticks.length - 1] !== total - 1) {
        ticks.push(total - 1);
    }

    return ticks;
}

function changeText(percent) {
    if (percent === null || percent === undefined) {
        return 'N/A';
    }

    const value = Number(percent);
    return `${value >= 0 ? '+' : ''}${value.toFixed(1)}%`;
}

function formatIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function rangeDays(days) {
    const end = new Date();
    const start = new Date(end);
    start.setDate(end.getDate() - (days - 1));
    return { start: formatIsoDate(start), end: formatIsoDate(end) };
}

function getQuickRange(key) {
    const today = new Date();

    switch (key) {
        case 'today':
            return { start: formatIsoDate(today), end: formatIsoDate(today) };
        case 'yesterday': {
            const day = new Date(today);
            day.setDate(today.getDate() - 1);
            return { start: formatIsoDate(day), end: formatIsoDate(day) };
        }
        case 'last7':
            return rangeDays(7);
        case 'last30':
            return rangeDays(30);
        case 'thisMonth': {
            const start = new Date(today.getFullYear(), today.getMonth(), 1);
            return { start: formatIsoDate(start), end: formatIsoDate(today) };
        }
        case 'lastMonth': {
            const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const end = new Date(today.getFullYear(), today.getMonth(), 0);
            return { start: formatIsoDate(start), end: formatIsoDate(end) };
        }
        case 'thisYear': {
            const start = new Date(today.getFullYear(), 0, 1);
            return { start: formatIsoDate(start), end: formatIsoDate(today) };
        }
        case 'lastYear': {
            const start = new Date(today.getFullYear() - 1, 0, 1);
            const end = new Date(today.getFullYear() - 1, 11, 31);
            return { start: formatIsoDate(start), end: formatIsoDate(end) };
        }
        default:
            return { start: '', end: '' };
    }
}

function findMatchingQuickRange(startDate, endDate) {
    return QUICK_RANGE_OPTIONS.find((option) => {
        const range = getQuickRange(option.key);
        return range.start === startDate && range.end === endDate;
    })?.key ?? null;
}

function shareText(amount, totalAmount) {
    const total = Number(totalAmount || 0);
    if (total <= 0) {
        return '0.0% of total';
    }

    return `${((Number(amount || 0) / total) * 100).toFixed(1)}% of total`;
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
                                <div className="whitespace-nowrap text-sm font-semibold tabular-nums text-slate-900">
                                    {money(item?.total, currencySymbol, currencyCode)}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        {emptyText}
                    </div>
                )}
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
        <div className="w-full rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-slate-300">
            <div className="flex items-center gap-2.5">
                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-700 text-xs font-semibold text-white shadow-sm">
                    {icon}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="truncate text-xs font-medium text-slate-500">{data?.label || '--'}</div>
                    <div className="mt-0.5 whitespace-nowrap text-xl font-semibold leading-none tracking-tight text-slate-900">
                        {money(data?.amount, currencySymbol, currencyCode)}
                    </div>
                    <div className={`mt-1 truncate text-xs font-medium ${tone}`}>
                        {changeText(change)} {data?.comparison_label || ''}
                    </div>
                </div>
            </div>
        </div>
    );
}

function AISummaryMetric({ label, amount, note, currencyCode, currencySymbol, accent = 'text-slate-900' }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">{label}</div>
            <div className={`mt-2 text-xl font-semibold leading-none ${accent}`}>
                {money(amount, currencySymbol, currencyCode)}
            </div>
            <div className="mt-2 text-xs text-slate-500">{note}</div>
        </div>
    );
}

function SummaryListCard({ title, items = [], emptyLabel = '--' }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-3 space-y-2.5">
                {items.length > 0 ? items.map((item) => (
                    <div key={`${title}-${item.label}`} className="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2">
                        <div className="min-w-0 truncate text-sm font-medium text-slate-700">{item.label}</div>
                        <div className="shrink-0 text-sm font-semibold text-slate-900">{item.value}</div>
                    </div>
                )) : (
                    <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-3 py-4 text-sm text-slate-500">
                        {emptyLabel}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Dashboard({
    pageTitle = 'Expense Dashboard',
    filters = {},
    expenseTotal = 0,
    entriesCount = 0,
    expenseBySource = {},
    expenseStatus = {},
    categoryTotals = [],
    employeeTotals = [],
    salesRepTotals = [],
    yearlyTotal = 0,
    topCategories = [],
    periodSeries = {},
    recentEntries = [],
    currencyCode = 'BDT',
    currencySymbol = '',
    aiSummary = null,
    aiError = null,
    routes = {},
}) {
    const [period, setPeriod] = useState('day');
    const [hoveredIndex, setHoveredIndex] = useState(null);
    const [seriesVisible, setSeriesVisible] = useState({
        total: true,
        manual: true,
        salary: true,
        contract: true,
        sales: true,
    });
    const [activeQuickRange, setActiveQuickRange] = useState(() => findMatchingQuickRange(filters?.start_date || '', filters?.end_date || ''));
    const formRef = useRef(null);
    const startDateRef = useRef(null);
    const endDateRef = useRef(null);

    const activeSeries = periodSeries?.[period] || {
        labels: [],
        total: [],
        manual: [],
        salary: [],
        contract: [],
        sales: [],
    };

    const selectedSources = Array.isArray(filters?.sources) ? filters.sources : [];

    const sourceSummaryCards = useMemo(() => ([
        {
            label: 'Total Expenses',
            amount: expenseTotal,
            note: `${Number(entriesCount || 0)} filtered entries`,
            accent: 'text-slate-900',
        },
        {
            label: 'Manual',
            amount: expenseBySource?.manual || 0,
            note: shareText(expenseBySource?.manual || 0, expenseTotal),
            accent: 'text-blue-700',
        },
        {
            label: 'Salary',
            amount: expenseBySource?.salary || 0,
            note: shareText(expenseBySource?.salary || 0, expenseTotal),
            accent: 'text-amber-700',
        },
        {
            label: 'Contract Payout',
            amount: expenseBySource?.contract_payout || 0,
            note: shareText(expenseBySource?.contract_payout || 0, expenseTotal),
            accent: 'text-indigo-700',
        },
        {
            label: 'Sales Rep Payout',
            amount: expenseBySource?.sales_payout || 0,
            note: shareText(expenseBySource?.sales_payout || 0, expenseTotal),
            accent: 'text-teal-700',
        },
    ]), [entriesCount, expenseBySource, expenseTotal]);

    const topPeople = useMemo(() => (
        Array.isArray(employeeTotals)
            ? employeeTotals.slice(0, 5).map((item) => ({
                label: item?.label || '--',
                value: money(item?.total, currencySymbol, currencyCode),
            }))
            : []
    ), [employeeTotals, currencyCode, currencySymbol]);

    const chartModel = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const total = asNumberList(activeSeries?.total, labels.length);
        const manual = asNumberList(activeSeries?.manual, labels.length);
        const salary = asNumberList(activeSeries?.salary, labels.length);
        const contract = asNumberList(activeSeries?.contract, labels.length);
        const sales = asNumberList(activeSeries?.sales, labels.length);
        const allValues = [...total, ...manual, ...salary, ...contract, ...sales];
        const maxValue = Math.max(1, ...allValues);
        const baseY = CHART_FRAME.height - CHART_FRAME.padBottom;

        return {
            labels,
            baseY,
            maxValue,
            values: { total, manual, salary, contract, sales },
            ticks: yTicks(maxValue, CHART_FRAME.rows),
            xTickIndexes: xTickIndexes(labels.length),
            points: {
                total: buildChartPoints(total, maxValue),
                manual: buildChartPoints(manual, maxValue),
                salary: buildChartPoints(salary, maxValue),
                contract: buildChartPoints(contract, maxValue),
                sales: buildChartPoints(sales, maxValue),
            },
        };
    }, [activeSeries]);

    const hoverRegions = useMemo(() => {
        const points = chartModel.points.total;
        if (!Array.isArray(points) || points.length === 0) {
            return [];
        }

        const leftEdge = CHART_FRAME.padLeft;
        const rightEdge = CHART_FRAME.width - CHART_FRAME.padRight;

        return points.map((point, index) => {
            const prev = points[index - 1];
            const next = points[index + 1];
            const start = prev ? (prev.x + point.x) / 2 : leftEdge;
            const end = next ? (point.x + next.x) / 2 : rightEdge;

            return {
                index,
                x: start,
                width: Math.max(16, end - start),
            };
        });
    }, [chartModel.points.total]);

    const hoverDetails = useMemo(() => {
        if (hoveredIndex === null || hoveredIndex < 0 || hoveredIndex >= chartModel.labels.length) {
            return null;
        }

        const activePoint = ['total', 'manual', 'salary', 'contract', 'sales']
            .map((key) => chartModel.points[key]?.[hoveredIndex])
            .find(Boolean);

        if (!activePoint) {
            return null;
        }

        return {
            label: chartModel.labels[hoveredIndex],
            total: chartModel.values.total[hoveredIndex] || 0,
            manual: chartModel.values.manual[hoveredIndex] || 0,
            salary: chartModel.values.salary[hoveredIndex] || 0,
            contract: chartModel.values.contract[hoveredIndex] || 0,
            sales: chartModel.values.sales[hoveredIndex] || 0,
            xPct: (activePoint.x / CHART_FRAME.width) * 100,
            yPct: Math.max(10, ((activePoint.y - 68) / CHART_FRAME.height) * 100),
        };
    }, [chartModel, hoveredIndex]);

    const toggleSeries = (key) => {
        setSeriesVisible((current) => ({ ...current, [key]: !current[key] }));
    };

    const applyQuickRange = (key) => {
        const range = getQuickRange(key);
        setActiveQuickRange(key);

        if (startDateRef.current) {
            startDateRef.current.value = range.start;
        }
        if (endDateRef.current) {
            endDateRef.current.value = range.end;
        }

        formRef.current?.requestSubmit();
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="card bg-gradient-to-br from-[#eef9fb] via-white to-[#f5f8ff] p-5 md:p-6">
                <form ref={formRef} method="get" action={routes?.index} className="space-y-5">
                    <input ref={startDateRef} type="hidden" name="start_date" defaultValue={filters?.start_date || ''} />
                    <input ref={endDateRef} type="hidden" name="end_date" defaultValue={filters?.end_date || ''} />

                    <div className="rounded-2xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="min-w-0 flex-1">
                                <div className="section-label">Quick Range</div>
                                <div className="mt-3 inline-flex flex-wrap rounded-lg border border-slate-200 bg-white/90 p-1 text-xs font-semibold shadow-sm">
                                    {QUICK_RANGE_OPTIONS.map((option) => {
                                        const active = activeQuickRange === option.key;
                                        return (
                                            <button
                                                key={option.key}
                                                type="button"
                                                onClick={() => applyQuickRange(option.key)}
                                                className={`rounded-md px-3 py-1 ${active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                                            >
                                                {option.label}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 lg:justify-end">
                                <div className="text-[11px] uppercase tracking-[0.2em] text-emerald-700">Filtered Snapshot</div>
                                <div className="text-1xl font-semibold leading-none text-slate-900">{Number(entriesCount || 0)}</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div className="flex items-center justify-between gap-3">
                                <div className="section-label">Sources</div>
                                <div className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    {selectedSources.length} selected
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {[
                                    { key: 'manual', label: 'Manual' },
                                    { key: 'salary', label: 'Salary' },
                                    { key: 'contract_payout', label: 'Contract Payout' },
                                    { key: 'sales_payout', label: 'Sales Rep Payout' },
                                ].map((source) => (
                                    <label key={source.key} className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-3 text-sm font-medium text-slate-700">
                                        <input
                                            type="checkbox"
                                            name="sources[]"
                                            value={source.key}
                                            defaultChecked={selectedSources.includes(source.key)}
                                            className="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                        />
                                        <span>{source.label}</span>
                                    </label>
                                ))}
                            </div>

                            <div className="mt-4 flex flex-wrap items-center gap-3">
                                <button type="submit" className="inline-flex items-center rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                    Apply Filters
                                </button>
                                <a href={routes?.index} data-native="true" className="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800">
                                    Reset View
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#fff7f7] via-white to-[#f7fbff] p-5 md:p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div className="section-label">Expense Trend</div>
                        <div className="mt-1 text-sm text-slate-500">System overview style graph for daily, weekly, and monthly expense movement.</div>
                    </div>

                    <div className="inline-flex rounded-lg border border-slate-200 bg-white/90 p-1 text-xs font-semibold shadow-sm">
                        {PERIOD_OPTIONS.map((option) => (
                            <button
                                key={option.key}
                                type="button"
                                onClick={() => {
                                    setPeriod(option.key);
                                    setHoveredIndex(null);
                                }}
                                className={`rounded-md px-3 py-1 ${period === option.key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(CHART_SERIES).map(([key, config]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => toggleSeries(key)}
                                className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition ${
                                    seriesVisible[key]
                                        ? 'border-slate-200 bg-white text-slate-700 shadow-sm'
                                        : 'border-slate-200 bg-slate-50 text-slate-400'
                                }`}
                            >
                                <span className={`h-2.5 w-2.5 rounded-full ${config.legend}`} />
                                <span>{config.label}</span>
                            </button>
                        ))}
                    </div>

                    <div className="mt-4">
                        {chartModel.labels.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-16 text-center text-sm text-slate-500">
                                No expense trend data available for the selected filters.
                            </div>
                        ) : (
                            <div className="relative overflow-hidden rounded-2xl border border-slate-100 bg-slate-50/60 px-2 py-3">
                                <svg viewBox={`0 0 ${CHART_FRAME.width} ${CHART_FRAME.height}`} className="h-[320px] w-full">
                                    <defs>
                                        <linearGradient id="expenseTotalGradient" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stopColor="rgba(239, 68, 68, 0.30)" />
                                            <stop offset="100%" stopColor="rgba(239, 68, 68, 0.03)" />
                                        </linearGradient>
                                    </defs>

                                    {chartModel.ticks.map((tick, idx) => {
                                        const y = CHART_FRAME.padTop + ((CHART_FRAME.height - CHART_FRAME.padBottom - CHART_FRAME.padTop) / CHART_FRAME.rows) * idx;
                                        return (
                                            <g key={`grid-${idx}`}>
                                                <line
                                                    x1={CHART_FRAME.padLeft}
                                                    y1={y}
                                                    x2={CHART_FRAME.width - CHART_FRAME.padRight}
                                                    y2={y}
                                                    stroke="#e2e8f0"
                                                    strokeDasharray="4 6"
                                                />
                                                <text x={12} y={y + 4} fontSize="11" fill="#64748b">
                                                    {money(tick, currencySymbol, '')}
                                                </text>
                                            </g>
                                        );
                                    })}

                                    {seriesVisible.total ? (
                                        <>
                                            <path d={pointsAreaPath(chartModel.points.total, chartModel.baseY)} fill="url(#expenseTotalGradient)" stroke="none" />
                                            <path d={pointsPath(chartModel.points.total)} fill="none" stroke={CHART_SERIES.total.stroke} strokeWidth="2.4" />
                                            {chartModel.points.total.map((point, idx) => (
                                                <circle key={`total-dot-${idx}`} cx={point.x} cy={point.y} r="3" fill={CHART_SERIES.total.pointFill} stroke={CHART_SERIES.total.pointStroke} strokeWidth="1.1" />
                                            ))}
                                        </>
                                    ) : null}

                                    {seriesVisible.manual ? (
                                        <>
                                            <path d={pointsPath(chartModel.points.manual)} fill="none" stroke={CHART_SERIES.manual.stroke} strokeWidth="2" />
                                            {chartModel.points.manual.map((point, idx) => (
                                                <circle key={`manual-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.manual.pointFill} stroke={CHART_SERIES.manual.pointStroke} strokeWidth="1" />
                                            ))}
                                        </>
                                    ) : null}

                                    {seriesVisible.salary ? (
                                        <>
                                            <path d={pointsPath(chartModel.points.salary)} fill="none" stroke={CHART_SERIES.salary.stroke} strokeWidth="2" />
                                            {chartModel.points.salary.map((point, idx) => (
                                                <circle key={`salary-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.salary.pointFill} stroke={CHART_SERIES.salary.pointStroke} strokeWidth="1" />
                                            ))}
                                        </>
                                    ) : null}

                                    {seriesVisible.contract ? (
                                        <>
                                            <path d={pointsPath(chartModel.points.contract)} fill="none" stroke={CHART_SERIES.contract.stroke} strokeWidth="2" />
                                            {chartModel.points.contract.map((point, idx) => (
                                                <circle key={`contract-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.contract.pointFill} stroke={CHART_SERIES.contract.pointStroke} strokeWidth="1" />
                                            ))}
                                        </>
                                    ) : null}

                                    {seriesVisible.sales ? (
                                        <>
                                            <path d={pointsPath(chartModel.points.sales)} fill="none" stroke={CHART_SERIES.sales.stroke} strokeWidth="2" />
                                            {chartModel.points.sales.map((point, idx) => (
                                                <circle key={`sales-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.sales.pointFill} stroke={CHART_SERIES.sales.pointStroke} strokeWidth="1" />
                                            ))}
                                        </>
                                    ) : null}

                                    {hoverRegions.map((region) => (
                                        <rect
                                            key={`hover-zone-${region.index}`}
                                            x={region.x}
                                            y={CHART_FRAME.padTop}
                                            width={region.width}
                                            height={chartModel.baseY - CHART_FRAME.padTop}
                                            fill="transparent"
                                            onMouseEnter={() => setHoveredIndex(region.index)}
                                            onMouseMove={() => setHoveredIndex(region.index)}
                                            onClick={() => setHoveredIndex(region.index)}
                                        />
                                    ))}

                                    {chartModel.xTickIndexes.map((index) => {
                                        const point = chartModel.points.total[index];
                                        if (!point) {
                                            return null;
                                        }

                                        return (
                                            <text
                                                key={`x-label-${index}`}
                                                x={point.x}
                                                y={CHART_FRAME.height - 12}
                                                textAnchor="end"
                                                transform={`rotate(-35 ${point.x} ${CHART_FRAME.height - 12})`}
                                                fontSize="11"
                                                fill="#64748b"
                                            >
                                                {chartModel.labels[index]}
                                            </text>
                                        );
                                    })}
                                </svg>

                                {hoverDetails ? (
                                    <div
                                        className="pointer-events-none absolute z-10 -translate-x-1/2 rounded-2xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl"
                                        style={{ left: `${hoverDetails.xPct}%`, top: `${hoverDetails.yPct}%` }}
                                    >
                                        <div className="text-[11px] font-semibold text-slate-200">{hoverDetails.label}</div>
                                        <div className="mt-1">Total: {money(hoverDetails.total, currencySymbol, currencyCode)}</div>
                                        <div className="text-slate-300">Manual: {money(hoverDetails.manual, currencySymbol, currencyCode)}</div>
                                        <div className="text-slate-300">Salary: {money(hoverDetails.salary, currencySymbol, currencyCode)}</div>
                                        <div className="text-slate-300">Contract: {money(hoverDetails.contract, currencySymbol, currencyCode)}</div>
                                        <div className="text-slate-300">Sales: {money(hoverDetails.sales, currencySymbol, currencyCode)}</div>
                                    </div>
                                ) : null}
                            </div>
                        )}
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <StatusMetric data={expenseStatus?.today} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.week} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.month} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                        <StatusMetric data={expenseStatus?.filtered} currencyCode={currencyCode} currencySymbol={currencySymbol} />
                    </div>
                </div>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#fff6f4] via-white to-[#f5fbff] p-5 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Google AI Summary</div>
                        <div className="mt-1 text-sm text-slate-500">Detailed overall expense summary with source mix, category pressure, and recent payout activity.</div>
                    </div>
                    <a href={routes?.refresh_ai || `${routes?.index}?ai=refresh`} data-native="true" className="inline-flex rounded-full border border-rose-200 bg-white px-3 py-1 text-xs font-semibold text-rose-700 hover:border-rose-300 hover:text-rose-600">
                        Refresh AI
                    </a>
                </div>

                <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    {sourceSummaryCards.map((item) => (
                        <AISummaryMetric
                            key={item.label}
                            label={item.label}
                            amount={item.amount}
                            note={item.note}
                            currencyCode={currencyCode}
                            currencySymbol={currencySymbol}
                            accent={item.accent}
                        />
                    ))}
                </div>

                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,0.95fr)]">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">AI Narrative</div>
                            <div className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                {selectedSources.length} sources
                            </div>
                            <div className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                {Number(entriesCount || 0)} entries
                            </div>
                            <div className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                                YTD {money(yearlyTotal, currencySymbol, currencyCode)}
                            </div>
                        </div>
                        <div className="mt-3 rounded-xl border border-slate-100 bg-slate-50/70 p-4 text-sm whitespace-pre-line leading-relaxed text-slate-600">
                            {aiSummary || (aiError ? `AI summary unavailable: ${aiError}` : 'AI summary is not available yet.')}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <SummaryListCard
                            title="Top Categories"
                            emptyLabel="No expense categories found."
                            items={Array.isArray(topCategories) ? topCategories.map((item) => ({
                                label: item?.name || '--',
                                value: money(item?.total, currencySymbol, currencyCode),
                            })) : []}
                        />
                        <SummaryListCard
                            title="Top People"
                            emptyLabel="No expense payees found."
                            items={topPeople}
                        />
                    </div>
                </div>

                <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="flex items-center justify-between gap-3">
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Recent Expense Activity</div>
                        <div className="text-xs text-slate-500">Latest filtered entries</div>
                    </div>

                    <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {(Array.isArray(recentEntries) ? recentEntries.slice(0, 6) : []).map((entry) => (
                            <div key={entry?.key || `${entry?.title}-${entry?.expense_date_display}`} className="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-semibold text-slate-800">{entry?.title || '--'}</div>
                                        <div className="mt-1 text-xs text-slate-500">{entry?.expense_date_display || '--'} | {entry?.source_label || '--'}</div>
                                    </div>
                                    <div className="shrink-0 text-sm font-semibold text-slate-900">
                                        {entry?.amount_display || money(0, currencySymbol, currencyCode)}
                                    </div>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                    <span>{entry?.category_name || '--'}</span>
                                    <span>{entry?.person_name || '--'}</span>
                                </div>
                            </div>
                        ))}

                        {Array.isArray(recentEntries) && recentEntries.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-4 text-sm text-slate-500">
                                No recent expense entries available for the current filters.
                            </div>
                        ) : null}
                    </div>
                </div>
            </div>

            <div className="mt-5 grid gap-5 xl:grid-cols-3">
                <BreakdownCard
                    title="Expense by Category"
                    items={Array.isArray(categoryTotals) ? categoryTotals : []}
                    emptyText="No category totals found for the selected filters."
                    getKey={(item, index) => `${item?.category_id || item?.name || 'category'}-${index}`}
                    getLabel={(item) => item?.name || '--'}
                    currencyCode={currencyCode}
                    currencySymbol={currencySymbol}
                />
                <BreakdownCard
                    title="Expense by Person"
                    items={Array.isArray(employeeTotals) ? employeeTotals : []}
                    emptyText="No employee or payee totals found for the selected filters."
                    getKey={(item, index) => `${item?.label || 'person'}-${index}`}
                    getLabel={(item) => item?.label || '--'}
                    currencyCode={currencyCode}
                    currencySymbol={currencySymbol}
                />
                <BreakdownCard
                    title="Sales Rep Payouts"
                    items={Array.isArray(salesRepTotals) ? salesRepTotals : []}
                    emptyText="No sales rep payouts found for the selected filters."
                    getKey={(item, index) => `${item?.label || 'sales'}-${index}`}
                    getLabel={(item) => item?.label || '--'}
                    currencyCode={currencyCode}
                    currencySymbol={currencySymbol}
                />
            </div>
        </>
    );
}

Dashboard.title = 'Expense Dashboard';
