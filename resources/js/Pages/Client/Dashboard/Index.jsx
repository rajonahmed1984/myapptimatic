import React from 'react';
import { Head, usePage } from '@inertiajs/react';

/* ─── tiny helpers ─────────────────────────────────────────────────── */

function StatusDot({ color = 'slate' }) {
    const map = {
        green:  'bg-emerald-500 ring-4 ring-emerald-50',
        amber:  'bg-amber-500 ring-4 ring-amber-50',
        rose:   'bg-rose-500 ring-4 ring-rose-50',
        slate:  'bg-slate-400 ring-4 ring-slate-50',
        sky:    'bg-sky-500 ring-4 ring-sky-50',
        violet: 'bg-violet-500 ring-4 ring-violet-50',
    };
    return (
        <span className={`inline-block h-2.5 w-2.5 rounded-full shrink-0 ${map[color] ?? map.slate}`} />
    );
}

function Badge({ children, color = 'slate' }) {
    const map = {
        green:  'bg-emerald-50 text-emerald-700 border-emerald-200/80',
        amber:  'bg-amber-50 text-amber-700 border-amber-200/80',
        rose:   'bg-rose-50 text-rose-700 border-rose-200/80',
        slate:  'bg-slate-100 text-slate-600 border-slate-200/80',
        sky:    'bg-sky-50 text-sky-700 border-sky-200/80',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
    };
    return (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide ${map[color] ?? map.slate}`}>
            {children}
        </span>
    );
}

/* ─── stat tile (WHMCS / reference style) ─────────────────────────── */
function StatTile({ href, label, value, accentColor, hoverBorder, icon, valueColor = 'text-[#0e5077]' }) {
    return (
        <a
            href={href}
            data-native="true"
            className={`group relative flex items-center justify-between overflow-hidden rounded-xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md ${hoverBorder} ${accentColor}`}
        >
            {/* Left Content */}
            <div className="z-10 flex flex-col justify-center min-w-0 pr-2">
                <div className={`text-3xl sm:text-4xl font-extrabold tabular-nums tracking-tight ${valueColor}`}>
                    {value}
                </div>
                <div className="mt-1 text-xs font-bold uppercase tracking-wider text-slate-500">
                    {label}
                </div>
            </div>

            {/* Right Watermark Silhouette Icon */}
            <div className="shrink-0 select-none opacity-85 group-hover:opacity-100 transition-opacity">
                {icon}
            </div>
        </a>
    );
}

/* ─── account health banner ────────────────────────────────────────── */
function AccountStatus({ openInvoiceCount, openInvoiceBalance, currency, nextOpenInvoice, routes }) {
    if (openInvoiceCount === 0) return null;
    return (
        <div className="rounded-2xl border border-rose-200 bg-rose-50/90 p-4 sm:p-5 text-sm shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex items-start sm:items-center gap-3 text-rose-900">
                    <div className="mt-0.5 sm:mt-0 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <div className="font-bold text-rose-950">
                            {openInvoiceCount} Unpaid Invoice{openInvoiceCount > 1 ? 's' : ''} Pending
                        </div>
                        <div className="text-xs text-rose-800 mt-0.5">
                            Total Outstanding Balance: <span className="font-extrabold text-rose-950">{currency} {Number(openInvoiceBalance).toFixed(2)}</span>
                            {nextOpenInvoice ? <span className="block sm:inline sm:ml-2">· Due Date: <span className="font-semibold">{nextOpenInvoice.due_date_display}</span></span> : null}
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 w-full sm:w-auto">
                    {nextOpenInvoice ? (
                        <a
                            href={nextOpenInvoice.route_pay}
                            data-native="true"
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-rose-700 active:scale-95"
                        >
                            <span>Pay Now</span>
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    ) : (
                        <a
                            href={routes.invoices_index}
                            data-native="true"
                            className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-rose-700 active:scale-95"
                        >
                            <span>View Invoices</span>
                            <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}

/* ─── tasks compact strip ──────────────────────────────────────────── */
function TaskStrip({ showTasksWidget, taskSummary = {}, openTasks = [], inProgressTasks = [] }) {
    const { csrf_token: csrfToken } = usePage().props;
    if (!showTasksWidget) return null;

    const allTasks = [
        ...openTasks.map(t => ({ ...t, _status: 'open' })),
        ...inProgressTasks.map(t => ({ ...t, _status: 'in_progress' })),
    ];

    return (
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-slate-700">Tasks Overview</div>
                        <div className="flex flex-wrap items-center gap-1.5 text-xs text-slate-500 mt-0.5">
                            <span><span className="font-semibold text-slate-800">{taskSummary.open || 0}</span> open</span>
                            <span>·</span>
                            <span><span className="font-semibold text-amber-600">{taskSummary.in_progress || 0}</span> in progress</span>
                            <span>·</span>
                            <span><span className="font-semibold text-emerald-600">{taskSummary.completed || 0}</span> done</span>
                        </div>
                    </div>
                </div>
                <a href="/client/tasks" data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-700 inline-flex items-center gap-1">
                    <span>All tasks</span>
                    <span>→</span>
                </a>
            </div>

            {allTasks.length > 0 ? (
                <div className="mt-3 divide-y divide-slate-100">
                    {allTasks.slice(0, 5).map((task) => (
                        <div key={task.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 py-3">
                            <div className="min-w-0 flex-1">
                                <div className="text-sm font-medium text-slate-800 break-words">{task.title}</div>
                                {task.project ? (
                                    <a href={task.project.route_show} data-native="true"
                                        className="text-xs text-slate-400 hover:text-teal-600 inline-block mt-0.5">
                                        {task.project.name}
                                    </a>
                                ) : null}
                            </div>
                            <div className="flex items-center gap-2 self-start sm:self-auto shrink-0">
                                <Badge color={task._status === 'in_progress' ? 'amber' : 'slate'}>
                                    {task._status === 'in_progress' ? 'In Progress' : 'Open'}
                                </Badge>
                                {task.project ? (
                                    <a href={task.project.route_task_show} data-native="true"
                                        className="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 transition">
                                        View
                                    </a>
                                ) : null}
                                {task.can_complete && task.project ? (
                                    <form method="POST" action={task.project.route_task_update} data-native="true" className="inline">
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="PATCH" />
                                        <input type="hidden" name="status" value="completed" />
                                        <button type="submit"
                                            className="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                            Mark done
                                        </button>
                                    </form>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="py-6 text-center text-sm text-slate-400">
                    No pending tasks right now.
                </div>
            )}
        </div>
    );
}

/* ─── projects list ────────────────────────────────────────────────── */
function ProjectsList({ projects = [], routes }) {
    return (
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                <div className="flex items-center gap-2.5">
                    <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </div>
                    <span className="text-xs font-bold uppercase tracking-wider text-slate-700">Ongoing Projects</span>
                </div>
                <a href={routes.projects_index || '/client/projects'} data-native="true"
                    className="text-xs font-semibold text-teal-600 hover:text-teal-700 inline-flex items-center gap-1">
                    <span>View all</span>
                    <span>→</span>
                </a>
            </div>

            <div className="mt-2 divide-y divide-slate-100">
                {projects.length === 0 ? (
                    <div className="py-6 text-center text-sm text-slate-400">
                        No ongoing projects right now.
                    </div>
                ) : (
                    projects.map((project) => {
                        const pct = project.total_tasks_count > 0
                            ? Math.round((project.done_tasks_count / project.total_tasks_count) * 100)
                            : 0;
                        return (
                            <a key={project.id} href={project.routes.show} data-native="true"
                                className="group flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3.5 transition hover:bg-slate-50/70 -mx-2 px-2 rounded-xl">
                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-sm font-semibold text-slate-800 group-hover:text-teal-600">
                                        {project.name}
                                    </div>
                                    <div className="mt-2 flex items-center gap-2.5">
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="h-full rounded-full bg-teal-500 transition-all duration-300"
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                        <span className="shrink-0 text-xs font-medium text-slate-500 tabular-nums">
                                            {project.done_tasks_count}/{project.total_tasks_count} ({pct}%)
                                        </span>
                                    </div>
                                </div>
                                <div className="self-start sm:self-center shrink-0">
                                    <Badge color={
                                        project.status_label?.toLowerCase().includes('complet') ? 'green' :
                                        project.status_label?.toLowerCase().includes('progress') ? 'amber' : 'slate'
                                    }>
                                        {project.status_label}
                                    </Badge>
                                </div>
                            </a>
                        );
                    })
                )}
            </div>
        </div>
    );
}

/* ─── services & subscriptions ─────────────────────────────────────── */
function ServicesList({ subscriptions = [], routes }) {
    return (
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                <div className="flex items-center gap-2.5">
                    <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                        <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.378 1.602a.75.75 0 00-.756 0L3.372 6.297A.75.75 0 003 6.947v10.106a.75.75 0 00.372.65l8.25 4.695a.75.75 0 00.756 0l8.25-4.695a.75.75 0 00.372-.65V6.947a.75.75 0 00-.372-.65L12.378 1.602zM12 3.168l6.837 3.893-2.987 1.702L9.013 4.87 12 3.168zM4.5 7.917l6.75 3.844v7.79l-6.75-3.844V7.917zm8.25 11.634v-7.79l6.75-3.844v7.79l-6.75 3.844z" />
                        </svg>
                    </div>
                    <span className="text-xs font-bold uppercase tracking-wider text-slate-700">Active Services</span>
                </div>
                <a href={routes.services_index || routes.licenses_index || '/client/services'} data-native="true"
                    className="text-xs font-semibold text-teal-600 hover:text-teal-700 inline-flex items-center gap-1">
                    <span>Manage</span>
                    <span>→</span>
                </a>
            </div>

            <div className="mt-2 divide-y divide-slate-100">
                {subscriptions.length === 0 ? (
                    <div className="py-6 text-center text-sm text-slate-400">
                        No active services yet.{' '}
                        <a href={routes.orders_index || '/client/orders'} data-native="true" className="font-semibold text-teal-600 hover:underline">
                            Place an order
                        </a>
                    </div>
                ) : (
                    subscriptions.map((sub) => (
                        <div key={sub.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 py-3.5">
                            <div className="min-w-0 flex-1">
                                <div className="text-sm font-semibold text-slate-800">{sub.plan_name}</div>
                                <div className="text-xs text-slate-500 mt-0.5">
                                    <span className="font-medium text-slate-600">{sub.product_name}</span>
                                    <span className="mx-1.5 text-slate-300">·</span>
                                    <span>Next Renewal: {sub.next_invoice_display}</span>
                                </div>
                            </div>
                            <div className="self-start sm:self-auto shrink-0">
                                <Badge color="green">{sub.status_label}</Badge>
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    );
}



/* ─── licenses / domains alert ─────────────────────────────────────── */
function ExpiringLicenses({ expiringLicenses = [], routes }) {
    if (expiringLicenses.length === 0) return null;
    return (
        <div className="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 sm:p-5 shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    <StatusDot color="amber" />
                    <span className="text-xs font-bold uppercase tracking-wider text-amber-900">
                        Licenses / Domains Expiring Soon ({expiringLicenses.length})
                    </span>
                </div>
                <a href={routes.orders_index || '/client/orders'} data-native="true"
                    className="text-xs font-bold text-amber-800 hover:text-amber-950 inline-flex items-center gap-1 self-start sm:self-auto">
                    <span>Renew Services</span>
                    <span>→</span>
                </a>
            </div>
            <div className="mt-3 divide-y divide-amber-200/60">
                {expiringLicenses.map((lic) => (
                    <div key={lic.id} className="flex items-center justify-between py-2 text-sm">
                        <span className="font-semibold text-amber-950">{lic.product_name}</span>
                        <span className="text-xs font-medium text-amber-700 tabular-nums">Expires {lic.expires_at_display}</span>
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
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wider text-slate-700 pb-2 border-b border-slate-100">
                Maintenance Plan
            </div>
            <div className="mt-3 space-y-1.5 text-sm text-slate-600">
                <div>Plan: <span className="font-semibold text-slate-900">{maintenanceRenewal.plan}</span></div>
                <div>Next Renewal: <span className="font-semibold text-slate-900">{maintenanceRenewal.next_renewal_display}</span></div>
                {maintenanceRenewal.project_name && (
                    <div>Project: <span className="font-semibold text-slate-900">{maintenanceRenewal.project_name}</span></div>
                )}
            </div>
            <div className="mt-4">
                <a href={routes.invoices_index || '/client/invoices'} data-native="true"
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-400 hover:text-teal-700 transition">
                    View Invoices
                </a>
            </div>
        </div>
    );
}

/* ─── SVG icons for WHMCS stat tiles ───────────────────────────────── */
const BoxIsometricIcon = () => (
    <svg className="w-12 h-12 sm:w-14 sm:h-14 text-slate-300 group-hover:text-sky-400/80 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.378 1.602a.75.75 0 00-.756 0L3.372 6.297A.75.75 0 003 6.947v10.106a.75.75 0 00.372.65l8.25 4.695a.75.75 0 00.756 0l8.25-4.695a.75.75 0 00.372-.65V6.947a.75.75 0 00-.372-.65L12.378 1.602zM12 3.168l6.837 3.893-2.987 1.702L9.013 4.87 12 3.168zM4.5 7.917l6.75 3.844v7.79l-6.75-3.844V7.917zm8.25 11.634v-7.79l6.75-3.844v7.79l-6.75 3.844z" />
    </svg>
);

const LicenseKeyIcon = () => (
    <svg className="w-12 h-12 sm:w-14 sm:h-14 text-slate-300 group-hover:text-emerald-400/80 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path fillRule="evenodd" d="M15.75 1.5a6.75 6.75 0 00-6.651 7.906c-.067.39-.142.779-.224 1.168L2.47 16.978A1.5 1.5 0 002 18.038V21a1.5 1.5 0 001.5 1.5H6a1.5 1.5 0 001.5-1.5v-1.5h1.5A1.5 1.5 0 0010.5 18v-1.5h1.5a1.5 1.5 0 001.06-.44l1.378-1.378c.389-.082.778-.157 1.168-.224A6.75 6.75 0 1015.75 1.5zm1.5 4.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" clipRule="evenodd" />
    </svg>
);

const SpeechBubblesIcon = () => (
    <svg className="w-12 h-12 sm:w-14 sm:h-14 text-slate-300 group-hover:text-rose-400/80 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M4.5 3.75A2.75 2.75 0 001.75 6.5v6a2.75 2.75 0 002.75 2.75h1.25v2.5a.75.75 0 001.28.53l3.03-3.03h3.44A2.75 2.75 0 0016.25 12.5v-6a2.75 2.75 0 00-2.75-2.75h-9zm12.5 4.5h-.75v4.25a3.5 3.5 0 01-3.5 3.5H9.5v.75A2.25 2.25 0 0011.75 19h4.44l2.53 2.53a.75.75 0 001.28-.53v-2H20.5A2.25 2.25 0 0022.75 16.75v-6.5A2.25 2.25 0 0020.5 8.25h-3.5z" />
    </svg>
);

const CreditCardIcon = () => (
    <svg className="w-12 h-12 sm:w-14 sm:h-14 text-slate-300 group-hover:text-amber-400/80 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path fillRule="evenodd" d="M2.25 6A2.25 2.25 0 014.5 3.75h15A2.25 2.25 0 0121.75 6v12A2.25 2.25 0 0119.5 20.25h-15A2.25 2.25 0 012.25 18V6zm2.25-.75A.75.75 0 003.75 6v1.5h16.5V6a.75.75 0 00-.75-.75h-15zm16.5 4.5H3.75V18c0 .414.336.75.75.75h15a.75.75 0 00.75-.75V9.75zm-14.25 4.5a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H7.5a.75.75 0 01-.75-.75zm4.5 0a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75z" clipRule="evenodd" />
    </svg>
);

const FolderProjectsIcon = () => (
    <svg className="w-12 h-12 sm:w-14 sm:h-14 text-slate-300 group-hover:text-sky-400/80 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-1.5V9a3 3 0 00-3-3h-3.379a3 3 0 01-2.121-.879L8.379 4.043A3 3 0 006.257 3.164H4.5A3 3 0 001.5 6.164v11.836A3 3 0 004.5 21h15z" />
    </svg>
);

/* ─── main client dashboard component ──────────────────────────────── */
export default function Index({
    customer = null,
    subscriptions = [],
    invoices = [],
    serviceCount = 0,
    projectCount = 0,
    licenseCount = 0,
    domainCount = 0,
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
    // Determine invoices count display for WHMCS tile:
    // In WHMCS, tile shows unpaid invoices if any, or total active invoices
    const displayInvoiceCount = openInvoiceCount > 0 ? openInvoiceCount : (invoices?.length || 0);

    return (
        <>
            <Head title="Client Area" />

            <div className="space-y-4 sm:space-y-6">

                {/* ── Welcome Header Card ── */}
                <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-6 shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2.5">
                                <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                                    Welcome back, {customer?.name || 'Client'}
                                </h1>
                                <span className="inline-flex items-center gap-1 rounded-full bg-teal-50 px-2.5 py-0.5 text-[11px] font-bold text-teal-700 border border-teal-200/70">
                                    <span className="h-1.5 w-1.5 rounded-full bg-teal-500" />
                                    Active
                                </span>
                            </div>
                            <div className="mt-1 text-xs sm:text-sm text-slate-500 flex flex-wrap items-center gap-x-2">
                                <span>{customer?.email || 'Logged in to Client Portal'}</span>
                                {openInvoiceCount > 0 && (
                                    <span className="text-rose-600 font-medium">
                                        · {openInvoiceCount} invoice{openInvoiceCount > 1 ? 's' : ''} awaiting payment
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Action Buttons: optimized for mobile tap targets */}
                        <div className="flex flex-wrap items-center gap-2.5 w-full md:w-auto pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                            <a
                                href={routes.orders_index || '/client/orders'}
                                data-native="true"
                                className="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-teal-700 active:scale-95"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>Order Service</span>
                            </a>
                            <a
                                href={routes.support_create || '/client/support-tickets/create'}
                                data-native="true"
                                className="flex-1 sm:flex-initial inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-teal-300 hover:text-teal-700 active:scale-95"
                            >
                                <svg className="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                                <span>Open Ticket</span>
                            </a>
                            <a
                                href={routes.licenses_index || '/client/licenses'}
                                data-native="true"
                                className="hidden sm:inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-white hover:border-slate-300 transition"
                            >
                                <span>Licenses</span>
                            </a>
                        </div>
                    </div>
                </div>

                {/* ── Outstanding Invoice Alert ── */}
                <AccountStatus
                    openInvoiceCount={openInvoiceCount}
                    openInvoiceBalance={openInvoiceBalance}
                    currency={currency}
                    nextOpenInvoice={nextOpenInvoice}
                    routes={routes}
                />

                {/* ── Expiring Licenses / Domains Alert ── */}
                <ExpiringLicenses expiringLicenses={expiringLicenses} routes={routes} />

                {/* ── Stat Cards (WHMCS Reference Style with Bottom Color Strips & Silhouette Icons) ── */}
                {/* On mobile: clean 2x2 grid. On desktop: 4 columns (or 5 columns if projects exist) */}
                <div className={`grid grid-cols-2 gap-2.5 sm:gap-4 ${projectCount > 0 ? 'lg:grid-cols-5' : 'lg:grid-cols-4'}`}>
                    {/* 1. SERVICES */}
                    <StatTile
                        href={routes.services_index || '/client/services'}
                        label="SERVICES"
                        value={serviceCount}
                        accentColor="border-b-[3.5px] border-b-[#0284c7]"
                        hoverBorder="hover:border-sky-300"
                        icon={<BoxIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 2. LICENSES */}
                    <StatTile
                        href={routes.licenses_index || '/client/licenses'}
                        label="LICENSES"
                        value={licenseCount}
                        accentColor="border-b-[3.5px] border-b-[#16a34a]"
                        hoverBorder="hover:border-emerald-300"
                        icon={<LicenseKeyIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 3. TICKETS */}
                    <StatTile
                        href={routes.support_index || '/client/support-tickets'}
                        label="TICKETS"
                        value={ticketOpenCount}
                        accentColor="border-b-[3.5px] border-b-[#dc2626]"
                        hoverBorder="hover:border-rose-300"
                        icon={<SpeechBubblesIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 4. INVOICES */}
                    <StatTile
                        href={routes.invoices_index || '/client/invoices'}
                        label="INVOICES"
                        value={displayInvoiceCount}
                        accentColor="border-b-[3.5px] border-b-[#ea580c]"
                        hoverBorder="hover:border-amber-300"
                        icon={<CreditCardIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 5. PROJECTS (shown when projects exist) */}
                    {projectCount > 0 && (
                        <StatTile
                            href={routes.projects_index || '/client/projects'}
                            label="PROJECTS"
                            value={projectCount}
                            accentColor="border-b-[3.5px] border-b-sky-600"
                            hoverBorder="hover:border-sky-300"
                            icon={<FolderProjectsIcon />}
                            valueColor="text-[#0e5077]"
                        />
                    )}
                </div>

                {/* ── Two-Column 50% / 50% Grid: Tasks Overview & Active Services ── */}
                <div className="grid gap-4 sm:gap-6 lg:grid-cols-2 items-start">
                    <TaskStrip
                        showTasksWidget={showTasksWidget}
                        taskSummary={taskSummary || {}}
                        openTasks={openTasks}
                        inProgressTasks={inProgressTasks}
                    />
                    <ServicesList subscriptions={subscriptions} routes={routes} />
                </div>

                {/* Additional Projects / Maintenance (if any) */}
                {(projects.length > 0 || maintenanceRenewal) && (
                    <div className="grid gap-4 sm:gap-6 lg:grid-cols-2 items-start">
                        {projects.length > 0 && <ProjectsList projects={projects} routes={routes} />}
                        {maintenanceRenewal && <MaintenanceCard maintenanceRenewal={maintenanceRenewal} routes={routes} />}
                    </div>
                )}

            </div>
        </>
    );
}
