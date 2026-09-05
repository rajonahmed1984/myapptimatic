import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { ResponsiveContainer, ComposedChart, Area, Line, XAxis, YAxis, CartesianGrid, Tooltip as RechartsTooltip } from 'recharts';

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

const TASK_STATUS_LABELS = {
    pending: 'Pending',
    todo: 'To Do',
    blocked: 'Blocked',
    in_progress: 'In Progress',
    completed: 'Completed',
    done: 'Done',
};

function taskStatusLabel(status) {
    if (!status) {
        return '--';
    }

    if (TASK_STATUS_LABELS[status]) {
        return TASK_STATUS_LABELS[status];
    }

    return String(status)
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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
    openTasks = [],
    inProgressTasks = [],
    routes = {},
}) {
    const businessPulseVerdict = businessPulseAi?.verdict || businessPulse?.health_label || 'Unknown';
    const businessPulseScore = Number(businessPulseAi?.score ?? businessPulse?.health_score ?? 0);
    const businessPulseIncomeScore = Number(businessPulse?.income_score ?? 0);
    const businessPulseExpenseScore = Number(businessPulse?.expense_score ?? 0);
    const businessPulseOperationsScore = Number(businessPulse?.operations_score ?? 0);
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
    const scoreToneClass = (score) => {
        if (score >= 80) {
            return 'text-emerald-600';
        }
        if (score >= 65) {
            return 'text-amber-600';
        }
        return 'text-rose-600';
    };
    const aiVerdictClass = {
        Healthy: 'bg-emerald-100 text-emerald-700',
        Watch: 'bg-amber-100 text-amber-700',
        Critical: 'bg-rose-100 text-rose-700',
    }[businessPulseAi?.verdict] || (businessPulse?.health_classes || 'bg-slate-100 text-slate-700');

    const [period, setPeriod] = useState('month');
    const [isPulseExpanded, setIsPulseExpanded] = useState(false);
    const [seriesVisible, setSeriesVisible] = useState({
        new_orders: true,
        active_orders: true,
        income: true,
        expense: true,
    });
    const activeMetrics = periodMetrics?.[period] || { new_orders: 0, active_orders: 0, income: 0, expense: 0, hosting_income: 0 };
    const activeSeries = periodSeries?.[period] || { labels: [], new_orders: [], active_orders: [], income: [], expense: [] };
    const recentClients = Array.isArray(clientActivity?.recentClients) ? clientActivity.recentClients : [];

    // Recharts wants one row per point rather than a parallel-array-per-series
    // shape, so the period series are pivoted into { label, new_orders, ... }.
    const chartData = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const seriesLength = labels.length;
        const newOrders = asNumberList(activeSeries?.new_orders, seriesLength);
        const activeOrders = asNumberList(activeSeries?.active_orders, seriesLength);
        const income = asNumberList(activeSeries?.income, seriesLength);
        const expense = asNumberList(activeSeries?.expense, seriesLength);

        return labels.map((label, index) => ({
            label,
            new_orders: newOrders[index] ?? 0,
            active_orders: activeOrders[index] ?? 0,
            income: income[index] ?? 0,
            expense: expense[index] ?? 0,
        }));
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

    const hasChartData = chartData.length > 0;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-4 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="section-label">
                            Business Pulse
                            {hasAiVerification ? (
                                <span className="ml-2 rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-700">
                                    AI Verified
                                </span>
                            ) : null}
                        </div>
                        <div className="mt-1 text-xs text-slate-500 sm:text-sm">Accounting health summary for last 30 days.</div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {routes?.dashboard_refresh_ai ? (
                            <a href={routes.dashboard_refresh_ai} data-native="true" className="rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 hover:border-violet-300 hover:bg-violet-100">
                                Refresh AI
                            </a>
                        ) : null}
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${aiVerdictClass}`}>
                            Health: {businessPulseVerdict}
                        </span>
                        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${scoreBadgeClass(businessPulseScore)}`}>
                            Overall: {businessPulseScore}/100
                        </span>
                        <button
                            type="button"
                            onClick={() => setIsPulseExpanded(!isPulseExpanded)}
                            className="md:hidden inline-flex items-center gap-1 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1 rounded-full cursor-pointer transition-colors"
                            aria-expanded={isPulseExpanded}
                        >
                            <span>{isPulseExpanded ? 'Hide' : 'Details'}</span>
                            <svg className={`w-3.5 h-3.5 transform transition-transform duration-200 ${isPulseExpanded ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div className={`${isPulseExpanded ? 'block' : 'hidden md:block'} transition-all duration-200`}>
                    <div className="mt-4 grid gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <SmallMetric
                            label="Net Cash Position (30d)"
                            value={money(currency, businessPulse?.net_30d)}
                            tone={Number(businessPulse?.net_30d || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'}
                            note={`Income: ${money(currency, businessPulse?.income_30d)} | Expense: ${money(currency, businessPulse?.expense_30d)}`}
                        />
                        <SmallMetric
                            label="Expense-to-Income Ratio"
                            value={`${Number(businessPulse?.expense_ratio_percent || 0).toFixed(1)}%`}
                            tone={Number(businessPulse?.expense_ratio_percent || 0) > 85 ? 'text-rose-600' : 'text-amber-600'}
                            note="Operating expense ratio for last 30 days."
                            href={routes?.expenses_dashboard}
                            action="Open Accounting Expense Dashboard"
                        />
                        <SmallMetric
                            label="A/R Overdue Ratio"
                            value={`${Number(businessPulse?.overdue_share_percent || 0).toFixed(1)}%`}
                            tone="text-amber-600"
                            note={`Overdue invoices: ${metricValue(businessPulse?.overdue_invoices)} | Open receivables: ${metricValue(Number(businessPulse?.unpaid_invoices || 0) + Number(businessPulse?.overdue_invoices || 0))}`}
                            href={routes?.invoices_overdue}
                            action="Review A/R Aging"
                        />
                        <SmallMetric
                            label="A/P Exposure"
                            value={money(currency, businessPulse?.payable_total)}
                            tone={Number(businessPulse?.payable_pressure_percent || 0) > 60 ? 'text-rose-600' : 'text-amber-600'}
                            note={`Due in next 30 days: ${money(currency, businessPulse?.expense_due_30d)}`}
                        />
                    </div>

                    <div className="mt-3 sm:mt-4 grid gap-2.5 sm:gap-3 grid-cols-3">
                        <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3 text-center sm:text-left">
                            <div className="text-[10px] sm:text-[11px] uppercase tracking-[0.16em] text-slate-500 truncate">Income</div>
                            <div className={`mt-0.5 sm:mt-1 text-sm sm:text-base font-semibold ${scoreToneClass(businessPulseIncomeScore)}`}>
                                {businessPulseIncomeScore}/100
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3 text-center sm:text-left">
                            <div className="text-[10px] sm:text-[11px] uppercase tracking-[0.16em] text-slate-500 truncate">Expense</div>
                            <div className={`mt-0.5 sm:mt-1 text-sm sm:text-base font-semibold ${scoreToneClass(businessPulseExpenseScore)}`}>
                                {businessPulseExpenseScore}/100
                            </div>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-2.5 sm:p-3 text-center sm:text-left">
                            <div className="text-[10px] sm:text-[11px] uppercase tracking-[0.16em] text-slate-500 truncate">Operations</div>
                            <div className={`mt-0.5 sm:mt-1 text-sm sm:text-base font-semibold ${scoreToneClass(businessPulseOperationsScore)}`}>
                                {businessPulseOperationsScore}/100
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-4 sm:mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-2 lg:grid-cols-4">
                <MetricLink href={routes?.customers_index} label="Customers" value={metricValue(customerCount)} className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-slate-500" />
                <MetricLink href={routes?.subscriptions_index} label="Subscriptions" value={metricValue(subscriptionCount)} tone="text-sky-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-sky-700" />
                <MetricLink href={routes?.licenses_index} label="Licenses" value={metricValue(licenseCount)} tone="text-teal-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-teal-700" />
                <MetricLink href={routes?.invoices_unpaid} label="Unpaid invoices" value={metricValue(pendingInvoiceCount)} tone="text-blue-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-blue-700" />
            </div>

            <div className="mt-3 sm:mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={`${routes?.projects_all}?status=ongoing`} title="Ongoing projects" value={metricValue(projectMaintenance?.projects_active)} tone="text-sky-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-sky-700" />
                <SmallLinkCard href={routes?.subscriptions_index} title="Blocked services" value={metricValue(projectMaintenance?.subscriptions_blocked)} tone="text-rose-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-rose-700" />
                <SmallLinkCard href={routes?.project_maintenances_index} title="Renewals (30d)" value={metricValue(projectMaintenance?.renewals_30d)} tone="text-emerald-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-emerald-700" />
                <SmallLinkCard href={routes?.projects_all} title="Loss risk projects" value={metricValue(projectMaintenance?.projects_loss)} tone="text-rose-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-rose-700" />
            </div>

            <div className="mt-3 sm:mt-6 grid grid-cols-2 gap-2.5 sm:gap-4 md:grid-cols-2 lg:grid-cols-4">
                <SmallLinkCard href={routes?.hr_employees_index} title="Active employees" value={metricValue(hrStats?.active_employees)} tone="text-emerald-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-emerald-700" />
                <SmallLinkCard href={routes?.hr_timesheets_index} title="Work logs (7d)" value={metricValue(hrStats?.pending_timesheets)} tone="text-amber-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-amber-700" />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Draft payroll periods" value={metricValue(hrStats?.draft_payroll_periods)} tone="text-slate-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-slate-600" />
                <SmallLinkCard href={routes?.hr_payroll_index} title="Payroll to pay" value={metricValue(hrStats?.payroll_items_to_pay)} tone="text-rose-700" className="border-sky-200 bg-sky-50 hover:border-sky-300" labelClassName="text-rose-700" />
            </div>

            <div className="mt-8 card p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="section-label">System Overview</div>
                        <div className="mt-1 text-xs text-slate-500 sm:text-sm">Accounting snapshot across orders, revenue, and costs.</div>
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
                    <CompactOverviewStat label="Sales Orders (New)" value={metricValue(activeMetrics?.new_orders)} />
                    <CompactOverviewStat label="Sales Orders (Activated)" value={metricValue(activeMetrics?.active_orders)} tone="text-blue-600" />
                    <CompactOverviewStat label="Gross Revenue" value={money(currency, activeMetrics?.income)} tone="text-emerald-600" />
                    <CompactOverviewStat label="Operating Expense" value={money(currency, activeMetrics?.expense)} tone="text-orange-600" />
                    <CompactOverviewStat label="Hosting Revenue" value={money(currency, activeMetrics?.hosting_income)} tone="text-emerald-600" />
                    <CompactOverviewStat
                        label="Revenue per New Order"
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
                        <div className="rounded-xl border border-slate-200 bg-white p-2 pt-4">
                            <ResponsiveContainer width="100%" height={280}>
                                <ComposedChart data={chartData} margin={{ top: 4, right: 8, left: -8, bottom: 4 }}>
                                    <defs>
                                        <linearGradient id="incomeFillGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                            <stop offset="0%" stopColor={CHART_SERIES.income.fill} />
                                            <stop offset="100%" stopColor="rgba(16, 185, 129, 0.02)" />
                                        </linearGradient>
                                    </defs>
                                    <CartesianGrid stroke="#e2e8f0" strokeDasharray="4 4" vertical={false} />
                                    <XAxis
                                        dataKey="label"
                                        tick={{ fontSize: 11, fill: '#64748b' }}
                                        angle={-35}
                                        textAnchor="end"
                                        height={48}
                                        interval="preserveStartEnd"
                                    />
                                    <YAxis yAxisId="orders" tick={{ fontSize: 11, fill: '#64748b' }} width={36} allowDecimals={false} label={{ value: 'Orders', angle: -90, position: 'insideLeft', fontSize: 12, fill: '#334155' }} />
                                    <YAxis yAxisId="amount" orientation="right" tick={{ fontSize: 11, fill: '#64748b' }} width={48} label={{ value: 'Amount', angle: 90, position: 'insideRight', fontSize: 12, fill: '#334155' }} />
                                    <RechartsTooltip content={<DashboardChartTooltip currency={currency} />} />

                                    {seriesVisible.income ? (
                                        <Area yAxisId="amount" type="monotone" dataKey="income" name="Income" stroke={CHART_SERIES.income.stroke} fill="url(#incomeFillGradient)" strokeWidth={2.5} dot={{ r: 3, fill: CHART_SERIES.income.pointFill, stroke: CHART_SERIES.income.pointStroke, strokeWidth: 1.2 }} activeDot={{ r: 4 }} isAnimationActive={false} />
                                    ) : null}
                                    {seriesVisible.expense ? (
                                        <Line yAxisId="amount" type="monotone" dataKey="expense" name="Expense" stroke={CHART_SERIES.expense.stroke} strokeWidth={2.2} dot={{ r: 2.8, fill: CHART_SERIES.expense.pointFill, stroke: CHART_SERIES.expense.pointStroke, strokeWidth: 1.1 }} isAnimationActive={false} />
                                    ) : null}
                                    {seriesVisible.new_orders ? (
                                        <Line yAxisId="orders" type="monotone" dataKey="new_orders" name="New Orders" stroke={CHART_SERIES.new_orders.stroke} strokeWidth={2} dot={{ r: 2.5, fill: CHART_SERIES.new_orders.pointFill, stroke: CHART_SERIES.new_orders.pointStroke, strokeWidth: 1 }} isAnimationActive={false} />
                                    ) : null}
                                    {seriesVisible.active_orders ? (
                                        <Line yAxisId="orders" type="monotone" dataKey="active_orders" name="Activated Orders" stroke={CHART_SERIES.active_orders.stroke} strokeWidth={2} dot={{ r: 2.5, fill: CHART_SERIES.active_orders.pointFill, stroke: CHART_SERIES.active_orders.pointStroke, strokeWidth: 1 }} isAnimationActive={false} />
                                    ) : null}
                                </ComposedChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </div>

            </div>

            <div className="mt-8">
                <div className="card p-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div className="section-label">Client Activity</div>
                            <div className="mt-1 text-sm text-slate-500">Last 30 clients login (all time)</div>
                        </div>
                        <a href={routes?.customers_index} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View customers</a>
                    </div>

                    <div className="mt-4 max-h-[420px] overflow-auto rounded-xl border border-slate-200 px-2.5 py-2">
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
                <div className="mt-6 grid gap-4 lg:grid-cols-2">
                    <TaskList title="Open tasks" tasks={openTasks} routes={routes} variant="open" />
                    <TaskList title="In progress tasks" tasks={inProgressTasks} routes={routes} variant="in_progress" />
                </div>
            ) : null}
        </>
    );
}

Dashboard.title = 'Admin Dashboard';

const CURRENCY_CHART_KEYS = new Set(['income', 'expense']);

function DashboardChartTooltip({ active, payload, label, currency }) {
    if (!active || !Array.isArray(payload) || payload.length === 0) {
        return null;
    }

    return (
        <div className="rounded-2xl bg-slate-900 px-3 py-2 text-xs text-white shadow-xl">
            <div className="text-[11px] font-semibold text-slate-200">{label}</div>
            {payload.map((entry) => (
                <div key={entry.dataKey} className="mt-1 flex items-center gap-2">
                    <span className="h-2 w-2 rounded-sm" style={{ background: entry.color }} />
                    <span>
                        {entry.name}: {CURRENCY_CHART_KEYS.has(entry.dataKey) ? money(currency, entry.value) : metricValue(entry.value)}
                    </span>
                </div>
            ))}
        </div>
    );
}

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
        <div className="rounded-lg border border-slate-200 bg-white px-2.5 py-2 sm:px-3 sm:py-2.5">
            <div className="text-[9px] uppercase tracking-[0.16em] text-slate-400">{label}</div>
            <div className={`mt-1 text-[12px] font-semibold leading-tight sm:text-sm ${tone} break-words`}>{value}</div>
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
            wrapper: 'border-amber-200 bg-amber-50/40',
            title: 'text-amber-700',
            row: 'border-amber-100 bg-white hover:border-amber-300',
            badge: 'bg-amber-100 text-amber-700',
        },
        in_progress: {
            wrapper: 'border-sky-200 bg-sky-50/40',
            title: 'text-sky-700',
            row: 'border-sky-100 bg-white hover:border-sky-300',
            badge: 'bg-sky-100 text-sky-700',
        },
        default: {
            wrapper: 'border-slate-200 bg-white',
            title: 'text-slate-500',
            row: 'border-slate-100 bg-white hover:border-teal-200',
            badge: 'bg-slate-100 text-slate-600',
        },
    }[variant] || {
        wrapper: 'border-slate-200 bg-white',
        title: 'text-slate-500',
        row: 'border-slate-100 bg-white hover:border-teal-200',
        badge: 'bg-slate-100 text-slate-600',
    };

    return (
        <div className={`card rounded-2xl border p-3 sm:p-4 ${styles.wrapper}`}>
            <div className={`text-[11px] uppercase tracking-[0.2em] ${styles.title}`}>{title}</div>
            <div className="mt-2.5 space-y-1.5 sm:space-y-2">
                {rows.length === 0 ? (
                    <div className="text-xs text-slate-500">No tasks in this bucket.</div>
                ) : rows.map((task) => (
                    <a
                        key={task.id}
                        href={taskRoute(routes?.tasks_show_template, task.project_id, task.id)}
                        data-native="true"
                        className={`block rounded-lg border px-2.5 py-2 transition ${styles.row}`}
                    >
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <div className="text-[13px] font-semibold leading-5 text-slate-900 sm:text-sm">{task.title}</div>
                            <span className={`w-fit rounded-full px-2 py-0.5 text-[10px] font-semibold ${styles.badge}`}>{taskStatusLabel(task.status)}</span>
                        </div>
                        <div className="mt-1 text-[11px] leading-4 text-slate-500 sm:text-xs sm:leading-5">
                            {task.project_name} | Subtasks: {metricValue(task.subtasks_count)} | Due: {task.due_date || '--'}
                        </div>
                    </a>
                ))}
            </div>
        </div>
    );
}
