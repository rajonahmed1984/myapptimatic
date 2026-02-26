import React, { useMemo, useState } from 'react';
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
        label: 'Total Income',
        stroke: '#10b981',
        pointFill: '#a7f3d0',
        pointStroke: '#059669',
        fill: 'rgba(16, 185, 129, 0.16)',
        legend: 'bg-emerald-400',
    },
    manual: {
        label: 'Manual Income',
        stroke: '#2563eb',
        pointFill: '#bfdbfe',
        pointStroke: '#1d4ed8',
        fill: 'rgba(37, 99, 235, 0.08)',
        legend: 'bg-blue-400',
    },
    system: {
        label: 'System Income',
        stroke: '#f59e0b',
        pointFill: '#fde68a',
        pointStroke: '#d97706',
        fill: 'rgba(245, 158, 11, 0.08)',
        legend: 'bg-amber-400',
    },
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

    const linePath = pointsPath(points);
    const first = points[0];
    const last = points[points.length - 1];

    return `${linePath} L${last.x.toFixed(2)} ${baseY.toFixed(2)} L${first.x.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

function yTicks(maxValue, count) {
    const safeMax = Math.max(1, Number(maxValue || 0));
    return Array.from({ length: count + 1 }, (_, idx) => {
        const ratio = (count - idx) / count;
        return safeMax * ratio;
    });
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

export default function Dashboard({
    pageTitle = 'Income Dashboard',
    categories = [],
    filters = {},
    totals = {},
    income_status = {},
    period_series = {},
    entries_count = 0,
    currency = {},
    ai = {},
    whmcs_errors = [],
    routes = {},
}) {
    const [period, setPeriod] = useState('day');
    const [seriesVisible, setSeriesVisible] = useState({
        total: true,
        manual: true,
        system: true,
    });
    const [hoveredIndex, setHoveredIndex] = useState(null);

    const activeSeries = period_series?.[period] || { labels: [], total: [], manual: [], system: [] };
    const sources = Array.isArray(filters?.sources) ? filters.sources : [];

    const chartModel = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const seriesLength = labels.length;
        const totalSeries = asNumberList(activeSeries?.total, seriesLength);
        const manualSeries = asNumberList(activeSeries?.manual, seriesLength);
        const systemSeries = asNumberList(activeSeries?.system, seriesLength);
        const maxValue = Math.max(1, ...totalSeries, ...manualSeries, ...systemSeries);
        const baseY = CHART_FRAME.height - CHART_FRAME.padBottom;

        return {
            labels,
            seriesLength,
            totalSeries,
            manualSeries,
            systemSeries,
            maxValue,
            ticks: yTicks(maxValue, CHART_FRAME.rows),
            xTickIndexes: xTickIndexes(seriesLength),
            points: {
                total: buildChartPoints(totalSeries, maxValue),
                manual: buildChartPoints(manualSeries, maxValue),
                system: buildChartPoints(systemSeries, maxValue),
            },
            baseY,
        };
    }, [activeSeries]);

    const hoverRegions = useMemo(() => {
        const points = Array.isArray(chartModel.points?.total) ? chartModel.points.total : [];
        if (points.length === 0) {
            return [];
        }

        return points.map((point, index) => {
            const prevX = points[index - 1]?.x ?? CHART_FRAME.padLeft;
            const nextX = points[index + 1]?.x ?? (CHART_FRAME.width - CHART_FRAME.padRight);
            const leftEdge = index === 0 ? CHART_FRAME.padLeft : (prevX + point.x) / 2;
            const rightEdge = index === points.length - 1 ? (CHART_FRAME.width - CHART_FRAME.padRight) : (point.x + nextX) / 2;

            return {
                index,
                x: leftEdge,
                width: Math.max(1, rightEdge - leftEdge),
            };
        });
    }, [chartModel.points]);

    const hoverDetails = useMemo(() => {
        if (hoveredIndex === null || hoveredIndex < 0 || hoveredIndex >= chartModel.seriesLength) {
            return null;
        }

        const point = chartModel.points.total?.[hoveredIndex];
        if (!point) {
            return null;
        }

        const xPct = (point.x / CHART_FRAME.width) * 100;
        const yPct = (point.y / CHART_FRAME.height) * 100;

        return {
            label: chartModel.labels?.[hoveredIndex] || '--',
            total: Number(chartModel.totalSeries?.[hoveredIndex] || 0),
            manual: Number(chartModel.manualSeries?.[hoveredIndex] || 0),
            system: Number(chartModel.systemSeries?.[hoveredIndex] || 0),
            xPct: Math.max(12, Math.min(88, xPct)),
            yPct: Math.max(8, Math.min(62, yPct - 6)),
            pointX: point.x,
        };
    }, [hoveredIndex, chartModel]);

    const toggleSeries = (key) => {
        setSeriesVisible((previous) => ({
            ...previous,
            [key]: !previous[key],
        }));
    };

    const hasChartData = chartModel.seriesLength > 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card bg-gradient-to-br from-[#eef8fb] via-white to-[#f4f8ff] p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Income Controls</div>
                        <div className="mt-1 text-xs text-slate-500">Filter sources and period, then review day/week/month performance.</div>
                    </div>
                    <div className="rounded-full border border-slate-200 bg-white/90 px-3 py-1 text-xs font-semibold text-slate-600 shadow-sm">
                        Filtered entries: {Number(entries_count || 0)}
                    </div>
                </div>

                <form method="GET" action={routes?.dashboard} data-native="true" className="mt-3 grid gap-2 text-sm md:grid-cols-4 lg:grid-cols-5">
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
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>{category.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="mt-6 flex items-center gap-2">
                        <button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-teal-500">Apply</button>
                        <a href={routes?.dashboard} data-native="true" className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Reset</a>
                    </div>
                    <div className="md:col-span-4 lg:col-span-5">
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Sources</div>
                        <div className="mt-1.5 flex flex-wrap gap-3 text-xs text-slate-600">
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="manual" defaultChecked={sources.includes('manual')} /> Manual</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="system" defaultChecked={sources.includes('system')} /> System</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="credit_settlement" defaultChecked={sources.includes('credit_settlement')} /> Credit Settlement</label>
                            <label className="flex items-center gap-2"><input type="checkbox" name="sources[]" value="carrothost" defaultChecked={sources.includes('carrothost')} /> CarrotHost</label>
                        </div>
                    </div>
                </form>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#edf8f7] via-white to-[#f3f7ff] p-5 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Income Trend</div>
                        <div className="mt-1 text-sm text-slate-500">System Overview style graph for day/week/month income status.</div>
                    </div>
                    <div className="inline-flex rounded-lg border border-slate-200 bg-white/90 p-1 text-xs font-semibold shadow-sm">
                        {PERIOD_OPTIONS.map((item) => (
                            <button
                                key={item.key}
                                type="button"
                                className={`rounded-md px-3 py-1 ${period === item.key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                                onClick={() => setPeriod(item.key)}
                            >
                                {item.label}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-4 grid gap-4 xl:grid-cols-[minmax(0,1.85fr)_minmax(260px,1fr)]">
                    <div className="rounded-2xl border border-slate-200 bg-white/90 p-3 shadow-sm">
                        <div className="mb-2 flex flex-wrap gap-2">
                            {Object.entries(CHART_SERIES).map(([key, config]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => toggleSeries(key)}
                                    className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold ${
                                        seriesVisible[key] ? 'border-slate-300 bg-white text-slate-700' : 'border-slate-200 bg-slate-50 text-slate-400'
                                    }`}
                                >
                                    <span className={`h-2 w-2 rounded-full ${config.legend}`} />
                                    {config.label}
                                </button>
                            ))}
                        </div>

                        {!hasChartData ? (
                            <div className="py-16 text-center text-sm text-slate-500">No trend data available for selected filters.</div>
                        ) : (
                            <div className="relative">
                                <svg viewBox={`0 0 ${CHART_FRAME.width} ${CHART_FRAME.height}`} className="h-auto w-full">
                                <defs>
                                    <linearGradient id="incomeTotalGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stopColor={CHART_SERIES.total.fill} />
                                        <stop offset="100%" stopColor="rgba(16, 185, 129, 0.02)" />
                                    </linearGradient>
                                </defs>

                                {chartModel.ticks.map((value, index) => {
                                    const ratio = index / CHART_FRAME.rows;
                                    const y = CHART_FRAME.padTop + ratio * (CHART_FRAME.height - CHART_FRAME.padTop - CHART_FRAME.padBottom);
                                    return (
                                        <g key={`y-grid-${index}`}>
                                            <line
                                                x1={CHART_FRAME.padLeft}
                                                y1={y}
                                                x2={CHART_FRAME.width - CHART_FRAME.padRight}
                                                y2={y}
                                                stroke="#e2e8f0"
                                                strokeDasharray={index === CHART_FRAME.rows ? undefined : '4 4'}
                                            />
                                            <text x={CHART_FRAME.padLeft - 10} y={y + 4} textAnchor="end" fontSize="11" fill="#64748b">
                                                {Math.round(value)}
                                            </text>
                                        </g>
                                    );
                                })}

                                <text x={CHART_FRAME.padLeft - 42} y={CHART_FRAME.padTop - 4} textAnchor="start" fontSize="12" fill="#334155">
                                    Income
                                </text>

                                {hoverDetails ? (
                                    <line
                                        x1={hoverDetails.pointX}
                                        y1={CHART_FRAME.padTop}
                                        x2={hoverDetails.pointX}
                                        y2={chartModel.baseY}
                                        stroke="#0f172a"
                                        strokeOpacity="0.15"
                                        strokeDasharray="3 4"
                                    />
                                ) : null}

                                {seriesVisible.total ? (
                                    <>
                                        <path d={pointsAreaPath(chartModel.points.total, chartModel.baseY)} fill="url(#incomeTotalGradient)" stroke="none" />
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

                                {seriesVisible.system ? (
                                    <>
                                        <path d={pointsPath(chartModel.points.system)} fill="none" stroke={CHART_SERIES.system.stroke} strokeWidth="2" />
                                        {chartModel.points.system.map((point, idx) => (
                                            <circle key={`system-dot-${idx}`} cx={point.x} cy={point.y} r="2.2" fill={CHART_SERIES.system.pointFill} stroke={CHART_SERIES.system.pointStroke} strokeWidth="1" />
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
                                        <div className="mt-1">Total: {money(hoverDetails.total, currency?.symbol, currency?.code)}</div>
                                        <div className="text-slate-300">Manual: {money(hoverDetails.manual, currency?.symbol, currency?.code)}</div>
                                        <div className="text-slate-300">System: {money(hoverDetails.system, currency?.symbol, currency?.code)}</div>
                                    </div>
                                ) : null}
                            </div>
                        )}
                    </div>

                    <div className="space-y-3">
                        <StatusMetric data={income_status?.today} currency={currency} />
                        <StatusMetric data={income_status?.week} currency={currency} />
                        <StatusMetric data={income_status?.month} currency={currency} />
                        <StatusMetric data={income_status?.overall} currency={currency} />
                    </div>
                </div>
            </div>

            <div className="mt-5 card bg-gradient-to-br from-[#eefaf5] via-white to-[#f4fbff] p-5 md:p-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Google AI Summary</div>
                        <div className="mt-1 text-sm text-slate-500">AI-generated snapshot based on selected income filters.</div>
                    </div>
                    <a href={routes?.ai_refresh || `${routes?.dashboard}?ai=refresh`} data-native="true" className="inline-flex rounded-full border border-emerald-200 bg-white px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300 hover:text-emerald-600">
                        Refresh AI
                    </a>
                </div>
                <div className="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-sm whitespace-pre-line text-slate-600 shadow-sm">
                    {ai?.summary || (ai?.error ? `AI summary unavailable: ${ai.error}` : 'AI summary is not available yet.')}
                </div>
            </div>

            {whmcs_errors.length > 0 ? (
                <div className="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <div className="font-semibold text-amber-900">WHMCS warnings</div>
                    <ul className="mt-2 list-disc pl-5">
                        {whmcs_errors.map((error, index) => <li key={index}>{error}</li>)}
                    </ul>
                </div>
            ) : null}
        </>
    );
}

function StatusMetric({ data = {}, currency = {} }) {
    const change = data?.change_percent;
    const changeNumber = change === null || change === undefined ? null : Number(change);
    const tone = changeNumber === null ? 'text-slate-500' : changeNumber >= 0 ? 'text-emerald-600' : 'text-rose-600';
    const icon = (data?.label || 'I').slice(0, 1).toUpperCase();

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-700 text-xs font-semibold text-white">
                    {icon}
                </div>
                <div className="min-w-0">
                    <div className="truncate text-xs text-slate-500">{data?.label || '--'}</div>
                    <div className="whitespace-nowrap text-2xl font-semibold leading-7 text-slate-900">{money(data?.amount, currency?.symbol, currency?.code)}</div>
                    <div className={`truncate text-xs font-semibold ${tone}`}>
                        {changeText(change)} {data?.comparison_label || ''}
                    </div>
                </div>
            </div>
        </div>
    );
}
