import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (isActive) => (
    isActive
        ? 'border-emerald-200 text-emerald-700 bg-emerald-50'
        : 'border-slate-300 text-slate-600 bg-slate-50'
);

const TAX_CHART_FRAME = {
    width: 1120,
    height: 300,
    padLeft: 56,
    padRight: 24,
    padTop: 20,
    padBottom: 38,
    rows: 4,
};

const TAX_CHART_SERIES = {
    total: {
        label: 'Total Tax',
        stroke: '#0f766e',
        pointFill: '#99f6e4',
        pointStroke: '#0f766e',
        fill: 'rgba(20, 184, 166, 0.16)',
        legend: 'bg-teal-400',
    },
};

const safeNum = (value) => {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number : 0;
};

const money = (currencyCode, value) => `${currencyCode} ${safeNum(value).toFixed(2)}`;

const percentage = (value) => {
    if (value === null || value === undefined) return 'N/A';
    return `${value >= 0 ? '+' : ''}${safeNum(value).toFixed(1)}%`;
};

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

    const left = TAX_CHART_FRAME.padLeft;
    const right = TAX_CHART_FRAME.width - TAX_CHART_FRAME.padRight;
    const top = TAX_CHART_FRAME.padTop;
    const bottom = TAX_CHART_FRAME.height - TAX_CHART_FRAME.padBottom;
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

const pointsPath = (points = []) => {
    if (!points.length) return '';
    return points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ');
};

const pointsAreaPath = (points = [], baseY = 0) => {
    if (!points.length) return '';
    return `${pointsPath(points)} L ${points[points.length - 1].x} ${baseY} L ${points[0].x} ${baseY} Z`;
};

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

export default function Index({
    pageTitle = 'Tax Settings',
    heading = 'Tax settings',
    subheading = 'Configure tax mode, default rates, and invoice notes.',
    routes = {},
    settings_form = {},
    rate_form = {},
    quick_reference = {},
    rate_options = [],
    rates = [],
    currency_code = 'BDT',
    tax_analytics = {},
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const summary = tax_analytics?.summary || {};
    const monthRows = tax_analytics?.monthly_rows || [];
    const yearRows = tax_analytics?.yearly_rows || [];
    const trendLabels = tax_analytics?.trend?.labels || [];
    const trendSeries = asNumberList(tax_analytics?.trend?.series || []);
    const [hoveredIndex, setHoveredIndex] = React.useState(null);

    const chartModel = React.useMemo(() => {
        const labels = Array.isArray(trendLabels) ? trendLabels : [];
        const seriesLength = labels.length;
        const totalSeries = asNumberList(trendSeries, seriesLength);
        const maxValue = Math.max(1, ...totalSeries);
        const baseY = TAX_CHART_FRAME.height - TAX_CHART_FRAME.padBottom;

        return {
            labels,
            seriesLength,
            totalSeries,
            maxValue,
            ticks: yTicks(maxValue, TAX_CHART_FRAME.rows),
            xTickIndexes: xTickIndexes(seriesLength),
            points: {
                total: buildChartPoints(totalSeries, maxValue),
            },
            baseY,
        };
    }, [trendLabels, trendSeries]);

    const hoverRegions = React.useMemo(() => {
        const points = Array.isArray(chartModel.points?.total) ? chartModel.points.total : [];
        if (points.length === 0) {
            return [];
        }

        return points.map((point, index) => {
            const prevX = points[index - 1]?.x ?? TAX_CHART_FRAME.padLeft;
            const nextX = points[index + 1]?.x ?? (TAX_CHART_FRAME.width - TAX_CHART_FRAME.padRight);
            const leftEdge = index === 0 ? TAX_CHART_FRAME.padLeft : (prevX + point.x) / 2;
            const rightEdge = index === points.length - 1 ? (TAX_CHART_FRAME.width - TAX_CHART_FRAME.padRight) : (point.x + nextX) / 2;

            return {
                index,
                x: leftEdge,
                width: Math.max(1, rightEdge - leftEdge),
            };
        });
    }, [chartModel.points]);

    const hoverDetails = React.useMemo(() => {
        if (hoveredIndex === null || hoveredIndex < 0 || hoveredIndex >= chartModel.seriesLength) {
            return null;
        }

        const point = chartModel.points.total?.[hoveredIndex];
        if (!point) {
            return null;
        }

        const xPct = (point.x / TAX_CHART_FRAME.width) * 100;
        const yPct = (point.y / TAX_CHART_FRAME.height) * 100;

        return {
            label: chartModel.labels?.[hoveredIndex] || '--',
            total: Number(chartModel.totalSeries?.[hoveredIndex] || 0),
            xPct: Math.max(12, Math.min(88, xPct)),
            yPct: Math.max(8, Math.min(62, yPct - 6)),
            pointX: point.x,
        };
    }, [hoveredIndex, chartModel]);

    const hasChartData = chartModel.seriesLength > 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">{heading}</div>
                    <div className="mt-1 text-sm text-slate-500">{subheading}</div>
                </div>
                <a
                    href={routes?.reports}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    View Reports
                </a>
            </div>

            <div className="card p-6">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Effective Year Tax</div>
                        <div className="text-sm text-slate-500">Month-to-month and year-to-year tax performance.</div>
                    </div>
                    <form method="GET" action={routes?.index} data-native="true" className="flex items-center gap-2">
                        <label className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Effective year</label>
                        <select
                            name="effective_year"
                            defaultValue={String(tax_analytics?.effective_year || '')}
                            onChange={(event) => event.currentTarget.form?.submit()}
                            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        >
                            {(tax_analytics?.effective_year_options || []).map((year) => (
                                <option key={year.value} value={year.value}>
                                    {year.label}
                                </option>
                            ))}
                        </select>
                    </form>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">This month tax</div>
                        <div className="mt-2 text-xl font-semibold text-slate-900">{money(currency_code, summary.this_month_total)}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">This year tax</div>
                        <div className="mt-2 text-xl font-semibold text-slate-900">{money(currency_code, summary.this_year_total)}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">All time tax</div>
                        <div className="mt-2 text-xl font-semibold text-slate-900">{money(currency_code, summary.all_time_total)}</div>
                    </div>
                </div>

                <div className="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-4">
                    <div className="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                        Tax Trend ({tax_analytics?.period_label || '--'})
                    </div>
                    {!hasChartData ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            No tax trend data available for this effective year.
                        </div>
                    ) : (
                        <div className="relative w-full overflow-x-auto">
                            <div className="mb-2 flex flex-wrap gap-2">
                                <div className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                    <span className={`h-2 w-2 rounded-full ${TAX_CHART_SERIES.total.legend}`} />
                                    {TAX_CHART_SERIES.total.label}
                                </div>
                            </div>
                            <svg viewBox={`0 0 ${TAX_CHART_FRAME.width} ${TAX_CHART_FRAME.height}`} className="h-auto min-w-[700px] w-full">
                                <defs>
                                    <linearGradient id="taxTrendFillGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stopColor={TAX_CHART_SERIES.total.fill} />
                                        <stop offset="100%" stopColor="rgba(20, 184, 166, 0.02)" />
                                    </linearGradient>
                                </defs>

                                {chartModel.ticks.map((value, index) => {
                                    const ratio = index / TAX_CHART_FRAME.rows;
                                    const y = TAX_CHART_FRAME.padTop + ratio * (TAX_CHART_FRAME.height - TAX_CHART_FRAME.padTop - TAX_CHART_FRAME.padBottom);
                                    return (
                                        <g key={`tax-grid-${index}`}>
                                            <line
                                                x1={TAX_CHART_FRAME.padLeft}
                                                y1={y}
                                                x2={TAX_CHART_FRAME.width - TAX_CHART_FRAME.padRight}
                                                y2={y}
                                                stroke="#e2e8f0"
                                                strokeDasharray={index === TAX_CHART_FRAME.rows ? undefined : '4 4'}
                                            />
                                            <text x={TAX_CHART_FRAME.padLeft - 8} y={y + 4} textAnchor="end" fontSize="11" fill="#64748b">
                                                {Math.round(value)}
                                            </text>
                                        </g>
                                    );
                                })}

                                <text x={TAX_CHART_FRAME.padLeft - 42} y={TAX_CHART_FRAME.padTop - 4} textAnchor="start" fontSize="12" fill="#334155">
                                    Tax
                                </text>

                                {hoverDetails ? (
                                    <line
                                        x1={hoverDetails.pointX}
                                        y1={TAX_CHART_FRAME.padTop}
                                        x2={hoverDetails.pointX}
                                        y2={chartModel.baseY}
                                        stroke="#0f172a"
                                        strokeOpacity="0.15"
                                        strokeDasharray="3 4"
                                    />
                                ) : null}

                                <path d={pointsAreaPath(chartModel.points.total, chartModel.baseY)} fill="url(#taxTrendFillGradient)" stroke="none" />
                                <path d={pointsPath(chartModel.points.total)} fill="none" stroke={TAX_CHART_SERIES.total.stroke} strokeWidth="2.4" />
                                {chartModel.points.total.map((point, index) => (
                                    <circle key={`tax-dot-${index}`} cx={point.x} cy={point.y} r="3" fill={TAX_CHART_SERIES.total.pointFill} stroke={TAX_CHART_SERIES.total.pointStroke} strokeWidth="1.1">
                                        <title>{`${chartModel.labels[index] || '--'}: ${money(currency_code, point.value)}`}</title>
                                    </circle>
                                ))}

                                {hoverRegions.map((region) => (
                                    <rect
                                        key={`hover-zone-${region.index}`}
                                        x={region.x}
                                        y={TAX_CHART_FRAME.padTop}
                                        width={region.width}
                                        height={chartModel.baseY - TAX_CHART_FRAME.padTop}
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
                                        key={`tax-label-${index}`}
                                        x={point.x}
                                        y={TAX_CHART_FRAME.height - 10}
                                        textAnchor="end"
                                        transform={`rotate(-35 ${point.x} ${TAX_CHART_FRAME.height - 10})`}
                                        fontSize="11"
                                        fill="#64748b"
                                    >
                                        {chartModel.labels[index] || '--'}
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
                                    <div className="mt-1">Tax: {money(currency_code, hoverDetails.total)}</div>
                                </div>
                            ) : null}
                        </div>
                    )}
                </div>

                <div className="mt-5 grid gap-5 xl:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Month to month tax</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="whitespace-nowrap px-2 py-2 text-left">Month</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">Tax</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">Invoices</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">M/M</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {monthRows.length ? (
                                        monthRows.map((row) => (
                                            <tr key={row.month_number} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-2">{row.month_label}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right font-semibold text-slate-900">
                                                    {money(currency_code, row.tax_total)}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">{row.invoice_count}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                            row.change_direction === 'up'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : row.change_direction === 'down'
                                                                  ? 'bg-rose-50 text-rose-700'
                                                                  : 'bg-slate-100 text-slate-600'
                                                        }`}
                                                    >
                                                        {percentage(row.change_percent)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-2 py-4 text-center text-slate-500">
                                                No month-wise tax data found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Year to year tax</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="whitespace-nowrap px-2 py-2 text-left">Year</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">Tax</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">Invoices</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right">Y/Y</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {yearRows.length ? (
                                        yearRows.map((row) => (
                                            <tr key={row.year} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-2">{row.year}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right font-semibold text-slate-900">
                                                    {money(currency_code, row.tax_total)}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">{row.invoice_count}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                            row.change_direction === 'up'
                                                                ? 'bg-emerald-50 text-emerald-700'
                                                                : row.change_direction === 'down'
                                                                  ? 'bg-rose-50 text-rose-700'
                                                                  : 'bg-slate-100 text-slate-600'
                                                        }`}
                                                    >
                                                        {percentage(row.change_percent)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-2 py-4 text-center text-slate-500">
                                                No year-wise tax data found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[2fr_3fr]">
                <div className="card p-6">
                    <div className="section-label">Settings</div>
                    <div className="mt-3 rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Quick reference</div>
                        <div className="mt-2 space-y-2 text-sm text-slate-600">
                            <div>
                                Default mode: <span className="font-semibold text-slate-900">{quick_reference?.mode || '--'}</span>
                            </div>
                            <div>
                                Default rate: <span className="font-semibold text-slate-900">{quick_reference?.default_rate_name || 'None'}</span>
                            </div>
                            <div>
                                Invoices label: <span className="font-semibold text-slate-900">Tax</span>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action={routes?.settings_update} className="mt-4 grid gap-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="PUT" />
                        <div className="flex items-center gap-2">
                            <input type="hidden" name="enabled" value="0" />
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                defaultChecked={Boolean(settings_form?.enabled)}
                            />
                            <span className="text-xs text-slate-600">Enable tax system</span>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Default tax mode</label>
                            <select
                                name="tax_mode_default"
                                defaultValue={settings_form?.tax_mode_default || 'exclusive'}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="exclusive">Exclusive (add tax on top)</option>
                                <option value="inclusive">Inclusive (included in total)</option>
                            </select>
                            {errors.tax_mode_default ? <div className="mt-1 text-xs text-rose-600">{errors.tax_mode_default}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Default tax rate</label>
                            <select
                                name="default_tax_rate_id"
                                defaultValue={settings_form?.default_tax_rate_id || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="">No default</option>
                                {rate_options.map((rate) => (
                                    <option key={rate.id} value={rate.id}>
                                        {rate.label}
                                    </option>
                                ))}
                            </select>
                            {errors.default_tax_rate_id ? <div className="mt-1 text-xs text-rose-600">{errors.default_tax_rate_id}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Invoice tax label</label>
                            <div className="mt-1 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900">
                                Tax
                            </div>
                            <div className="mt-1 text-xs text-slate-500">This label is fixed system-wide.</div>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Invoice tax note template</label>
                            <textarea
                                name="invoice_tax_note_template"
                                rows={3}
                                defaultValue={settings_form?.invoice_tax_note_template || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            <div className="mt-1 text-xs text-slate-500">Use {'{rate}'} to display the applied percentage.</div>
                            {errors.invoice_tax_note_template ? <div className="mt-1 text-xs text-rose-600">{errors.invoice_tax_note_template}</div> : null}
                        </div>

                        <div className="flex justify-end">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <div className="card p-6">
                    <div className="section-label">Tax rates</div>
                    <form method="POST" action={routes?.rate_store} className="mt-4 grid gap-3 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input
                            name="name"
                            defaultValue={rate_form?.name || ''}
                            placeholder="Rate name"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="rate_percent"
                            defaultValue={rate_form?.rate_percent || ''}
                            placeholder="Rate %"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.rate_percent ? <div className="mt-1 text-xs text-rose-600">{errors.rate_percent}</div> : null}
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="effective_from"
                            defaultValue={rate_form?.effective_from || ''}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.effective_from ? <div className="mt-1 text-xs text-rose-600">{errors.effective_from}</div> : null}
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="effective_to"
                            defaultValue={rate_form?.effective_to || ''}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.effective_to ? <div className="mt-1 text-xs text-rose-600">{errors.effective_to}</div> : null}
                        <div className="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                defaultChecked={Boolean(rate_form?.is_active)}
                            />
                            <span className="text-xs text-slate-600">Active</span>
                        </div>
                        <div>
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Add Rate
                            </button>
                        </div>
                    </form>

                    <div className="mt-5 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="py-2 px-3">Name</th>
                                    <th className="py-2 px-3">Rate</th>
                                    <th className="py-2 px-3">Effective</th>
                                    <th className="py-2 px-3">Status</th>
                                    <th className="py-2 px-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.length > 0 ? (
                                    rates.map((rate) => (
                                        <tr key={rate.id} className="border-b border-slate-100">
                                            <td className="py-2 px-3 font-semibold text-slate-900">{rate.name}</td>
                                            <td className="py-2 px-3">{rate.rate_percent_display}</td>
                                            <td className="py-2 px-3">
                                                <div>{rate.effective_from_display}</div>
                                                <div className="text-xs text-slate-500">to {rate.effective_to_display}</div>
                                            </td>
                                            <td className="py-2 px-3">
                                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(rate.is_active)}`}>
                                                    {rate.status_label}
                                                </span>
                                            </td>
                                            <td className="py-2 px-3 text-right">
                                                <div className="flex justify-end gap-3 text-xs font-semibold">
                                                    <a href={rate?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action={rate?.routes?.destroy}
                                                        data-native="true"
                                                        data-delete-confirm
                                                        data-confirm-name={rate.confirm_name}
                                                        data-confirm-title={`Delete tax rate ${rate.confirm_name}?`}
                                                        data-confirm-description="This will permanently delete the tax rate."
                                                    >
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="py-4 px-3 text-center text-slate-500">
                                            No tax rates yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
