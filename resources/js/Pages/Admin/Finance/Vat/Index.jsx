import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';

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
        label: 'Total VAT',
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

const changeBadgeClass = (direction) => {
    if (direction === 'up') return 'bg-emerald-50 text-emerald-700';
    if (direction === 'down') return 'bg-rose-50 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

const formatRate = (value) => {
    const number = safeNum(value);
    return Number.isInteger(number) ? String(number) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
};

const inputClass = 'mt-1 w-full rounded-full border border-slate-300 bg-white px-4 py-1.5 h-9 text-sm focus:outline-none focus:ring-1 focus:ring-teal-600';

function FieldLabel({ htmlFor, children, hint }) {
    return (
        <div>
            <label htmlFor={htmlFor} className="text-xs font-semibold text-slate-700">{children}</label>
            {hint ? <div className="text-xs text-slate-500">{hint}</div> : null}
        </div>
    );
}

export default function Index({
    pageTitle = 'VAT Settings',
    routes = {},
    settings_form = {},
    rate_form = {},
    quick_reference = {},
    rate_options = [],
    rates = [],
    currency_code = 'BDT',
    vat_analytics = {},
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const summary = vat_analytics?.summary || {};
    const monthRows = vat_analytics?.monthly_rows || [];
    const yearRows = vat_analytics?.yearly_rows || [];
    const trendLabels = vat_analytics?.trend?.labels || [];
    const trendSeries = asNumberList(vat_analytics?.trend?.series || []);
    const [hoveredIndex, setHoveredIndex] = React.useState(null);
    const [mode, setMode] = React.useState(String(settings_form?.tax_mode_default || 'exclusive'));
    const [noteTemplate, setNoteTemplate] = React.useState(settings_form?.invoice_tax_note_template || '');
    const effectiveYearFormRef = React.useRef(null);
    const effectiveYearOptions = React.useMemo(() => (vat_analytics?.effective_year_options || []).map((year) => ({
        value: String(year.value),
        label: year.label,
    })), [vat_analytics?.effective_year_options]);
    const defaultTaxRateOptions = React.useMemo(() => [
        { value: '', label: 'Automatic (newest active rate)' },
        ...rate_options.map((rate) => ({ value: String(rate.id), label: rate.label })),
    ], [rate_options]);

    const isEnabled = Boolean(quick_reference?.enabled);
    const currentRate = quick_reference?.current_rate_percent;
    const hasCurrentRate = currentRate !== null && currentRate !== undefined;
    // Worked examples use today's rate, or the standard 15% when none is set.
    const exampleRate = hasCurrentRate ? safeNum(currentRate) : 15;
    const examplePrice = 1000;
    const exclusiveVat = examplePrice * (exampleRate / 100);
    const inclusiveVat = examplePrice * (exampleRate / (100 + exampleRate));
    const notePreview = noteTemplate.trim() === '' ? '' : noteTemplate.split('{rate}').join(formatRate(exampleRate));

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

    const hasChartData = chartModel.totalSeries.some((value) => value > 0);

    return (
        <>
            <Head title={pageTitle} />

            {/* Current status: what happens to a new invoice right now */}
            <div className="card p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="text-lg font-semibold text-slate-900">VAT</div>
                            <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${isEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-slate-50 text-slate-600'}`}>
                                {isEnabled ? 'On' : 'Off'}
                            </span>
                        </div>
                        <div className="mt-1 text-sm text-slate-600">
                            {!isEnabled ? (
                                'New invoices are created without VAT. Turn it on under "VAT settings" below.'
                            ) : hasCurrentRate ? (
                                <>
                                    New invoices get <span className="font-semibold text-slate-900">{formatRate(currentRate)}% VAT</span>,{' '}
                                    {quick_reference?.mode_key === 'inclusive' ? 'already included in the price.' : 'added on top of the price.'}
                                </>
                            ) : (
                                <span className="text-amber-700">VAT is on, but no active rate covers today, so new invoices get no VAT. Add a rate below.</span>
                            )}
                        </div>
                        <div className="mt-1 text-xs text-slate-500">Bangladesh fiscal year: 1 July – 30 June.</div>
                    </div>
                    {routes?.reports ? (
                        <a
                            href={routes.reports}
                            data-native="true"
                            className="border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                        >
                            View Reports
                        </a>
                    ) : null}
                </div>
            </div>

            {/* VAT collected */}
            <div className="card mt-6 p-6">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <div className="text-sm font-semibold text-slate-800">VAT on invoices</div>
                        <div className="text-xs text-slate-500">
                            Counted by invoice issue date. Cancelled invoices are left out.
                        </div>
                    </div>
                    <form ref={effectiveYearFormRef} method="GET" action={routes?.index} data-native="true" className="w-full sm:w-auto sm:min-w-[300px]">
                        <label className="text-xs font-semibold text-slate-700">Fiscal year (July – June)</label>
                        <SearchableSelect
                            name="effective_year"
                            defaultValue={String(vat_analytics?.effective_year || '')}
                            onChange={() => effectiveYearFormRef.current?.submit()}
                            options={effectiveYearOptions}
                            className="mt-1"
                            placeholder="Select fiscal year"
                        />
                    </form>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold text-slate-500">This month</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{money(currency_code, summary.this_month_total)}</div>
                        <div className="mt-1 text-xs text-slate-500">{vat_analytics?.current_month_label || '--'}</div>
                    </div>
                    <div className="rounded-2xl border border-teal-200 bg-teal-50/40 p-4">
                        <div className="text-xs font-semibold text-slate-500">{vat_analytics?.fiscal_year_label || 'Fiscal year'}</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{money(currency_code, summary.this_year_total)}</div>
                        <div className="mt-1 text-xs text-slate-500">{vat_analytics?.fiscal_year_range || '--'}</div>
                    </div>
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold text-slate-500">All time</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{money(currency_code, summary.all_time_total)}</div>
                        <div className="mt-1 text-xs text-slate-500">Every fiscal year</div>
                    </div>
                </div>

                <div className="mt-5 rounded-2xl border border-slate-200 bg-white/90 p-4">
                    <div className="mb-3 text-xs font-semibold text-slate-600">
                        Monthly VAT, {vat_analytics?.fiscal_year_label || '--'} ({vat_analytics?.fiscal_year_range || '--'})
                    </div>
                    {!hasChartData ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            No VAT was charged in this fiscal year.
                        </div>
                    ) : (
                        <div className="relative w-full overflow-x-auto">
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
                                        onMouseLeave={() => setHoveredIndex(null)}
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
                                            y={TAX_CHART_FRAME.height - 12}
                                            textAnchor="middle"
                                            fontSize="12"
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
                                    <div className="mt-1">VAT: {money(currency_code, hoverDetails.total)}</div>
                                </div>
                            ) : null}
                        </div>
                    )}
                </div>

                <div className="mt-5 grid gap-5 xl:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold text-slate-600">Month by month ({vat_analytics?.fiscal_year_label || '--'})</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs text-slate-500">
                                        <th className="whitespace-nowrap px-2 py-2 text-left font-semibold">Month</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">VAT</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Invoices</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">vs previous month</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {monthRows.length ? (
                                        monthRows.map((row) => (
                                            <tr key={row.month_key} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-2">{row.month_label}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right font-semibold text-slate-900">
                                                    {money(currency_code, row.vat_total)}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">{row.invoice_count}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${changeBadgeClass(row.change_direction)}`}>
                                                        {percentage(row.change_percent)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-2 py-4 text-center text-slate-500">
                                                No VAT in this fiscal year.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white/85 p-4">
                        <div className="text-xs font-semibold text-slate-600">Fiscal year by fiscal year</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs text-slate-500">
                                        <th className="whitespace-nowrap px-2 py-2 text-left font-semibold">Fiscal year</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">VAT</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">Invoices</th>
                                        <th className="whitespace-nowrap px-2 py-2 text-right font-semibold">vs previous year</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {yearRows.length ? (
                                        yearRows.map((row) => (
                                            <tr key={row.year} className="border-t border-slate-100">
                                                <td className="whitespace-nowrap px-2 py-2">
                                                    <div>{row.label}</div>
                                                    <div className="text-xs text-slate-500">{row.range}</div>
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right font-semibold text-slate-900">
                                                    {money(currency_code, row.vat_total)}
                                                </td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">{row.invoice_count}</td>
                                                <td className="whitespace-nowrap px-2 py-2 text-right">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${changeBadgeClass(row.change_direction)}`}>
                                                        {percentage(row.change_percent)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={4} className="px-2 py-4 text-center text-slate-500">
                                                No VAT charged yet.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                {/* Settings */}
                <div className="card p-6">
                    <div className="section-label">VAT settings</div>
                    <div className="mt-1 text-xs text-slate-500">Applies to new invoices. Invoices already issued keep their VAT.</div>

                    <form method="POST" action={routes?.settings_update} className="mt-4 grid gap-5 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="PUT" />

                        <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <input type="hidden" name="enabled" value="0" />
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600"
                                defaultChecked={Boolean(settings_form?.enabled)}
                            />
                            <span>
                                <span className="block text-sm font-semibold text-slate-900">Charge VAT on new invoices</span>
                                <span className="block text-xs text-slate-500">When off, invoices are created without VAT.</span>
                            </span>
                        </label>

                        <div>
                            <FieldLabel>How VAT is applied</FieldLabel>
                            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                {[
                                    {
                                        value: 'exclusive',
                                        title: 'Added on top of the price',
                                        lines: [
                                            `Price ${money(currency_code, examplePrice)}`,
                                            `+ VAT ${formatRate(exampleRate)}% ${money(currency_code, exclusiveVat)}`,
                                            `Customer pays ${money(currency_code, examplePrice + exclusiveVat)}`,
                                        ],
                                    },
                                    {
                                        value: 'inclusive',
                                        title: 'Already included in the price',
                                        lines: [
                                            `Customer pays ${money(currency_code, examplePrice)}`,
                                            `of which VAT ${formatRate(exampleRate)}% ${money(currency_code, inclusiveVat)}`,
                                            `Net price ${money(currency_code, examplePrice - inclusiveVat)}`,
                                        ],
                                    },
                                ].map((option) => (
                                    <label
                                        key={option.value}
                                        className={`cursor-pointer rounded-2xl border p-3 transition ${mode === option.value ? 'border-teal-400 bg-teal-50/60 ring-1 ring-teal-400' : 'border-slate-200 bg-white/80 hover:border-slate-300'}`}
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="tax_mode_default"
                                                value={option.value}
                                                checked={mode === option.value}
                                                onChange={() => setMode(option.value)}
                                                className="h-4 w-4 text-teal-600"
                                            />
                                            <span className="text-sm font-semibold text-slate-900">{option.title}</span>
                                        </span>
                                        <span className="mt-2 block space-y-0.5 text-xs text-slate-600 tabular-nums">
                                            {option.lines.map((line) => <span key={line} className="block">{line}</span>)}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {errors.tax_mode_default ? <div className="mt-1 text-xs text-rose-600">{errors.tax_mode_default}</div> : null}
                        </div>

                        <div>
                            <FieldLabel hint="Automatic picks the newest active rate for each invoice's issue date.">Rate to use</FieldLabel>
                            <SearchableSelect
                                name="default_tax_rate_id"
                                defaultValue={String(settings_form?.default_tax_rate_id || '')}
                                options={defaultTaxRateOptions}
                                className="mt-1"
                                placeholder="Automatic (newest active rate)"
                                error={errors.default_tax_rate_id}
                            />
                        </div>

                        <div>
                            <FieldLabel htmlFor="vatNoteTemplate" hint={'Printed on invoices next to the VAT amount. {rate} becomes the percentage.'}>
                                Invoice VAT line
                            </FieldLabel>
                            <input
                                id="vatNoteTemplate"
                                name="invoice_tax_note_template"
                                value={noteTemplate}
                                onChange={(event) => setNoteTemplate(event.target.value)}
                                placeholder="VAT ({rate}%)"
                                className={inputClass}
                            />
                            {notePreview ? (
                                <div className="mt-1 text-xs text-slate-500">
                                    Shows as: <span className="font-semibold text-slate-800">{notePreview}</span>
                                </div>
                            ) : null}
                            {errors.invoice_tax_note_template ? <div className="mt-1 text-xs text-rose-600">{errors.invoice_tax_note_template}</div> : null}
                        </div>

                        <div className="flex justify-end">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Save settings
                            </button>
                        </div>
                    </form>
                </div>

                {/* Rates */}
                <div className="card p-6">
                    <div className="section-label">VAT rates</div>
                    <div className="mt-1 text-xs text-slate-500">
                        Each invoice uses the rate active on its issue date. When the rate changes by law, add a new rate with its start date
                        and set an end date on the old one, so past invoices stay correct.
                    </div>

                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white/80">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="text-xs text-slate-500">
                                    <th className="px-3 py-2 text-left font-semibold">Name</th>
                                    <th className="px-3 py-2 text-left font-semibold">Rate</th>
                                    <th className="px-3 py-2 text-left font-semibold">Valid</th>
                                    <th className="px-3 py-2 text-left font-semibold">Status</th>
                                    <th className="px-3 py-2 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.length > 0 ? (
                                    rates.map((rate) => (
                                        <tr key={rate.id} className="border-t border-slate-100">
                                            <td className="px-3 py-2 font-semibold text-slate-900">
                                                {rate.name}
                                                {String(rate.id) === String(quick_reference?.current_rate_id ?? '') ? (
                                                    <span className="ml-2 rounded-full bg-teal-50 px-2 py-0.5 text-[11px] font-semibold text-teal-700">In use today</span>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2">{rate.rate_percent_display}</td>
                                            <td className="whitespace-nowrap px-3 py-2">
                                                <div>From {rate.effective_from_display}</div>
                                                <div className="text-xs text-slate-500">To {rate.effective_to_display}</div>
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(rate.is_active)}`}>
                                                    {rate.status_label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-right">
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
                                                        data-confirm-title={`Delete VAT rate ${rate.confirm_name}?`}
                                                        data-confirm-description="This will permanently delete the VAT rate."
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
                                        <td colSpan={5} className="px-3 py-4 text-center text-slate-500">
                                            No VAT rates yet. Add one below.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action={routes?.rate_store} className="mt-5 rounded-2xl border border-dashed border-slate-300 p-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <div className="text-sm font-semibold text-slate-900">Add a rate</div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <FieldLabel htmlFor="vatRateName">Name</FieldLabel>
                                <input id="vatRateName" name="name" defaultValue={rate_form?.name || ''} placeholder="Standard VAT" className={inputClass} />
                                {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                            </div>
                            <div>
                                <FieldLabel htmlFor="vatRatePercent">Rate (%)</FieldLabel>
                                <input
                                    id="vatRatePercent"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    name="rate_percent"
                                    defaultValue={rate_form?.rate_percent || ''}
                                    placeholder="15"
                                    className={inputClass}
                                />
                                {errors.rate_percent ? <div className="mt-1 text-xs text-rose-600">{errors.rate_percent}</div> : null}
                            </div>
                            <div>
                                <FieldLabel htmlFor="vatRateFrom">Starts on</FieldLabel>
                                <input
                                    id="vatRateFrom"
                                    type="text"
                                    placeholder="DD-MM-YYYY"
                                    inputMode="numeric"
                                    name="effective_from"
                                    defaultValue={rate_form?.effective_from || ''}
                                    className={inputClass}
                                />
                                {errors.effective_from ? <div className="mt-1 text-xs text-rose-600">{errors.effective_from}</div> : null}
                            </div>
                            <div>
                                <FieldLabel htmlFor="vatRateTo">Ends on <span className="font-normal text-slate-500">(optional)</span></FieldLabel>
                                <input
                                    id="vatRateTo"
                                    type="text"
                                    placeholder="Leave blank for no end date"
                                    inputMode="numeric"
                                    name="effective_to"
                                    defaultValue={rate_form?.effective_to || ''}
                                    className={inputClass}
                                />
                                {errors.effective_to ? <div className="mt-1 text-xs text-rose-600">{errors.effective_to}</div> : null}
                            </div>
                        </div>
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <label className="flex items-center gap-2 text-xs text-slate-600">
                                <input type="hidden" name="is_active" value="0" />
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                    defaultChecked={Boolean(rate_form?.is_active)}
                                />
                                Active
                            </label>
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Add rate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
