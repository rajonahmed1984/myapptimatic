import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

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
    systemOverview = {},
    clientActivity = {},
    showTasksWidget = false,
    taskSummary = null,
    openTasks = [],
    inProgressTasks = [],
    routes = {},
}) {
    const [period, setPeriod] = useState('month');

    const activeMetrics = periodMetrics?.[period] || { new_orders: 0, active_orders: 0, income: 0, hosting_income: 0 };
    const activeSeries = periodSeries?.[period] || { labels: [], new_orders: [], active_orders: [], income: [] };
    const recentClients = Array.isArray(clientActivity?.recentClients) ? clientActivity.recentClients : [];
    const summary = taskSummary || { total: 0, open: 0, in_progress: 0, completed: 0 };

    const trendRows = useMemo(() => {
        const labels = Array.isArray(activeSeries?.labels) ? activeSeries.labels : [];
        const newOrders = Array.isArray(activeSeries?.new_orders) ? activeSeries.new_orders : [];
        const activeOrders = Array.isArray(activeSeries?.active_orders) ? activeSeries.active_orders : [];
        const income = Array.isArray(activeSeries?.income) ? activeSeries.income : [];

        return labels.slice(-8).map((label, index) => {
            const offset = labels.length - Math.min(8, labels.length) + index;

            return {
                label,
                newOrders: Number(newOrders[offset] || 0),
                activeOrders: Number(activeOrders[offset] || 0),
                income: Number(income[offset] || 0),
            };
        });
    }, [activeSeries]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <MetricLink href={routes?.customers_index} label="Customers" value={metricValue(customerCount)} />
                <MetricLink href={routes?.subscriptions_index} label="Subscriptions" value={metricValue(subscriptionCount)} />
                <MetricLink href={routes?.licenses_index} label="Licenses" value={metricValue(licenseCount)} />
                <MetricLink href={routes?.invoices_unpaid} label="Unpaid invoices" value={metricValue(pendingInvoiceCount)} tone="text-blue-600" />
            </div>

            <div className="mt-6 card p-6">
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
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">System Overview</div>
                        <div className="mt-1 text-sm text-slate-500">Period activity snapshot</div>
                    </div>
                    <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1 text-xs font-semibold">
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

                <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SmallMetricTile label="New Orders" value={metricValue(activeMetrics?.new_orders)} />
                    <SmallMetricTile label="Activated Orders" value={metricValue(activeMetrics?.active_orders)} tone="text-blue-600" />
                    <SmallMetricTile label="Total Income" value={money(currency, activeMetrics?.income)} tone="text-emerald-600" />
                    <SmallMetricTile label="Hosting Income" value={money(currency, activeMetrics?.hosting_income)} tone="text-emerald-600" />
                    <SmallMetricTile
                        label="Avg Per Order"
                        value={money(currency, Number(activeMetrics?.new_orders || 0) > 0 ? Number(activeMetrics?.income || 0) / Number(activeMetrics?.new_orders || 1) : 0)}
                        tone="text-emerald-600"
                    />
                </div>

                <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200">
                    <table className="min-w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Period</th>
                                <th className="px-3 py-2">New</th>
                                <th className="px-3 py-2">Active</th>
                                <th className="px-3 py-2">Income</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {trendRows.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-3 py-3 text-slate-500">No trend rows available.</td>
                                </tr>
                            ) : trendRows.map((row) => (
                                <tr key={row.label}>
                                    <td className="px-3 py-2">{row.label}</td>
                                    <td className="px-3 py-2">{metricValue(row.newOrders)}</td>
                                    <td className="px-3 py-2">{metricValue(row.activeOrders)}</td>
                                    <td className="px-3 py-2">{money(currency, row.income)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
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

                <div className="mt-4 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="py-2 pr-4">User</th>
                                <th className="py-2 pr-4">Last login</th>
                                <th className="py-2 pr-4">IP</th>
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
                            <a href={routes?.automation_status} data-native="true" className="mt-1 inline-flex text-xs font-semibold text-teal-600 hover:text-teal-500">
                                View automation status
                            </a>
                        </div>
                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {systemOverview?.automation_cards?.status_badge || '--'}
                        </span>
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <SmallMetricTile label="Invoices Created" value={metricValue(automation?.invoices_created)} />
                        <SmallMetricTile label="Overdue Suspensions" value={metricValue(automation?.overdue_suspensions)} />
                        <SmallMetricTile label="Tickets Closed" value={metricValue(automation?.tickets_closed)} />
                        <SmallMetricTile label="Overdue Reminders" value={metricValue(automation?.overdue_reminders)} />
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
        <a href={href} data-native="true" className="card px-4 py-3 leading-tight transition hover:border-teal-300 hover:shadow-sm">
            <div className="text-xs uppercase tracking-[0.25em] text-slate-400">{title}</div>
            <div className={`mt-1 text-xl font-semibold ${tone}`}>{value}</div>
        </a>
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
