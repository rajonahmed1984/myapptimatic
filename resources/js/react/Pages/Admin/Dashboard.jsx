import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

const CHART_FRAME = {
    width: 1120,
    height: 300,
    padLeft: 54,
    padRight: 54,
    padTop: 20,
    padBottom: 34,
    rows: 4,
};

const CHART_SERIES = {
    new_orders: {
        label: 'New Orders',
        stroke: '#d1d5db',
        pointFill: '#f8fafc',
        pointStroke: '#94a3b8',
        fill: 'rgba(148, 163, 184, 0.15)',
        legend: 'bg-slate-300',
    },
    active_orders: {
        label: 'Activated Orders',
        stroke: '#2563eb',
        pointFill: '#bfdbfe',
        pointStroke: '#1d4ed8',
        fill: 'rgba(37, 99, 235, 0.12)',
        legend: 'bg-blue-400',
    },
    income: {
        label: 'Income',
        stroke: '#10b981',
        pointFill: '#86efac',
        pointStroke: '#059669',
        fill: 'rgba(16, 185, 129, 0.16)',
        legend: 'bg-emerald-400',
    },
};

const SPARK_COLORS = {
    emerald: { stroke: '#10b981', fill: 'rgba(16, 185, 129, 0.14)', tone: 'text-emerald-600' },
    amber: { stroke: '#f59e0b', fill: 'rgba(245, 158, 11, 0.14)', tone: 'text-amber-600' },
    sky: { stroke: '#0ea5e9', fill: 'rgba(14, 165, 233, 0.14)', tone: 'text-sky-600' },
    rose: { stroke: '#f43f5e', fill: 'rgba(244, 63, 94, 0.14)', tone: 'text-rose-600' },
    slate: { stroke: '#64748b', fill: 'rgba(100, 116, 139, 0.14)', tone: 'text-slate-700' },
};

function money(currency, amount) {
    const value = Number(amount || 0);

    return `${currency}${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function metricValue(value) {
    return Number(value || 0).toLocaleString();
}

function taskRoute(template, projectId, taskId) {
    if (!template) {
        return '#';
    }

    return template
        .replace('__PROJECT__', String(projectId || '0'))
        .replace('__TASK__', String(taskId || '0'));
}

function customerRoute(template, customerId) {
    if (!template || !customerId) {
        return '#';
    }

    return template.replace('__CUSTOMER__', String(customerId));
}

function asNumberList(values, expectedLength = null) {
    const list = Array.isArray(values) ? values.map((value) => Number(value || 0)) : [];
    if (expectedLength === null || expectedLength <= list.length) {
        return list;
    }

    return [...list, ...new Array(expectedLength - list.length).fill(0)];
}

function buildChartPoints(values, maxValue, width, height, padLeft, padRight, padTop, padBottom) {
    const list = asNumberList(values);
    if (list.length === 0) {
        return [];
    }

    const left = padLeft;
    const right = width - padRight;
    const top = padTop;
    const bottom = height - padBottom;
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

function normalizeSparkSeries(rawSeries, fallbackValue = 0) {
    const list = asNumberList(rawSeries).filter((value) => Number.isFinite(value));
    const value = Math.max(0, Number(fallbackValue || 0));

    if (list.length === 0) {
        if (value === 0) {
            return [0, 0, 0, 0, 0];
        }
        return [value * 0.2, value * 0.2, value * 0.3, value * 0.55, value];
    }

    const allSame = list.every((item) => Math.abs(item - list[0]) < 0.0001);
    if (allSame && value > 0) {
        return [value * 0.2, value * 0.2, value * 0.3, value * 0.55, value];
    }

    return list;
}

function sparkPoints(values, width = 130, height = 32, padding = 2) {
    const list = asNumberList(values);
    if (list.length === 0) {
        return [];
    }

    const max = Math.max(1, ...list);
    const min = Math.min(...list);
    const range = Math.max(1, max - min);
    const usableWidth = Math.max(1, width - padding * 2);
    const usableHeight = Math.max(1, height - padding * 2);

    if (list.length === 1) {
        return [{ x: width / 2, y: height / 2, value: list[0] }];
    }

    return list.map((value, index) => {
        const x = padding + (index / (list.length - 1)) * usableWidth;
        const normalized = (value - min) / range;
        const y = padding + (1 - normalized) * usableHeight;
        return { x, y, value };
    });
}

function sparkPath(points) {
    if (!Array.isArray(points) || points.length === 0) {
        return '';
    }

    return points.map((point, idx) => `${idx === 0 ? 'M' : 'L'}${point.x.toFixed(2)} ${point.y.toFixed(2)}`).join(' ');
}

function sparkArea(points, baseY) {
    if (!Array.isArray(points) || points.length === 0) {
        return '';
    }

    const line = sparkPath(points);
    const first = points[0];
    const last = points[points.length - 1];
    return `${line} L${last.x.toFixed(2)} ${baseY.toFixed(2)} L${first.x.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

export default function Dashboard({
    pageTitle = 'Admin Dashboard',
    customerCount = 0,
    subscriptionCount = 0,
    licenseCount = 0,
    pendingInvoiceCount = 0,
    businessPulse = {},
    projectMaintenance = {},
    hrStats = {},
    periodMetrics = {},
    periodSeries = {},
    billingAmounts = {},
    currency = 'BDT',
    automation = {},
    automationMetrics = [],
    systemOverview = {},
    clientActivity = {},
    showTasksWidget = false,
    taskSummary = null,
    openTasks = [],
    inProgressTasks = [],
    routes = {},
}) {
    const [period, setPeriod] = useState('month');
    const [seriesVisible, setSeriesVisible] = useState({
        new_orders: true,
        active_orders: true,
        income: true,
    });
    const [hoveredIndex, setHoveredIndex] = useState(null);

    const activeMetrics = periodMetrics?.[period] || { new_orders: 0, active_orders: 0, income: 0, hosting_income: 0 };
    const activeSeries = periodSeries?.[period] || { labels: [], new_orders: [], active_orders: [], income: [] };
    const recentClients = Array.isArray(clientActivity?.recentClients) ? clientActivity.recentClients : [];
    const summary = taskSummary || { total: 0, open: 0, in_progress: 0, completed: 0 };

    const chartModel = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const seriesLength = labels.length;
        const newOrders = asNumberList(activeSeries?.new_orders, seriesLength);
        const activeOrders = asNumberList(activeSeries?.active_orders, seriesLength);
        const income = asNumberList(activeSeries?.income, seriesLength);

        const leftMax = Math.max(1, ...newOrders, ...activeOrders);
        const rightMax = Math.max(1, ...income);
        const baseY = CHART_FRAME.height - CHART_FRAME.padBottom;

        const points = {
            new_orders: buildChartPoints(
                newOrders,
                leftMax,
                CHART_FRAME.width,
                CHART_FRAME.height,
                CHART_FRAME.padLeft,
                CHART_FRAME.padRight,
                CHART_FRAME.padTop,
                CHART_FRAME.padBottom
            ),
            active_orders: buildChartPoints(
                activeOrders,
                leftMax,
                CHART_FRAME.width,
                CHART_FRAME.height,
                CHART_FRAME.padLeft,
                CHART_FRAME.padRight,
                CHART_FRAME.padTop,
                CHART_FRAME.padBottom
            ),
            income: buildChartPoints(
                income,
                rightMax,
                CHART_FRAME.width,
                CHART_FRAME.height,
                CHART_FRAME.padLeft,
                CHART_FRAME.padRight,
                CHART_FRAME.padTop,
                CHART_FRAME.padBottom
            ),
        };

        return {
            labels,
            seriesLength,
            newOrders,
            activeOrders,
            income,
            leftMax,
            rightMax,
            leftTicks: yTicks(leftMax, CHART_FRAME.rows),
            rightTicks: yTicks(rightMax, CHART_FRAME.rows),
            xTickIndexes: xTickIndexes(seriesLength),
            points,
            baseY,
        };
    }, [activeSeries]);

    const automationCards = useMemo(() => {
        const provided = Array.isArray(automationMetrics) ? automationMetrics : [];
        if (provided.length > 0) {
            return provided.map((item, index) => ({
                key: `automation-${index}`,
                label: String(item?.label || 'Metric'),
                value: Number(item?.value || 0),
                color: String(item?.color || 'slate'),
                stroke: item?.stroke || null,
                series: normalizeSparkSeries(item?.series, item?.value),
            }));
        }

        return [
            { key: 'invoices_created', label: 'Invoices Created', value: Number(automation?.invoices_created || 0), color: 'emerald' },
            { key: 'overdue_suspensions', label: 'Overdue Suspensions', value: Number(automation?.overdue_suspensions || 0), color: 'amber' },
            { key: 'tickets_closed', label: 'Inactive Tickets Closed', value: Number(automation?.tickets_closed || 0), color: 'sky' },
            { key: 'overdue_reminders', label: 'Overdue Reminders', value: Number(automation?.overdue_reminders || 0), color: 'rose' },
        ].map((item) => ({
            ...item,
            series: normalizeSparkSeries([], item.value),
        }));
    }, [automation, automationMetrics]);

    const toggleSeries = (key) => {
        setSeriesVisible((previous) => ({
            ...previous,
            [key]: !previous[key],
        }));
    };

    const hasChartData = chartModel.seriesLength > 0;

    const hoverRegions = useMemo(() => {
        const points = Array.isArray(chartModel.points?.income) ? chartModel.points.income : [];
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

        const incomePoint = chartModel.points.income?.[hoveredIndex];
        if (!incomePoint) {
            return null;
        }

        const xPct = (incomePoint.x / CHART_FRAME.width) * 100;
        const yPct = (incomePoint.y / CHART_FRAME.height) * 100;

        return {
            label: chartModel.labels?.[hoveredIndex] || '--',
            newOrders: Number(chartModel.newOrders?.[hoveredIndex] || 0),
            activeOrders: Number(chartModel.activeOrders?.[hoveredIndex] || 0),
            income: Number(chartModel.income?.[hoveredIndex] || 0),
            xPct: Math.max(12, Math.min(88, xPct)),
            yPct: Math.max(8, Math.min(62, yPct - 6)),
            pointX: incomePoint.x,
        };
    }, [hoveredIndex, chartModel]);

    useEffect(() => {
        if (hoveredIndex !== null && hoveredIndex >= chartModel.seriesLength) {
            setHoveredIndex(null);
        }
    }, [hoveredIndex, chartModel.seriesLength]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Business Pulse</div>
                        <div className="mt-1 text-sm text-slate-500">Quick view of health, cashflow and operational pressure.</div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${businessPulse?.health_classes || 'bg-slate-100 text-slate-700'}`}>
                            {businessPulse?.health_label || 'Unknown'}
                        </span>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            Score {Number(businessPulse?.health_score || 0)}/100
                        </span>
                    </div>
                </div>

                <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <SmallMetric
                        label="Net (30d)"
                        value={money(currency, businessPulse?.net_30d)}
                        tone={Number(businessPulse?.net_30d || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'}
                        note={`Income trend: ${businessPulse?.income_growth_percent === null || businessPulse?.income_growth_percent === undefined
                            ? 'N/A'
                            : `${Number(businessPulse.income_growth_percent) >= 0 ? '+' : ''}${Number(businessPulse.income_growth_percent).toFixed(1)}%`} vs previous 30d`}
                    />
                    <SmallMetric
                        label="Receivable Pressure"
                        value={`${Number(businessPulse?.overdue_share_percent || 0).toFixed(1)}%`}
                        tone="text-amber-600"
                        note={`Overdue ${Number(businessPulse?.overdue_invoices || 0)} / Open ${Number(businessPulse?.unpaid_invoices || 0) + Number(businessPulse?.overdue_invoices || 0)}`}
                        href={routes?.invoices_overdue}
                        action="View overdue invoices"
                    />
                    <SmallMetric
                        label="Sales Pipeline"
                        value={metricValue(businessPulse?.pending_orders)}
                        note="Pending orders awaiting conversion."
                        href={routes?.orders_index}
                        action="Review orders"
                    />
                    <SmallMetric
                        label="Support Load"
                        value={metricValue(businessPulse?.support_load)}
                        note={`Open: ${metricValue(businessPulse?.open_tickets)}, Customer reply: ${metricValue(businessPulse?.customer_reply_tickets)}`}
                        href={routes?.support_tickets_index}
                        action="Open tickets"
                    />
                </div>
            </div>

            <div className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <MetricLink href={routes?.customers_index} label="Customers" value={metricValue(customerCount)} />
                <MetricLink href={routes?.subscriptions_index} label="Subscriptions" value={metricValue(subscriptionCount)} />
                <MetricLink href={routes?.licenses_index} label="Licenses" value={metricValue(licenseCount)} />
                <MetricLink href={routes?.invoices_unpaid} label="Unpaid invoices" value={metricValue(pendingInvoiceCount)} tone="text-blue-600" />
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={`${routes?.projects_index}?status=ongoing`} title="Ongoing projects" value={metricValue(projectMaintenance?.projects_active)} />
                <SmallLinkCard href={routes?.subscriptions_index} title="Blocked services" value={metricValue(projectMaintenance?.subscriptions_blocked)} tone="text-rose-600" />
                <SmallLinkCard href={routes?.project_maintenances_index} title="Renewals (30d)" value={metricValue(projectMaintenance?.renewals_30d)} tone="text-emerald-600" />
                <SmallLinkCard href={routes?.projects_index} title="Loss risk projects" value={metricValue(projectMaintenance?.projects_loss)} tone="text-rose-600" />
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={routes?.hr_employees_index} title="Active employees" value={metricValue(hrStats?.active_employees)} />
                <SmallLinkCard href={routes?.hr_timesheets_index} title="Work logs (7d)" value={metricValue(hrStats?.pending_timesheets)} tone="text-amber-600" />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Draft payroll periods" value={metricValue(hrStats?.draft_payroll_periods)} />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Payroll to pay" value={metricValue(hrStats?.payroll_items_to_pay)} tone="text-rose-600" />
            </div>

            <div className="mt-8 card p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">System Overview</div>
                        <div className="mt-1 text-sm text-slate-500">Orders and income snapshot</div>
                    </div>

                    <div className="inline-flex shrink-0 rounded-lg border border-slate-200 bg-slate-50 p-1 text-xs font-semibold">
                        {['today', 'month', 'year'].map((key) => (
                            <button
                                key={key}
                                type="button"
                                className={`rounded-md px-3 py-1 ${period === key ? 'bg-white text-slate-900 shadow' : 'text-slate-500'}`}
                                onClick={() => setPeriod(key)}
                            >
                                {key === 'today' ? 'Today' : key === 'month' ? 'Last 30 Days' : 'Last 1 Year'}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <CompactOverviewStat label="New Orders" value={metricValue(activeMetrics?.new_orders)} />
                    <CompactOverviewStat label="Activated Orders" value={metricValue(activeMetrics?.active_orders)} tone="text-blue-600" />
                    <CompactOverviewStat label="Total Income" value={money(currency, activeMetrics?.income)} tone="text-emerald-600" />
                    <CompactOverviewStat label="Hosting Income" value={money(currency, activeMetrics?.hosting_income)} tone="text-emerald-600" />
                    <CompactOverviewStat
                        label="Avg Per Order"
                        value={money(currency, Number(activeMetrics?.new_orders || 0) > 0 ? Number(activeMetrics?.income || 0) / Number(activeMetrics?.new_orders || 1) : 0)}
                        tone="text-emerald-600"
                    />
                </div>

                <div className="mt-6 rounded-2xl border border-slate-200 bg-slate-50/60 p-3">
                    <div className="mb-3 flex flex-wrap items-center justify-start gap-2 sm:justify-center">
                        {Object.entries(CHART_SERIES).map(([key, config]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => toggleSeries(key)}
                                className={`inline-flex items-center gap-2 rounded-lg border px-3 py-1 text-xs font-semibold transition ${
                                    seriesVisible[key] ? 'border-slate-300 bg-white text-slate-700' : 'border-slate-200 bg-slate-100 text-slate-400'
                                }`}
                            >
                                <span className={`h-2.5 w-4 rounded ${seriesVisible[key] ? config.legend : 'bg-slate-200'}`} />
                                {config.label}
                            </button>
                        ))}
                    </div>

                    {!hasChartData ? (
                        <div className="flex h-[250px] items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white text-sm text-slate-500">
                            No chart data available for this period.
                        </div>
                    ) : (
                        <div className="relative rounded-xl border border-slate-200 bg-white">
                            <svg
                                viewBox={`0 0 ${CHART_FRAME.width} ${CHART_FRAME.height}`}
                                className="h-[260px] w-full sm:h-[300px]"
                                onMouseLeave={() => setHoveredIndex(null)}
                            >
                                <defs>
                                    <linearGradient id="incomeFillGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                        <stop offset="0%" stopColor={CHART_SERIES.income.fill} />
                                        <stop offset="100%" stopColor="rgba(16, 185, 129, 0.02)" />
                                    </linearGradient>
                                </defs>

                                {Array.from({ length: CHART_FRAME.rows + 1 }, (_, row) => {
                                    const ratio = row / CHART_FRAME.rows;
                                    const y = CHART_FRAME.padTop + ratio * (CHART_FRAME.height - CHART_FRAME.padTop - CHART_FRAME.padBottom);
                                    return (
                                        <line
                                            key={`grid-${row}`}
                                            x1={CHART_FRAME.padLeft}
                                            y1={y}
                                            x2={CHART_FRAME.width - CHART_FRAME.padRight}
                                            y2={y}
                                            stroke="#e2e8f0"
                                            strokeWidth="1"
                                        />
                                    );
                                })}

                                {chartModel.xTickIndexes.map((index) => {
                                    const point = chartModel.points.income[index];
                                    if (!point) {
                                        return null;
                                    }
                                    return (
                                        <line
                                            key={`v-${index}`}
                                            x1={point.x}
                                            y1={CHART_FRAME.padTop}
                                            x2={point.x}
                                            y2={chartModel.baseY}
                                            stroke="#f1f5f9"
                                            strokeDasharray="4 4"
                                        />
                                    );
                                })}

                                {chartModel.leftTicks.map((value, index) => {
                                    const ratio = index / CHART_FRAME.rows;
                                    const y = CHART_FRAME.padTop + ratio * (CHART_FRAME.height - CHART_FRAME.padTop - CHART_FRAME.padBottom);
                                    return (
                                        <text key={`left-tick-${index}`} x={CHART_FRAME.padLeft - 10} y={y + 4} textAnchor="end" fontSize="11" fill="#64748b">
                                            {Math.round(value)}
                                        </text>
                                    );
                                })}

                                {chartModel.rightTicks.map((value, index) => {
                                    const ratio = index / CHART_FRAME.rows;
                                    const y = CHART_FRAME.padTop + ratio * (CHART_FRAME.height - CHART_FRAME.padTop - CHART_FRAME.padBottom);
                                    return (
                                        <text key={`right-tick-${index}`} x={CHART_FRAME.width - CHART_FRAME.padRight + 10} y={y + 4} textAnchor="start" fontSize="11" fill="#64748b">
                                            {Math.round(value)}
                                        </text>
                                    );
                                })}

                                <text x={CHART_FRAME.padLeft - 30} y={CHART_FRAME.padTop - 4} textAnchor="start" fontSize="12" fill="#334155">
                                    Orders
                                </text>
                                <text x={CHART_FRAME.width - CHART_FRAME.padRight + 8} y={CHART_FRAME.padTop - 4} textAnchor="start" fontSize="12" fill="#334155">
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

                                {seriesVisible.income ? (
                                    <>
                                        <path d={pointsAreaPath(chartModel.points.income, chartModel.baseY)} fill="url(#incomeFillGradient)" stroke="none" />
                                        <path d={pointsPath(chartModel.points.income)} fill="none" stroke={CHART_SERIES.income.stroke} strokeWidth="2.5" />
                                        {chartModel.points.income.map((point, idx) => (
                                            <circle key={`income-dot-${idx}`} cx={point.x} cy={point.y} r="3" fill={CHART_SERIES.income.pointFill} stroke={CHART_SERIES.income.pointStroke} strokeWidth="1.2">
                                                <title>{`${chartModel.labels[idx]} | Income: ${money(currency, point.value)}`}</title>
                                            </circle>
                                        ))}
                                    </>
                                ) : null}

                                {seriesVisible.new_orders ? (
                                    <>
                                        <path d={pointsPath(chartModel.points.new_orders)} fill="none" stroke={CHART_SERIES.new_orders.stroke} strokeWidth="2" />
                                        {chartModel.points.new_orders.map((point, idx) => (
                                            <circle key={`new-dot-${idx}`} cx={point.x} cy={point.y} r="2.5" fill={CHART_SERIES.new_orders.pointFill} stroke={CHART_SERIES.new_orders.pointStroke} strokeWidth="1">
                                                <title>{`${chartModel.labels[idx]} | New Orders: ${point.value}`}</title>
                                            </circle>
                                        ))}
                                    </>
                                ) : null}

                                {seriesVisible.active_orders ? (
                                    <>
                                        <path d={pointsPath(chartModel.points.active_orders)} fill="none" stroke={CHART_SERIES.active_orders.stroke} strokeWidth="2" />
                                        {chartModel.points.active_orders.map((point, idx) => (
                                            <circle key={`active-dot-${idx}`} cx={point.x} cy={point.y} r="2.5" fill={CHART_SERIES.active_orders.pointFill} stroke={CHART_SERIES.active_orders.pointStroke} strokeWidth="1">
                                                <title>{`${chartModel.labels[idx]} | Active Orders: ${point.value}`}</title>
                                            </circle>
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
                                    const point = chartModel.points.income[index];
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
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-sm bg-emerald-400" />
                                        <span>Income: {money(currency, hoverDetails.income)}</span>
                                    </div>
                                    <div className="mt-1 text-[11px] text-slate-300">
                                        New: {metricValue(hoverDetails.newOrders)} | Active: {metricValue(hoverDetails.activeOrders)}
                                    </div>
                                </div>
                            ) : null}
                        </div>
                    )}
                </div>

            </div>

            <div className="mt-6 card p-6">
                <div className="flex items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Client Activity</div>
                        <div className="mt-1 text-sm text-slate-500">Last 30 clients login (all time)</div>
                    </div>
                    <a href={routes?.customers_index} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View customers</a>
                </div>

                <div className="mt-4 max-h-[230px] overflow-auto rounded-xl border border-slate-200">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="sticky top-0 bg-white py-2 pr-4">User</th>
                                <th className="sticky top-0 bg-white py-2 pr-4">Last login</th>
                                <th className="sticky top-0 bg-white py-2 pr-4">IP</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {recentClients.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="py-3 text-slate-500">No login sessions to show.</td>
                                </tr>
                            ) : recentClients.map((session, index) => {
                                const customerUrl = customerRoute(routes?.customers_show_template, session?.customer_id);
                                const hasCustomer = session?.customer_id && customerUrl !== '#';

                                return (
                                    <tr key={`${session?.user_id || 'user'}-${index}`}>
                                        <td className="py-2 pr-4">
                                            {hasCustomer ? (
                                                <a href={customerUrl} data-native="true" className="hover:text-teal-600">
                                                    {session?.name || '--'}
                                                </a>
                                            ) : (session?.name || '--')}
                                        </td>
                                        <td className="py-2 pr-4">{session?.last_login || '--'}</td>
                                        <td className="py-2 pr-4">{session?.ip || '--'}</td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Billing Status</div>
                            <div className="mt-1 text-sm text-slate-500">Revenue snapshots (including hosting income)</div>
                        </div>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Live</span>
                    </div>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <SmallMetricTile label="Today" value={money(currency, billingAmounts?.today)} tone="text-emerald-600" />
                        <SmallMetricTile label="This Month" value={money(currency, billingAmounts?.month)} tone="text-amber-500" />
                        <SmallMetricTile label="This Year" value={money(currency, billingAmounts?.year)} tone="text-rose-500" />
                        <SmallMetricTile label="All Time" value={money(currency, billingAmounts?.all_time)} />
                    </div>
                </div>

                <div className="card p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Automation Overview</div>
                            <div className="mt-1 text-sm text-slate-500">Last automation run: {systemOverview?.automation_last_run || '--'}</div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            <a href={routes?.automation_status} data-native="true" className="inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">
                                View automation status
                            </a>
                            <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                {systemOverview?.automation_cards?.status_badge || '--'}
                            </span>
                        </div>
                    </div>

                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        {automationCards.map((card) => (
                            <AutomationMetricCard
                                key={card.key}
                                label={card.label}
                                value={card.value}
                                color={card.color}
                                stroke={card.stroke}
                                series={card.series}
                            />
                        ))}
                    </div>
                </div>
            </div>

            {showTasksWidget ? (
                <div className="mt-8 card p-6">
                    <div className="section-label">Task Snapshot</div>
                    <div className="mt-3 grid gap-3 md:grid-cols-4">
                        <SmallMetricTile label="Total" value={metricValue(summary?.total)} />
                        <SmallMetricTile label="Open" value={metricValue(summary?.open)} />
                        <SmallMetricTile label="In progress" value={metricValue(summary?.in_progress)} />
                        <SmallMetricTile label="Completed" value={metricValue(summary?.completed)} />
                    </div>

                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <TaskList title="Open tasks" tasks={openTasks} routes={routes} />
                        <TaskList title="In progress tasks" tasks={inProgressTasks} routes={routes} />
                    </div>
                </div>
            ) : null}
        </>
    );
}

function MetricLink({ href, label, value, tone = 'text-slate-900' }) {
    return (
        <a href={href} data-native="true" className="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div className="section-label">{label}</div>
                <div className={`text-xl font-semibold ${tone}`}>{value}</div>
            </div>
        </a>
    );
}

function SmallMetric({ label, value, note, tone = 'text-slate-900', href = null, action = null }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</div>
            <div className="mt-1 text-xs text-slate-500">{note}</div>
            {href && action ? <a href={href} data-native="true" className="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">{action}</a> : null}
        </div>
    );
}

function SmallLinkCard({ href, title, value, tone = 'text-slate-900' }) {
    return (
        <a href={href} data-native="true" className="card px-4 py-3 transition hover:border-teal-300 hover:shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0 truncate text-sm font-medium text-slate-600" title={title}>{title}</div>
                <div className={`shrink-0 text-lg font-semibold ${tone}`}>{value}</div>
            </div>
        </a>
    );
}

function CompactOverviewStat({ label, value, tone = 'text-slate-900' }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-2.5 py-2">
            <div className="text-[9px] uppercase tracking-[0.16em] text-slate-400">{label}</div>
            <div className={`mt-1 text-xs font-semibold leading-tight sm:text-sm ${tone} break-words`}>{value}</div>
        </div>
    );
}

function SmallMetricTile({ label, value, tone = 'text-slate-900' }) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{label}</div>
            <div className={`mt-2 text-xl font-semibold ${tone}`}>{value}</div>
        </div>
    );
}

function AutomationMetricCard({ label, value, color = 'slate', stroke = null, series = [] }) {
    const palette = SPARK_COLORS[color] || SPARK_COLORS.slate;
    const sparkStroke = stroke || palette.stroke;
    const points = sparkPoints(series, 220, 32, 2);
    const path = sparkPath(points);
    const area = sparkArea(points, 31);
    const lastPoint = points.length > 0 ? points[points.length - 1] : null;

    return (
        <div className="rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-sm">
            <div className="flex items-center justify-between gap-3">
                <div className="text-[11px] uppercase tracking-[0.18em] text-slate-500">{label}</div>
                <div className={`text-lg font-semibold ${palette.tone}`}>{metricValue(value)}</div>
            </div>
            <div className="mt-1.5 w-full">
                <svg viewBox="0 0 220 32" className="block h-6 w-full">
                    {area ? <path d={area} fill={palette.fill} stroke="none" /> : null}
                    {path ? <path d={path} fill="none" stroke={sparkStroke} strokeWidth="1.9" strokeLinecap="round" strokeLinejoin="round" /> : null}
                    {lastPoint ? <circle cx={lastPoint.x} cy={lastPoint.y} r="1.9" fill={sparkStroke} /> : null}
                </svg>
            </div>
        </div>
    );
}

function TaskList({ title, tasks, routes }) {
    const rows = Array.isArray(tasks) ? tasks : [];

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{title}</div>
            <div className="mt-3 space-y-2">
                {rows.length === 0 ? (
                    <div className="text-xs text-slate-500">No tasks in this bucket.</div>
                ) : rows.map((task) => (
                    <a
                        key={task.id}
                        href={taskRoute(routes?.tasks_show_template, task.project_id, task.id)}
                        data-native="true"
                        className="block rounded-lg border border-slate-100 px-3 py-2 hover:border-teal-200"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-sm font-semibold text-slate-900">{task.title}</div>
                            <span className="text-[11px] text-slate-500">{task.status}</span>
                        </div>
                        <div className="mt-1 text-xs text-slate-500">
                            {task.project_name} | Subtasks: {metricValue(task.subtasks_count)} | Due: {task.due_date || '--'}
                        </div>
                    </a>
                ))}
            </div>
        </div>
    );
}
