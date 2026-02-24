import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({
    project = {},
    tasks = [],
    maintenances = [],
    initial_invoice = null,
    financials = {},
    taskTypeOptions = {},
    priorityOptions = {},
    is_project_specific_user = false,
    routes = {},
}) {
    const { csrf_token: csrfToken } = usePage().props;
    const currencyCode = project.currency || '';

    return (
        <>
            <Head title={`Project #${project.id || ''}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Project</div>
                    <div className="text-2xl font-semibold text-slate-900">{project.name}</div>
                    <div className="text-sm text-slate-500">Status: {project.status_label}</div>
                </div>
            </div>

            <div className="grid gap-6">
                <div className="card p-6">
                    <div className="grid gap-4 text-sm text-slate-700 md:grid-cols-2">
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Project ID & Dates</div>
                            <div className="mt-2 font-semibold text-slate-900">#{project.id}</div>
                            <div className="mt-2 text-sm text-slate-700">
                                Start: {project.start_date_display}
                                <br />
                                Expected end: {project.expected_end_date_display}
                                <br />
                                Due: {project.due_date_display}
                            </div>
                        </div>

                        {!is_project_specific_user ? (
                            <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div>
                                <div className="mt-2 text-sm text-slate-700">
                                    Budget:{' '}
                                    {financials.budget !== null && financials.budget !== undefined
                                        ? `${currencyCode} ${Number(financials.budget).toFixed(2)}`
                                        : '--'}
                                    <br />
                                    Initial payment:{' '}
                                    {financials.initial_payment !== null && financials.initial_payment !== undefined
                                        ? `${currencyCode} ${Number(financials.initial_payment).toFixed(2)}`
                                        : '--'}
                                    <br />
                                    Total overhead: {currencyCode} {Number(financials.overhead_total || 0).toFixed(2)}
                                    <br />
                                    Budget with overhead:{' '}
                                    {financials.budget_with_overhead !== null && financials.budget_with_overhead !== undefined
                                        ? `${currencyCode} ${Number(financials.budget_with_overhead).toFixed(2)}`
                                        : '--'}
                                </div>
                                {initial_invoice ? (
                                    <div className="mt-2 text-xs text-slate-500">
                                        Initial invoice: {initial_invoice.label} ({initial_invoice.status_label}){' '}
                                        <a href={initial_invoice.route} data-native="true" className="text-teal-700 hover:text-teal-600">
                                            View invoice
                                        </a>
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                    </div>

                    {maintenances.length > 0 && !is_project_specific_user ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="mb-2 text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance</div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Title</th>
                                            <th className="px-3 py-2">Cycle</th>
                                            <th className="px-3 py-2">Next Billing</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2 text-right">Amount</th>
                                            <th className="px-3 py-2 text-right">Invoices</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {maintenances.map((maintenance) => (
                                            <tr key={maintenance.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2">{maintenance.title}</td>
                                                <td className="px-3 py-2">{maintenance.billing_cycle_label}</td>
                                                <td className="px-3 py-2 text-xs text-slate-600">{maintenance.next_billing_date_display}</td>
                                                <td className="px-3 py-2">
                                                    <span
                                                        className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${
                                                            maintenance.status === 'active'
                                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                                : maintenance.status === 'paused'
                                                                  ? 'border-amber-200 bg-amber-50 text-amber-700'
                                                                  : 'border-slate-200 bg-slate-50 text-slate-600'
                                                        }`}
                                                    >
                                                        {maintenance.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-right">{maintenance.amount_label}</td>
                                                <td className="px-3 py-2 text-right text-xs text-slate-600">
                                                    {maintenance.invoice_count}
                                                    {maintenance.latest_invoice ? (
                                                        <div>
                                                            <a href={maintenance.latest_invoice.route} data-native="true" className="text-teal-700 hover:text-teal-600">
                                                                Latest {maintenance.latest_invoice.label}
                                                            </a>
                                                        </div>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}

                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                                <div className="text-xs text-slate-500">Dates and assignment are managed internally and locked after creation.</div>
                            </div>
                        </div>
                        <form method="POST" action={routes.task_store} encType="multipart/form-data" data-native="true" className="mt-4 grid gap-3 md:grid-cols-3">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div className="md:col-span-2">
                                <label className="text-xs text-slate-500">Title</label>
                                <input name="title" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-1">
                                <label className="text-xs text-slate-500">Task type</label>
                                <select name="task_type" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    {Object.entries(taskTypeOptions).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-3">
                                <label className="text-xs text-slate-500">Description</label>
                                <input name="description" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-1">
                                <label className="text-xs text-slate-500">Priority</label>
                                <select name="priority" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                                    {Object.entries(priorityOptions).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-1">
                                <label className="text-xs text-slate-500">Attachment (required for Upload type)</label>
                                <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" className="mt-1 w-full text-xs text-slate-600" />
                            </div>
                            <div className="md:col-span-3 flex justify-end">
                                <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                    Add task
                                </button>
                            </div>
                        </form>
                    </div>

                    {tasks.length > 0 ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="mb-2 text-xs uppercase tracking-[0.2em] text-slate-400">Tasks (customer-visible)</div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Task</th>
                                            <th className="px-3 py-2">Dates</th>
                                            <th className="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tasks.map((task) => (
                                            <tr key={task.id} className="border-t border-slate-100 align-top">
                                                <td className="px-3 py-2">
                                                    <div className="font-semibold text-slate-900">
                                                        <a href={task.routes.show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                            {task.title}
                                                        </a>
                                                    </div>
                                                    <div className="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">{taskTypeOptions[task.task_type] || task.task_type}</div>
                                                    {task.description ? <div className="text-xs text-slate-500">{task.description}</div> : null}
                                                </td>
                                                <td className="px-3 py-2 text-xs text-slate-600">
                                                    Start: {task.start_date_display}
                                                    <br />
                                                    Due: {task.due_date_display}
                                                </td>
                                                <td className="px-3 py-2 text-right align-top">
                                                    <a href={task.routes.show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                        Open Task
                                                    </a>
                                                    {task.can_edit ? (
                                                        <>
                                                            <span className="mx-2 text-slate-300">|</span>
                                                            <a href={task.routes.edit_anchor} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                                Edit
                                                            </a>
                                                        </>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
