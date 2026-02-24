import React from 'react';
import { Head, usePage } from '@inertiajs/react';

function TaskWidget({ showTasksWidget, taskSummary = {}, openTasks = [], inProgressTasks = [] }) {
    const { csrf_token: csrfToken } = usePage().props;
    if (!showTasksWidget) return null;

    return (
        <div className="card p-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Tasks</div>
                    <div className="text-sm text-slate-500">Quick access to your task backlog.</div>
                </div>
                <a href="/client/tasks" data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                    View all
                </a>
            </div>

            <div className="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Open</div>
                    <div className="mt-1 text-lg font-semibold text-slate-900">{taskSummary.open || 0}</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Inprogress</div>
                    <div className="mt-1 text-lg font-semibold text-amber-600">{taskSummary.in_progress || 0}</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</div>
                    <div className="mt-1 text-lg font-semibold text-emerald-600">{taskSummary.completed || 0}</div>
                </div>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">My Open Tasks</div>
                    <div className="mt-3 space-y-3 text-sm">
                        {openTasks.length === 0 ? (
                            <div className="text-sm text-slate-500">No open tasks right now.</div>
                        ) : (
                            openTasks.map((task) => (
                                <div key={task.id} className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-semibold text-slate-900">{task.title}</div>
                                            {task.project ? (
                                                <a href={task.project.route_show} data-native="true" className="text-xs text-slate-500 hover:text-teal-600">
                                                    {task.project.name}
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                        {task.project ? (
                                            <a href={task.project.route_task_show} data-native="true" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                                Open
                                            </a>
                                        ) : null}
                                        {task.can_start && task.project ? (
                                            <form method="POST" action={task.project.route_task_update} data-native="true">
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="PATCH" />
                                                <input type="hidden" name="status" value="in_progress" />
                                                <button type="submit" className="rounded-full border border-amber-200 px-2 py-0.5 text-[11px] font-semibold text-amber-700 hover:border-amber-300">
                                                    Inprogress
                                                </button>
                                            </form>
                                        ) : null}
                                        {task.can_complete && task.project ? (
                                            <form method="POST" action={task.project.route_task_update} data-native="true">
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="PATCH" />
                                                <input type="hidden" name="status" value="completed" />
                                                <button type="submit" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                                    Complete
                                                </button>
                                            </form>
                                        ) : null}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                <div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">My Inprogress Tasks</div>
                    <div className="mt-3 space-y-3 text-sm">
                        {inProgressTasks.length === 0 ? (
                            <div className="text-sm text-slate-500">No tasks in Inprogress.</div>
                        ) : (
                            inProgressTasks.map((task) => (
                                <div key={task.id} className="rounded-2xl border border-slate-200 bg-white/80 p-3">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-semibold text-slate-900">{task.title}</div>
                                            {task.project ? (
                                                <a href={task.project.route_show} data-native="true" className="text-xs text-slate-500 hover:text-teal-600">
                                                    {task.project.name}
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                        {task.project ? (
                                            <a href={task.project.route_task_show} data-native="true" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                                Open
                                            </a>
                                        ) : null}
                                        {task.can_complete && task.project ? (
                                            <form method="POST" action={task.project.route_task_update} data-native="true">
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="PATCH" />
                                                <input type="hidden" name="status" value="completed" />
                                                <button type="submit" className="rounded-full border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 hover:border-emerald-300">
                                                    Complete
                                                </button>
                                            </form>
                                        ) : null}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

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
            <Head title="Client Dashboard" />

            <div className="space-y-8">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div className="mt-2 text-3xl font-semibold text-slate-900">Welcome Back, {customer?.name || 'Client'}</div>
                        <div className="mt-1 text-sm text-slate-500">{customer?.email || ''}</div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={routes.orders_index} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">
                            Place an order
                        </a>
                        <a href={routes.support_create} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Open ticket
                        </a>
                    </div>
                </div>

                <div className="h-px bg-slate-200/80" />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <a href={routes.licenses_index} data-native="true" className="card block p-4 transition hover:border-teal-300 hover:shadow-sm">
                        <div className="text-2xl font-semibold text-slate-900">{serviceCount}</div>
                        <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Services</div>
                    </a>
                    <a href={routes.projects_index} data-native="true" className="card block p-4 transition hover:border-sky-300 hover:shadow-sm">
                        <div className="text-2xl font-semibold text-slate-900">{projectCount || 0}</div>
                        <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Projects</div>
                    </a>
                    <a href={routes.support_index} data-native="true" className="card block p-4 transition hover:border-amber-300 hover:shadow-sm">
                        <div className="text-2xl font-semibold text-slate-900">{ticketOpenCount}</div>
                        <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Tickets</div>
                    </a>
                    <a href={routes.invoices_index} data-native="true" className="card block p-4 transition hover:border-rose-300 hover:shadow-sm">
                        <div className="text-2xl font-semibold text-slate-900">{openInvoiceCount}</div>
                        <div className="text-xs uppercase tracking-[0.25em] text-slate-400">Invoices</div>
                    </a>
                </div>

                <TaskWidget showTasksWidget={showTasksWidget} taskSummary={taskSummary || {}} openTasks={openTasks} inProgressTasks={inProgressTasks} />

                <div className="grid gap-4 md:grid-cols-3">
                    <div className={`card p-4 ${maintenanceRenewal ? 'md:col-span-2' : 'md:col-span-3'}`}>
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="section-label">Projects</div>
                                <div className="text-sm text-slate-500">Software & website work in progress</div>
                            </div>
                            <a href={routes.support_create} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-teal-600">
                                Request update
                            </a>
                        </div>
                        <div className="mt-4 space-y-3">
                            {projects.length === 0 ? (
                                <div className="text-sm text-slate-500">No ongoing projects right now.</div>
                            ) : (
                                projects.map((project) => (
                                    <a key={project.id} href={project.routes.show} data-native="true" className="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-teal-300 hover:shadow-sm">
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <div className="text-sm text-slate-500">Project</div>
                                                <div className="text-lg font-semibold text-slate-900">{project.name}</div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    Tasks: {project.done_tasks_count}/{project.total_tasks_count} done
                                                </div>
                                            </div>
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">
                                                {project.status_label}
                                            </span>
                                        </div>
                                    </a>
                                ))
                            )}
                        </div>
                    </div>
                    {maintenanceRenewal ? (
                        <div className="card p-4">
                            <div className="section-label">Maintenance</div>
                            <div className="mt-2 text-sm text-slate-600">
                                Next renewal: {maintenanceRenewal.next_renewal_display}
                                <br />
                                Plan: {maintenanceRenewal.plan}
                                {maintenanceRenewal.project_name ? (
                                    <>
                                        <br />
                                        Project: {maintenanceRenewal.project_name}
                                    </>
                                ) : null}
                            </div>
                            <div className="mt-4">
                                <a href={routes.invoices_index} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    View invoices
                                </a>
                            </div>
                        </div>
                    ) : null}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-6">
                        <div className="card p-6">
                            <div className="flex items-center justify-between">
                                <div className="section-label">Your Active Products/Services</div>
                                <a href={routes.licenses_index} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    My services
                                </a>
                            </div>
                            <div className="mt-4 space-y-3">
                                {subscriptions.length === 0 ? (
                                    <div className="text-sm text-slate-500">
                                        You do not have any active services yet.{' '}
                                        <a href={routes.orders_index} data-native="true" className="font-semibold text-teal-600 hover:text-teal-500">
                                            Place an order
                                        </a>{' '}
                                        to get started.
                                    </div>
                                ) : (
                                    subscriptions.map((subscription) => (
                                        <div key={subscription.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                            <div className="flex flex-wrap items-center justify-between gap-4">
                                                <div>
                                                    <div className="text-sm text-slate-500">{subscription.product_name}</div>
                                                    <div className="text-lg font-semibold text-slate-900">{subscription.plan_name}</div>
                                                    <div className="mt-2 text-xs text-slate-500">Next invoice {subscription.next_invoice_display}</div>
                                                </div>
                                                <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">
                                                    {subscription.status_label}
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>

                        <div className="card p-6">
                            <div className="flex items-center justify-between">
                                <div className="section-label">Outstanding invoices</div>
                                <a href={routes.invoices_index} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                                    View all
                                </a>
                            </div>
                            <div className="mt-4 text-sm text-slate-600">
                                {openInvoiceCount > 0 ? (
                                    <>
                                        You have {openInvoiceCount} unpaid/overdue invoice(s) with a total balance due of {currency}{' '}
                                        {Number(openInvoiceBalance).toFixed(2)}.
                                        {nextOpenInvoice ? (
                                            <div className="mt-3 flex items-center gap-3 text-xs text-slate-600">
                                                <span>Next due: {nextOpenInvoice.due_date_display}</span>
                                                <a href={nextOpenInvoice.route_pay} data-native="true" className="rounded-full bg-rose-500 px-3 py-1 text-[11px] font-semibold text-white">
                                                    Pay now
                                                </a>
                                            </div>
                                        ) : null}
                                    </>
                                ) : (
                                    <>No outstanding invoices. You are all caught up.</>
                                )}
                            </div>
                        </div>

                        <div className="card p-6">
                            <div className="flex items-center justify-between">
                                <div className="section-label">Recent support tickets</div>
                                <a href={routes.support_create} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    Open new ticket
                                </a>
                            </div>
                            <div className="mt-4 space-y-3 text-sm">
                                {recentTickets.length === 0 ? (
                                    <div className="text-sm text-slate-500">No recent tickets. Need help? Open a ticket anytime.</div>
                                ) : (
                                    recentTickets.map((ticket) => (
                                        <div key={ticket.id} className="flex items-center justify-between rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <div>
                                                <div className="font-semibold text-slate-900">{ticket.subject}</div>
                                                <div className="text-xs text-slate-500">Updated {ticket.updated_at_display}</div>
                                            </div>
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                                {ticket.status_label}
                                            </span>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="card p-6">
                            <div className="flex items-center justify-between">
                                <div className="section-label">Domains expiring soon</div>
                                <a href={routes.orders_index} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                                    Renew now
                                </a>
                            </div>
                            <div className="mt-4 text-sm text-slate-600">
                                {expiringLicenses.length > 0 ? (
                                    <>
                                        You have {expiringLicenses.length} license(s) expiring within 45 days.
                                        <div className="mt-3 space-y-2 text-xs text-slate-500">
                                            {expiringLicenses.map((license) => (
                                                <div key={license.id} className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-3 py-2">
                                                    <span>{license.product_name}</span>
                                                    <span>Expires {license.expires_at_display}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                ) : (
                                    <>No expiring licenses in the next 45 days.</>
                                )}
                            </div>
                        </div>

                        <div id="invoices" className="card p-6">
                            <div className="flex items-center justify-between">
                                <div className="section-label">Recent invoices</div>
                                <a href={routes.invoices_index} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                                    View all
                                </a>
                            </div>
                            <div className="mt-4 space-y-3">
                                {invoices.length === 0 ? (
                                    <div className="text-sm text-slate-500">No invoices yet.</div>
                                ) : (
                                    invoices.map((invoice) => (
                                        <div key={invoice.id} className="rounded-2xl border border-slate-200 bg-white p-4">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <div className="text-sm text-slate-500">Invoice {invoice.number}</div>
                                                    <div className="text-lg font-semibold text-slate-900">
                                                        {invoice.currency} {invoice.total}
                                                    </div>
                                                    <div className="mt-2 text-xs text-slate-500">Due {invoice.due_date_display}</div>
                                                </div>
                                                <div className="text-right text-sm text-slate-600">
                                                    <div>{invoice.status_label}</div>
                                                    {invoice.can_pay ? (
                                                        <div className="mt-2">
                                                            <a href={invoice.routes.pay} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                                Pay now
                                                            </a>
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
