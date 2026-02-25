import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusLabel = (status) => {
    const map = {
        pending: 'Open',
        todo: 'Open',
        in_progress: 'In Progress',
        blocked: 'Blocked',
        completed: 'Completed',
        done: 'Completed',
    };
    return map[status] || status;
};

const statusClass = (status) => {
    const map = {
        pending: 'bg-slate-100 text-slate-600',
        todo: 'bg-slate-100 text-slate-600',
        in_progress: 'bg-amber-100 text-amber-700',
        blocked: 'bg-rose-100 text-rose-700',
        completed: 'bg-emerald-100 text-emerald-700',
        done: 'bg-emerald-100 text-emerald-700',
    };
    return map[status] || 'bg-slate-100 text-slate-600';
};

export default function Show({
    project = {},
    tasks = [],
    initial_invoice = null,
    task_type_options = {},
    priority_options = {},
    task_stats = {},
    permissions = {},
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
                <a href={routes?.chat} data-native="true" className="inline-flex items-center rounded-full border border-teal-200 bg-white px-4 py-2 text-xs font-semibold text-teal-700 hover:border-teal-300 hover:text-teal-600">
                    Open full chat
                </a>
            </div>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.total || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.in_progress || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.completed || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div></div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-2xl font-semibold text-slate-900">{task_stats?.unread || 0}</div><div className="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div></div>
            </div>

            <div className="space-y-6">
                <div className="card p-6">
                    <div className="grid gap-4 md:grid-cols-2 text-sm text-slate-700">
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                            <div className="mt-2">Start: {project?.start_date_display}<br />Expected end: {project?.expected_end_date_display}<br />Due: {project?.due_date_display}</div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</div>
                            <div className="mt-2"><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{project?.status_label}</span></div>
                            {initial_invoice ? <div className="mt-2 text-xs text-slate-500">Initial invoice: {initial_invoice.label} ({initial_invoice.status_label})</div> : null}
                        </div>
                    </div>

                    {permissions?.can_create_task ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                            <form method="POST" action={routes?.task_store} className="mt-4 grid gap-3 md:grid-cols-6" encType="multipart/form-data" data-native="true">
                                <input type="hidden" name="_token" value={csrfToken} />
                                <div className="md:col-span-4"><label className="text-xs text-slate-500">Title</label><input name="title" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                                <div className="md:col-span-2"><label className="text-xs text-slate-500">Task type</label><select name="task_type" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>{Object.entries(task_type_options || {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                                <div className="md:col-span-6"><label className="text-xs text-slate-500">Description</label><input name="description" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                                <div className="md:col-span-2"><label className="text-xs text-slate-500">Start date</label><input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                                <div className="md:col-span-2"><label className="text-xs text-slate-500">Due date</label><input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="due_date" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" /></div>
                                <div className="md:col-span-2"><label className="text-xs text-slate-500">Priority</label><select name="priority" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">{Object.entries(priority_options || {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>
                                <div className="md:col-span-2"><label className="text-xs text-slate-500">Attachment</label><input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" className="mt-1 w-full text-xs text-slate-600" /></div>
                                <div className="flex items-center gap-2"><input type="hidden" name="customer_visible" value="0" /><input type="checkbox" name="customer_visible" value="1" /><span className="text-xs text-slate-600">Customer visible</span></div>
                                <div className="md:col-span-6 flex justify-end"><button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button></div>
                            </form>
                        </div>
                    ) : null}

                    {tasks.length > 0 ? (
                        <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                            <div className="mb-2 text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Task</th>
                                            <th className="px-3 py-2">Dates</th>
                                            <th className="px-3 py-2">Progress</th>
                                            <th className="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tasks.map((task) => (
                                            <tr key={task.id} className="border-t border-slate-100 align-top">
                                                <td className="px-3 py-2">
                                                    <div className="font-semibold text-slate-900">{task.title}</div>
                                                    <div className="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">{task_type_options?.[task.task_type] || task.task_type}</div>
                                                    {task.description ? <div className="text-xs text-slate-500">{task.description}</div> : null}
                                                    {task.customer_visible ? <div className="text-[11px] font-semibold text-emerald-600">Customer visible</div> : null}
                                                </td>
                                                <td className="px-3 py-2 text-xs text-slate-600">Start: {task.start_date_display}<br />Due: {task.due_date_display}</td>
                                                <td className="px-3 py-2 text-right text-xs text-slate-500">
                                                    <div className="flex justify-end"><span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass(task.status)}`}>{statusLabel(task.status)}</span></div>
                                                    <div className="mt-2">Progress: {task.progress || 0}%</div>
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <div className="flex flex-wrap justify-end gap-2">
                                                        <a href={task?.routes?.show} data-native="true" className="inline-flex items-center rounded-full border border-teal-200 px-3 py-1 text-xs font-semibold text-teal-700">Open Task</a>
                                                        {task.can_start ? (
                                                            <form method="POST" action={task?.routes?.start} data-native="true">
                                                                <input type="hidden" name="_token" value={csrfToken} />
                                                                <input type="hidden" name="_method" value="PATCH" />
                                                                <button type="submit" className="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700">In Progress</button>
                                                            </form>
                                                        ) : null}
                                                        {task.can_complete ? (
                                                            <form method="POST" action={task?.routes?.update} data-native="true">
                                                                <input type="hidden" name="_token" value={csrfToken} />
                                                                <input type="hidden" name="_method" value="PATCH" />
                                                                <input type="hidden" name="status" value="completed" />
                                                                <button type="submit" className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">Complete</button>
                                                            </form>
                                                        ) : null}
                                                    </div>
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
