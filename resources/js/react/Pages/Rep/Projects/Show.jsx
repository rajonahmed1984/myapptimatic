import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({
    project = {},
    tasks = [],
    maintenances = [],
    initial_invoice = null,
    task_type_options = {},
    priority_options = {},
    sales_rep_amount = null,
    task_stats = {},
    routes = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={`Project #${project?.id || ''}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Delivery</div>
                    <div className="text-2xl font-semibold text-slate-900">{project?.name}</div>
                    <div className="text-sm text-slate-500">Status: {project?.status_label}</div>
                </div>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.total || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.in_progress || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.completed || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.unread || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div></div>
            </div>

            <div className="grid gap-6">
                <div className="card p-6">
                    <div className="grid gap-4 text-sm text-slate-700 md:grid-cols-3">
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div><div className="mt-2 font-semibold text-slate-900">{project.customer_name}</div><div className="text-xs text-slate-500">Project ID: {project.id}</div></div>
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div><div className="mt-2">Start: {project.start_date_display}<br />Expected end: {project.expected_end_date_display}<br />Due: {project.due_date_display}</div></div>
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div><div className="mt-2">Budget: {project.budget ? `${project.currency} ${project.budget}` : '--'}<br />Sales rep amount: {sales_rep_amount !== null ? `${project.currency} ${Number(sales_rep_amount).toFixed(2)}` : '--'}<br />Initial payment: {project.initial_payment_amount ? `${project.currency} ${project.initial_payment_amount}` : '--'}</div>{initial_invoice ? <div className="mt-2 text-xs text-slate-500">Initial invoice: {initial_invoice.label} ({initial_invoice.status_label})</div> : null}</div>
                    </div>

                    {maintenances.length > 0 ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="mb-2 text-xs uppercase tracking-[0.2em] text-slate-400">Maintenance</div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead><tr className="text-xs uppercase tracking-[0.2em] text-slate-500"><th className="px-3 py-2">Title</th><th className="px-3 py-2">Cycle</th><th className="px-3 py-2">Next Billing</th><th className="px-3 py-2">Status</th><th className="px-3 py-2 text-right">Amount</th><th className="px-3 py-2 text-right">Invoices</th></tr></thead>
                                    <tbody>
                                        {maintenances.map((maintenance) => (
                                            <tr key={maintenance.id} className="border-t border-slate-100">
                                                <td className="px-3 py-2">{maintenance.title}</td>
                                                <td className="px-3 py-2">{maintenance.billing_cycle_label}</td>
                                                <td className="px-3 py-2 text-xs text-slate-600">{maintenance.next_billing_date_display}</td>
                                                <td className="px-3 py-2"><span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600">{maintenance.status_label}</span></td>
                                                <td className="px-3 py-2 text-right">{maintenance.currency} {Number(maintenance.amount || 0).toFixed(2)}</td>
                                                <td className="px-3 py-2 text-right">{maintenance.invoice_count}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : null}

                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <form method="POST" action={routes?.task_store} className="grid gap-3 md:grid-cols-6" encType="multipart/form-data" data-native="true">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <div className="md:col-span-3"><label className="text-xs text-slate-500">Title</label><input name="title" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-2"><label className="text-xs text-slate-500">Task type</label><select name="task_type" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>{Object.entries(task_type_options || {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                            <div className="md:col-span-1"><label className="text-xs text-slate-500">Priority</label><select name="priority" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{Object.entries(priority_options || {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                            <div className="md:col-span-6"><label className="text-xs text-slate-500">Description</label><input name="description" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-2"><label className="text-xs text-slate-500">Start date</label><input type="date" name="start_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-2"><label className="text-xs text-slate-500">Due date</label><input type="date" name="due_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                            <div className="md:col-span-2"><label className="text-xs text-slate-500">Attachment</label><input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" className="mt-1 w-full text-xs text-slate-600" /></div>
                            <div className="flex items-center gap-2"><input type="hidden" name="customer_visible" value="0" /><input type="checkbox" name="customer_visible" value="1" /><span className="text-xs text-slate-600">Customer visible</span></div>
                            <div className="md:col-span-6 flex justify-end"><button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button></div>
                        </form>
                    </div>

                    {tasks.length > 0 ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="mb-2 text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead><tr className="text-xs uppercase tracking-[0.2em] text-slate-500"><th className="px-3 py-2">Task</th><th className="px-3 py-2">Dates</th><th className="px-3 py-2">Progress</th><th className="px-3 py-2 text-right">Actions</th></tr></thead>
                                    <tbody>
                                        {tasks.map((task) => (
                                            <tr key={task.id} className="border-t border-slate-100 align-top">
                                                <td className="px-3 py-2"><div className="font-semibold text-slate-900">{task.title}</div><div className="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">{task_type_options?.[task.task_type] || task.task_type}</div>{task.description ? <div className="text-xs text-slate-500">{task.description}</div> : null}{task.customer_visible ? <div className="text-[11px] font-semibold text-emerald-600">Customer visible</div> : null}</td>
                                                <td className="px-3 py-2 text-xs text-slate-600">Start: {task.start_date_display}<br />Due: {task.due_date_display}</td>
                                                <td className="px-3 py-2 text-right text-xs text-slate-500">Progress: {task.progress || 0}%{task.completed_at_display ? <div>Completed at {task.completed_at_display}</div> : null}</td>
                                                <td className="px-3 py-2 text-right"><a href={task?.routes?.show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a></td>
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
