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
    expense: {
        label: 'Expense',
        stroke: '#f97316',
        pointFill: '#fed7aa',
        pointStroke: '#ea580c',
        fill: 'rgba(249, 115, 22, 0.14)',
        legend: 'bg-orange-400',
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
    businessPulseAi = {},
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
    const businessPulseVerdict = businessPulseAi?.verdict || businessPulse?.health_label || 'Unknown';
    const businessPulseScore = Number(businessPulseAi?.score ?? businessPulse?.health_score ?? 0);
    const businessPulseIncomeScore = Number(businessPulse?.income_score ?? 0);
    const businessPulseExpenseScore = Number(businessPulse?.expense_score ?? 0);
    const businessPulseOperationsScore = Number(businessPulse?.operations_score ?? 0);
    const businessPulseMessage = businessPulseAi?.reason || 'AI-verified business pulse with supporting health, cashflow and operational pressure metrics.';
    const businessPulseAction = businessPulseAi?.action || (businessPulseAi?.error ? `AI insight unavailable: ${businessPulseAi.error}` : null);
    const hasAiVerification = Boolean(businessPulseAi?.verdict || businessPulseAi?.reason);
    const scoreBadgeClass = (score) => {
        if (score >= 80) {
            return 'bg-emerald-50 text-emerald-700';
        }
        if (score >= 65) {
            return 'bg-amber-50 text-amber-700';
        }
        return 'bg-rose-50 text-rose-700';
    };
    const aiVerdictClass = {
        Healthy: 'bg-emerald-100 text-emerald-700',
        Watch: 'bg-amber-100 text-amber-700',
        Critical: 'bg-rose-100 text-rose-700',
    }[businessPulseAi?.verdict] || (businessPulse?.health_classes || 'bg-slate-100 text-slate-700');

    const [period, setPeriod] = useState('month');
    const [seriesVisible, setSeriesVisible] = useState({
        new_orders: true,
        active_orders: true,
        income: true,
        expense: true,
    });
    const [hoveredIndex, setHoveredIndex] = useState(null);

    const activeMetrics = periodMetrics?.[period] || { new_orders: 0, active_orders: 0, income: 0, expense: 0, hosting_income: 0 };
    const activeSeries = periodSeries?.[period] || { labels: [], new_orders: [], active_orders: [], income: [], expense: [] };
    const recentClients = Array.isArray(clientActivity?.recentClients) ? clientActivity.recentClients : [];
    const summary = taskSummary || { total: 0, open: 0, in_progress: 0, completed: 0 };

    const chartModel = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const seriesLength = labels.length;
        const newOrders = asNumberList(activeSeries?.new_orders, seriesLength);
        const activeOrders = asNumberList(activeSeries?.active_orders, seriesLength);
        const income = asNumberList(activeSeries?.income, seriesLength);
        const expense = asNumberList(activeSeries?.expense, seriesLength);

        const leftMax = Math.max(1, ...newOrders, ...activeOrders);
        const rightMax = Math.max(1, ...income, ...expense);
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
            expense: buildChartPoints(
                expense,
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
            expense,
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
            expense: Number(chartModel.expense?.[hoveredIndex] || 0),
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
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="section-label">
                            Business Pulse                           
                            {hasAiVerification ? (
                                <span className="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-700 ml-2">
                                    AI Verified
                                </span>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center justify-start gap-2 md:justify-end">
                        {routes?.dashboard_refresh_ai ? (
                            <a href={routes.dashboard_refresh_ai} data-native="true" className="rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 hover:border-violet-300 hover:bg-violet-100">
                                Refresh AI
                            </a>
                        ) : null}
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${aiVerdictClass}`}>
                            {businessPulseVerdict}
                        </span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${scoreBadgeClass(businessPulseScore)}`}>
                            Score {businessPulseScore}/100
                        </span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${scoreBadgeClass(businessPulseIncomeScore)}`}>
                            Income {businessPulseIncomeScore}/100
                        </span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${scoreBadgeClass(businessPulseExpenseScore)}`}>
                            Expense {businessPulseExpenseScore}/100
                        </span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${scoreBadgeClass(businessPulseOperationsScore)}`}>
                            Ops {businessPulseOperationsScore}/100
                        </span>
                        {businessPulseAi?.confidence ? (
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                Confidence {businessPulseAi.confidence}
                            </span>
                        ) : null}
                    </div>
                </div>

                {businessPulseAction ? (
                    <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {businessPulseAction}
                    </div>
                ) : null}

                <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
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
                        label="Expense Burn"
                        value={money(currency, businessPulse?.expense_30d)}
                        tone={Number(businessPulse?.expense_ratio_percent || 0) > 85 ? 'text-rose-600' : 'text-amber-600'}
                        note={`Expense is ${Number(businessPulse?.expense_ratio_percent || 0).toFixed(1)}% of 30d income.`}
                        href={routes?.expenses_dashboard}
                        action="Review expense flow"
                    />
                    <SmallMetric
                        label="Payable Pressure"
                        value={money(currency, businessPulse?.payable_total)}
                        tone={Number(businessPulse?.payable_pressure_percent || 0) > 60 ? 'text-rose-600' : 'text-amber-600'}
                        note={`Next 30d due ${money(currency, businessPulse?.expense_due_30d)} | Payroll ${money(currency, businessPulse?.payroll_payable)} | Commission ${money(currency, businessPulse?.commission_payable)}`}
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
                <MetricLink href={routes?.customers_index} label="Customers" value={metricValue(customerCount)} className="border-slate-200 bg-slate-50 hover:border-slate-300" labelClassName="text-slate-500" />
                <MetricLink href={routes?.subscriptions_index} label="Subscriptions" value={metricValue(subscriptionCount)} tone="text-sky-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-sky-700" />
                <MetricLink href={routes?.licenses_index} label="Licenses" value={metricValue(licenseCount)} tone="text-teal-700" className="border-teal-200 bg-teal-50 hover:border-teal-300" labelClassName="text-teal-700" />
                <MetricLink href={routes?.invoices_unpaid} label="Unpaid invoices" value={metricValue(pendingInvoiceCount)} tone="text-blue-700" className="border-blue-200 bg-blue-50 hover:border-blue-300" labelClassName="text-blue-700" />
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={`${routes?.projects_all}?status=ongoing`} title="Ongoing projects" value={metricValue(projectMaintenance?.projects_active)} tone="text-sky-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-sky-700" />
                <SmallLinkCard href={routes?.subscriptions_index} title="Blocked services" value={metricValue(projectMaintenance?.subscriptions_blocked)} tone="text-rose-700" className="border-rose-200 bg-rose-50 hover:border-rose-300" labelClassName="text-rose-700" />
                <SmallLinkCard href={routes?.project_maintenances_index} title="Renewals (30d)" value={metricValue(projectMaintenance?.renewals_30d)} tone="text-emerald-700" className="border-emerald-200 bg-emerald-50 hover:border-emerald-300" labelClassName="text-emerald-700" />
                <SmallLinkCard href={routes?.projects_all} title="Loss risk projects" value={metricValue(projectMaintenance?.projects_loss)} tone="text-rose-700" className="border-rose-200 bg-rose-50 hover:border-rose-300" labelClassName="text-rose-700" />
            </div>

            <div className="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={routes?.hr_employees_index} title="Active employees" value={metricValue(hrStats?.active_employees)} tone="text-emerald-700" className="border-emerald-200 bg-emerald-50 hover:border-emerald-300" labelClassName="text-emerald-700" />
                <SmallLinkCard href={routes?.hr_timesheets_index} title="Work logs (7d)" value={metricValue(hrStats?.pending_timesheets)} tone="text-amber-700" className="border-amber-200 bg-amber-50 hover:border-amber-300" labelClassName="text-amber-700" />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Draft payroll periods" value={metricValue(hrStats?.draft_payroll_periods)} tone="text-slate-700" className="border-slate-200 bg-slate-50 hover:border-slate-300" labelClassName="text-slate-600" />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Payroll to pay" value={metricValue(hrStats?.payroll_items_to_pay)} tone="text-rose-700" className="border-rose-200 bg-rose-50 hover:border-rose-300" labelClassName="text-rose-700" />
            </div>

            <div className="mt-8 card p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">System Overview</div>
                        <div className="mt-1 text-sm text-slate-500">Orders, income and expense snapshot</div>
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

                <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                    <CompactOverviewStat label="New Orders" value={metricValue(activeMetrics?.new_orders)} />
                    <CompactOverviewStat label="Activated Orders" value={metricValue(activeMetrics?.active_orders)} tone="text-blue-600" />
                    <CompactOverviewStat label="Total Income" value={money(currency, activeMetrics?.income)} tone="text-emerald-600" />
                    <CompactOverviewStat label="Total Expense" value={money(currency, activeMetrics?.expense)} tone="text-orange-600" />
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
                                    Amount
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

                                {seriesVisible.expense ? (
                                    <>
                                        <path d={pointsPath(chartModel.points.expense)} fill="none" stroke={CHART_SERIES.expense.stroke} strokeWidth="2.2" />
                                        {chartModel.points.expense.map((point, idx) => (
                                            <circle key={`expense-dot-${idx}`} cx={point.x} cy={point.y} r="2.8" fill={CHART_SERIES.expense.pointFill} stroke={CHART_SERIES.expense.pointStroke} strokeWidth="1.1">
                                                <title>{`${chartModel.labels[idx]} | Expense: ${money(currency, point.value)}`}</title>
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
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="h-2 w-2 rounded-sm bg-orange-400" />
                                        <span>Expense: {money(currency, hoverDetails.expense)}</span>
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

            <div className="mt-8 card p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">Client Activity</div>
                        <div className="mt-1 text-sm text-slate-500">Last 30 clients login (all time)</div>
                    </div>
                    <a href={routes?.customers_index} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View customers</a>
                </div>

                <div className="mt-4 px-2.5 py-2 max-h-[230px] overflow-auto rounded-xl border border-slate-200">
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
                    <div className="flex flex-wrap items-start justify-between gap-3">
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
                    <div className="flex flex-wrap items-start justify-between gap-3">
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
                    <div>
                        <div className="section-label">Task Snapshot</div>
                        <div className="mt-1 text-sm text-slate-500">Open pipeline and current execution buckets at a glance.</div>
                    </div>
                    <div className="mt-3 grid gap-3 md:grid-cols-4">
                        <SmallMetricTile
                            label="Total"
                            value={metricValue(summary?.total)}
                            className="border-slate-200 bg-slate-50"
                            labelClassName="text-slate-500"
                            tone="text-slate-900"
                        />
                        <SmallMetricTile
                            label="Open"
                            value={metricValue(summary?.open)}
                            className="border-amber-200 bg-amber-50"
                            labelClassName="text-amber-700"
                            tone="text-amber-700"
                        />
                        <SmallMetricTile
                            label="In progress"
                            value={metricValue(summary?.in_progress)}
                            className="border-sky-200 bg-sky-50"
                            labelClassName="text-sky-700"
                            tone="text-sky-700"
                        />
                        <SmallMetricTile
                            label="Completed"
                            value={metricValue(summary?.completed)}
                            className="border-emerald-200 bg-emerald-50"
                            labelClassName="text-emerald-700"
                            tone="text-emerald-700"
                        />
                    </div>

                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <TaskList title="Open tasks" tasks={openTasks} routes={routes} variant="open" />
                        <TaskList title="In progress tasks" tasks={inProgressTasks} routes={routes} variant="in_progress" />
                    </div>
                </div>
            ) : null}
        </>
    );
}

Dashboard.title = 'Admin Dashboard';

function MetricLink({
    href,
    label,
    value,
    tone = 'text-slate-900',
    className = 'border-slate-200 bg-white hover:border-teal-300',
    labelClassName = 'text-slate-500',
}) {
    return (
        <a href={href} data-native="true" className={`card h-full px-4 py-3 leading-tight transition hover:shadow-sm ${className}`}>
            <div className="flex items-center justify-between gap-3">
                <div className={`section-label ${labelClassName}`}>{label}</div>
                <div className={`text-xl font-semibold ${tone}`}>{value}</div>
            </div>
        </a>
    );
}

function SmallMetric({ label, value, note, tone = 'text-slate-900', href = null, action = null }) {
    return (
        <div className="h-full rounded-2xl border border-slate-200 bg-white p-4">
            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{label}</div>
            <div className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</div>
            <div className="mt-1 text-xs text-slate-500">{note}</div>
            {href && action ? <a href={href} data-native="true" className="mt-2 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">{action}</a> : null}
        </div>
    );
}

function SmallLinkCard({
    href,
    title,
    value,
    tone = 'text-slate-900',
    className = 'border-slate-200 bg-white hover:border-teal-300',
    labelClassName = 'text-slate-600',
}) {
    return (
        <a href={href} data-native="true" className={`card h-full px-4 py-3 transition hover:shadow-sm ${className}`}>
            <div className="flex items-center justify-between gap-3">
                <div className={`min-w-0 truncate text-sm font-medium ${labelClassName}`} title={title}>{title}</div>
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

function SmallMetricTile({
    label,
    value,
    tone = 'text-slate-900',
    className = 'border-slate-100 bg-white',
    labelClassName = 'text-slate-500',
}) {
    return (
        <div className={`h-full rounded-2xl border p-4 shadow-sm ${className}`}>
            <div className={`text-xs uppercase tracking-[0.2em] ${labelClassName}`}>{label}</div>
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
        <div className="h-full rounded-xl border border-slate-100 bg-white px-3 py-2.5 shadow-sm">
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

function TaskList({ title, tasks, routes, variant = 'default' }) {
    const rows = Array.isArray(tasks) ? tasks : [];
    const styles = {
        open: {
            wrapper: 'border-amber-200 bg-amber-50/50',
            title: 'text-amber-700',
            row: 'border-amber-100 bg-white hover:border-amber-300',
            badge: 'bg-amber-100 text-amber-700',
        },
        in_progress: {
            wrapper: 'border-sky-200 bg-sky-50/50',
            title: 'text-sky-700',
            row: 'border-sky-100 bg-white hover:border-sky-300',
            badge: 'bg-sky-100 text-sky-700',
        },
        default: {
            wrapper: 'border-slate-200 bg-white',
            title: 'text-slate-400',
            row: 'border-slate-100 bg-white hover:border-teal-200',
            badge: 'bg-slate-100 text-slate-500',
        },
    }[variant] || {
        wrapper: 'border-slate-200 bg-white',
        title: 'text-slate-400',
        row: 'border-slate-100 bg-white hover:border-teal-200',
        badge: 'bg-slate-100 text-slate-500',
    };

    return (
        <div className={`rounded-2xl border p-4 ${styles.wrapper}`}>
            <div className={`text-xs uppercase tracking-[0.2em] ${styles.title}`}>{title}</div>
            <div className="mt-3 space-y-2">
                {rows.length === 0 ? (
                    <div className="text-xs text-slate-500">No tasks in this bucket.</div>
                ) : rows.map((task) => (
                    <a
                        key={task.id}
                        href={taskRoute(routes?.tasks_show_template, task.project_id, task.id)}
                        data-native="true"
                        className={`block rounded-lg border px-3 py-2 transition ${styles.row}`}
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div className="text-sm font-semibold text-slate-900">{task.title}</div>
                            <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${styles.badge}`}>{task.status}</span>
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
