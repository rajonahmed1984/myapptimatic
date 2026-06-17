import React from 'react';
import { Head, usePage } from '@inertiajs/react';

/* ─── tiny helpers ─────────────────────────────────────────────────── */

function StatusDot({ color = 'slate' }) {
    const map = {
        green:  'bg-emerald-400',
        amber:  'bg-amber-400',
        rose:   'bg-rose-400',
        slate:  'bg-slate-400',
        sky:    'bg-sky-400',
        violet: 'bg-violet-400',
    };
    return (
        <span className={`inline-block h-2 w-2 rounded-full ${map[color] ?? map.slate}`} />
    );
}

function Badge({ children, color = 'slate' }) {
    const map = {
        green:  'bg-emerald-50 text-emerald-700 border-emerald-200',
        amber:  'bg-amber-50  text-amber-700  border-amber-200',
        rose:   'bg-rose-50   text-rose-700   border-rose-200',
        slate:  'bg-slate-100 text-slate-600  border-slate-200',
        sky:    'bg-sky-50    text-sky-700    border-sky-200',
    };
    return (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-[0.15em] ${map[color] ?? map.slate}`}>
            {children}
        </span>
    );
}

function StatCard({ href, label, value, color = 'slate' }) {
    const accent = {
        teal:   'hover:border-teal-300',
        sky:    'hover:border-sky-300',
        amber:  'hover:border-amber-300',
        rose:   'hover:border-rose-300',
        slate:  'hover:border-slate-300',
    };
    const numColor = {
        teal:  'text-teal-600',
        sky:   'text-sky-600',
        amber: 'text-amber-600',
        rose:  'text-rose-600',
        slate: 'text-slate-800',
    };
    return (
        <a
            href={href}
            data-native="true"
            className={`card flex flex-col gap-1 p-4 transition hover:shadow-sm ${accent[color] ?? ''}`}
        >
            <div className={`text-2xl font-bold tabular-nums ${numColor[color] ?? 'text-slate-800'}`}>{value}</div>
            <div className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">{label}</div>
        </a>
    );
}

/* ─── account health banner ────────────────────────────────────────── */
function AccountStatus({ openInvoiceCount, openInvoiceBalance, currency, nextOpenInvoice, routes }) {
    if (openInvoiceCount === 0) return null;
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm">
            <div className="flex items-center gap-2 text-rose-700">
                <StatusDot color="rose" />
                <span>
                    <span className="font-semibold">{openInvoiceCount} unpaid invoice{openInvoiceCount > 1 ? 's' : ''}</span>
                    {' '}— balance due: <span className="font-semibold">{currency} {Number(openInvoiceBalance).toFixed(2)}</span>
                    {nextOpenInvoice ? <span className="text-rose-500"> · Due {nextOpenInvoice.due_date_display}</span> : null}
                </span>
            </div>
            {nextOpenInvoice ? (
                <a href={nextOpenInvoice.route_pay} data-native="true"
                    className="rounded-full bg-rose-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700">
                    Pay now
                </a>
            ) : (
                <a href={routes.invoices_index} data-native="true"
                    className="rounded-full bg-rose-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700">
                    View invoices
                </a>
            )}
        </div>
    );
}

/* ─── tasks compact strip ──────────────────────────────────────────── */
function TaskStrip({ showTasksWidget, taskSummary = {}, openTasks = [], inProgressTasks = [], }) {
    const { csrf_token: csrfToken } = usePage().props;
    if (!showTasksWidget) return null;

    const allTasks = [
        ...openTasks.map(t => ({ ...t, _status: 'open' })),
        ...inProgressTasks.map(t => ({ ...t, _status: 'in_progress' })),
    ];

    return (
        <div className="card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="section-label">Tasks</span>
                    <span className="flex gap-2 text-xs text-slate-500">
                        <span><span className="font-semibold text-slate-700">{taskSummary.open || 0}</span> open</span>
                        <span>·</span>
                        <span><span className="font-semibold text-amber-600">{taskSummary.in_progress || 0}</span> in progress</span>
                        <span>·</span>
                        <span><span className="font-semibold text-emerald-600">{taskSummary.completed || 0}</span> done</span>
                    </span>
                </div>
                <a href="/client/tasks" data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                    All tasks →
                </a>
            </div>

            {allTasks.length > 0 && (
                <div className="mt-4 divide-y divide-slate-100">
                    {allTasks.slice(0, 5).map((task) => (
                        <div key={task.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                            <div>
                                <div className="text-sm font-medium text-slate-800">{task.title}</div>
                                {task.project ? (
                                    <a href={task.project.route_show} data-native="true"
                                        className="text-xs text-slate-400 hover:text-teal-600">
                                        {task.project.name}
                                    </a>
                                ) : null}
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge color={task._status === 'in_progress' ? 'amber' : 'slate'}>
                                    {task._status === 'in_progress' ? 'In Progress' : 'Open'}
                                </Badge>
                                {task.project ? (
                                    <a href={task.project.route_task_show} data-native="true"
                                        className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                                        View
                                    </a>
                                ) : null}
                                {task.can_complete && task.project ? (
                                    <form method="POST" action={task.project.route_task_update} data-native="true" className="inline">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="PATCH" />
                                        <input type="hidden" name="status" value="completed" />
                                        <button type="submit"
                                            className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                            Mark done
                                        </button>
                                    </form>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/* ─── projects list ────────────────────────────────────────────────── */
function ProjectsList({ projects = [], routes }) {
    return (
        <div className="card p-5">
            <div className="flex items-center justify-between">
                <span className="section-label">Projects</span>
                <a href={routes.projects_index} data-native="true"
                    className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                    View all →
                </a>
            </div>
            <div className="mt-4 divide-y divide-slate-100">
                {projects.length === 0 ? (
                    <div className="py-3 text-sm text-slate-400">No ongoing projects right now.</div>
                ) : (
                    projects.map((project) => {
                        const pct = project.total_tasks_count > 0
                            ? Math.round((project.done_tasks_count / project.total_tasks_count) * 100)
                            : 0;
                        return (
                            <a key={project.id} href={project.routes.show} data-native="true"
                                className="group flex items-center justify-between gap-4 py-3 transition">
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-medium text-slate-800 group-hover:text-teal-600">
                                        {project.name}
                                    </div>
                                    <div className="mt-1.5 flex items-center gap-2">
                                        <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-full rounded-full bg-teal-400 transition-all"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                        <span className="shrink-0 text-[11px] text-slate-400 tabular-nums">
                                            {project.done_tasks_count}/{project.total_tasks_count}
                                        </span>
                                    </div>
                                </div>
                                <Badge color={
                                    project.status_label?.toLowerCase().includes('complet') ? 'green' :
                                    project.status_label?.toLowerCase().includes('progress') ? 'amber' : 'slate'
                                }>
                                    {project.status_label}
                                </Badge>
                            </a>
                        );
                    })
                )}
            </div>
        </div>
    );
}

/* ─── services ─────────────────────────────────────────────────────── */
function ServicesList({ subscriptions = [], routes }) {
    return (
        <div className="card p-5">
            <div className="flex items-center justify-between">
                <span className="section-label">Active Services</span>
                <a href={routes.licenses_index} data-native="true"
                    className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                    Manage →
                </a>
            </div>
            <div className="mt-4 divide-y divide-slate-100">
                {subscriptions.length === 0 ? (
                    <div className="py-3 text-sm text-slate-400">
                        No active services yet.{' '}
                        <a href={routes.orders_index} data-native="true" className="font-semibold text-teal-600 hover:text-teal-500">
                            Place an order
                        </a>
                    </div>
                ) : (
                    subscriptions.map((sub) => (
                        <div key={sub.id} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <div className="text-sm font-medium text-slate-800">{sub.plan_name}</div>
                                <div className="text-xs text-slate-400">{sub.product_name} · Renews {sub.next_invoice_display}</div>
                            </div>
                            <Badge color="green">{sub.status_label}</Badge>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

/* ─── invoices ─────────────────────────────────────────────────────── */
function RecentInvoices({ invoices = [], routes }) {
    const statusColor = (label) => {
        const l = (label || '').toLowerCase();
        if (l.includes('paid')) return 'green';
        if (l.includes('overdue')) return 'rose';
        if (l.includes('unpaid') || l.includes('due')) return 'amber';
        return 'slate';
    };
    return (
        <div className="card p-5">
            <div className="flex items-center justify-between">
                <span className="section-label">Recent Invoices</span>
                <a href={routes.invoices_index} data-native="true"
                    className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                    All invoices →
                </a>
            </div>
            <div className="mt-4 divide-y divide-slate-100">
                {invoices.length === 0 ? (
                    <div className="py-3 text-sm text-slate-400">No invoices yet.</div>
                ) : (
                    invoices.map((inv) => (
                        <div key={inv.id} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <div className="text-sm font-medium text-slate-800">#{inv.number}</div>
                                <div className="text-xs text-slate-400">Due {inv.due_date_display}</div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="tabular-nums text-sm font-semibold text-slate-700">
                                    {inv.currency} {inv.total}
                                </span>
                                <Badge color={statusColor(inv.status_label)}>{inv.status_label}</Badge>
                                {inv.can_pay ? (
                                    <a href={inv.routes.pay} data-native="true"
                                        className="text-xs font-semibold text-rose-600 hover:text-rose-500">
                                        Pay
                                    </a>
                                ) : null}
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

/* ─── support tickets ──────────────────────────────────────────────── */
function RecentTickets({ recentTickets = [], routes }) {
    const ticketColor = (label) => {
        const l = (label || '').toLowerCase();
        if (l.includes('open')) return 'amber';
        if (l.includes('closed') || l.includes('resolved')) return 'green';
        return 'slate';
    };
    return (
        <div className="card p-5">
            <div className="flex items-center justify-between">
                <span className="section-label">Support Tickets</span>
                <a href={routes.support_create} data-native="true"
                    className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 transition">
                    + New ticket
                </a>
            </div>
            <div className="mt-4 divide-y divide-slate-100">
                {recentTickets.length === 0 ? (
                    <div className="py-3 text-sm text-slate-400">No tickets — you're all good!</div>
                ) : (
                    recentTickets.map((ticket) => (
                        <div key={ticket.id} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <div className="truncate text-sm font-medium text-slate-800">{ticket.subject}</div>
                                <div className="text-xs text-slate-400">Updated {ticket.updated_at_display}</div>
                            </div>
                            <Badge color={ticketColor(ticket.status_label)}>{ticket.status_label}</Badge>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}

/* ─── licenses / domains ────────────────────────────────────────────── */
function ExpiringLicenses({ expiringLicenses = [], routes }) {
    if (expiringLicenses.length === 0) return null;
    return (
        <div className="card border-amber-200 bg-amber-50/60 p-5">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <StatusDot color="amber" />
                    <span className="section-label text-amber-700">Licenses Expiring Soon</span>
                </div>
                <a href={routes.orders_index} data-native="true"
                    className="text-xs font-semibold text-amber-700 hover:text-amber-600">
                    Renew →
                </a>
            </div>
            <div className="mt-3 divide-y divide-amber-100">
                {expiringLicenses.map((lic) => (
                    <div key={lic.id} className="flex items-center justify-between py-2 text-sm">
                        <span className="text-amber-800">{lic.product_name}</span>
                        <span className="text-xs text-amber-600 tabular-nums">Expires {lic.expires_at_display}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/* ─── maintenance renewal ──────────────────────────────────────────── */
function MaintenanceCard({ maintenanceRenewal, routes }) {
    if (!maintenanceRenewal) return null;
    return (
        <div className="card p-5">
            <div className="section-label">Maintenance</div>
            <div className="mt-3 space-y-1 text-sm text-slate-600">
                <div>Plan: <span className="font-medium text-slate-800">{maintenanceRenewal.plan}</span></div>
                <div>Next renewal: <span className="font-medium text-slate-800">{maintenanceRenewal.next_renewal_display}</span></div>
                {maintenanceRenewal.project_name && (
                    <div>Project: <span className="font-medium text-slate-800">{maintenanceRenewal.project_name}</span></div>
                )}
            </div>
            <div className="mt-4">
                <a href={routes.invoices_index} data-native="true"
                    className="rounded-full border border-slate-200 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 transition">
                    View invoices
                </a>
            </div>
        </div>
    );
}

/* ─── main page ─────────────────────────────────────────────────────── */
export default function Index({
    customer = null,
    subscriptions = [],
    invoices = [],
    serviceCount = 0,
    projectCount = 0,
    ticketOpenCount = 0,
    openInvoiceCount = 0,
    openInvoiceBalance = 0,
    nextOpenInvoice = null,
    recentTickets = [],
    expiringLicenses = [],
    currency = 'USD',
    projects = [],
    maintenanceRenewal = null,
    showTasksWidget = false,
    taskSummary = null,
    openTasks = [],
    inProgressTasks = [],
    routes = {},
}) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6">

                {/* ── greeting ──────────────────────────────── */}
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="text-2xl font-semibold text-slate-900">
                            Welcome back, {customer?.name || 'Client'}
                        </div>
                        <div className="mt-0.5 text-sm text-slate-400">{customer?.email || ''}</div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={routes.orders_index} data-native="true"
                            className="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-teal-600">
                            Place an order
                        </a>
                        <a href={routes.support_create} data-native="true"
                            className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600">
                            Open a ticket
                        </a>
                    </div>
                </div>

                {/* ── outstanding invoice alert ──────────────── */}
                <AccountStatus
                    openInvoiceCount={openInvoiceCount}
                    openInvoiceBalance={openInvoiceBalance}
                    currency={currency}
                    nextOpenInvoice={nextOpenInvoice}
                    routes={routes}
                />

                {/* ── expiring licenses alert ────────────────── */}
                <ExpiringLicenses expiringLicenses={expiringLicenses} routes={routes} />

                {/* ── stat cards ────────────────────────────── */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard href={routes.licenses_index} label="Services"    value={serviceCount}      color="teal"  />
                    <StatCard href={routes.projects_index} label="Projects"    value={projectCount || 0} color="sky"   />
                    <StatCard href={routes.support_index}  label="Open Tickets" value={ticketOpenCount}  color="amber" />
                    <StatCard href={routes.invoices_index} label="Unpaid Invoices" value={openInvoiceCount} color="rose" />
                </div>

                {/* ── tasks strip ───────────────────────────── */}
                <TaskStrip
                    showTasksWidget={showTasksWidget}
                    taskSummary={taskSummary || {}}
                    openTasks={openTasks}
                    inProgressTasks={inProgressTasks}
                />

                {/* ── two-column content ────────────────────── */}
                <div className="grid gap-5 lg:grid-cols-2">
                    <div className="space-y-5">
                        <ProjectsList projects={projects} routes={routes} />
                        <ServicesList subscriptions={subscriptions} routes={routes} />
                        {maintenanceRenewal && <MaintenanceCard maintenanceRenewal={maintenanceRenewal} routes={routes} />}
                    </div>
                    <div className="space-y-5">
                        <RecentInvoices invoices={invoices} routes={routes} />
                        <RecentTickets recentTickets={recentTickets} routes={routes} />
                    </div>
                </div>

            </div>
        </>
    );
}
