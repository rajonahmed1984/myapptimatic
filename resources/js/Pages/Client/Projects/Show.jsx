import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const STATUS_LABELS = {
    pending: 'To Do',
    todo: 'To Do',
    in_progress: 'In Progress',
    blocked: 'Blocked',
    completed: 'Completed',
    done: 'Completed',
};

const STATUS_CLASSES = {
    pending: 'bg-slate-100 text-slate-600',
    todo: 'bg-slate-100 text-slate-600',
    in_progress: 'bg-amber-100 text-amber-700',
    blocked: 'bg-rose-100 text-rose-700',
    completed: 'bg-emerald-100 text-emerald-700',
    done: 'bg-emerald-100 text-emerald-700',
};

const BOARD_COLUMNS = [
    { key: 'pending', label: 'To Do' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'blocked', label: 'Blocked' },
    { key: 'completed', label: 'Completed' },
];

const normalizeStatus = (status) => {
    if (status === 'todo') {
        return 'pending';
    }
    if (status === 'done') {
        return 'completed';
    }
    return status || 'pending';
};

const subtasksLabel = (task) => {
    const total = Number(task.subtasks_count || 0);
    if (total <= 0) {
        return null;
    }
    const completed = Number(task.completed_subtasks_count || 0);
    return `${completed}/${total} subtasks`;
};

export default function Show({
    project = {},
    tasks = [],
    maintenances = [],
    initial_invoice = null,
    financials = {},
    taskTypeOptions = {},
    priorityOptions = {},
    task_stats = {},
    is_project_specific_user = false,
    routes = {},
}) {
    const { csrf_token: csrfToken } = usePage().props;
    const currencyCode = project.currency || '';
    const [taskViewMode, setTaskViewMode] = React.useState('list');

    const groupedTasks = React.useMemo(() => {
        const groups = {
            pending: [],
            in_progress: [],
            blocked: [],
            completed: [],
        };

        tasks.forEach((task) => {
            const normalized = normalizeStatus(task.status);
            if (!groups[normalized]) {
                groups.pending.push(task);
                return;
            }
            groups[normalized].push(task);
        });

        return groups;
    }, [tasks]);

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

            <div className="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div className="text-2xl font-semibold text-slate-900">{task_stats?.total || 0}</div>
                    <div className="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div className="text-2xl font-semibold text-slate-900">{task_stats?.in_progress || 0}</div>
                    <div className="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div className="text-2xl font-semibold text-slate-900">{task_stats?.completed || 0}</div>
                    <div className="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div className="text-2xl font-semibold text-slate-900">{task_stats?.unread || 0}</div>
                    <div className="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div>
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

                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Tasks (customer-visible)</div>
                                <div className="text-xs text-slate-500">Switch between list and board preview.</div>
                            </div>
                            <div className="inline-flex items-center rounded-full border border-slate-200 bg-white p-1">
                                <button
                                    type="button"
                                    onClick={() => setTaskViewMode('list')}
                                    className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                                        taskViewMode === 'list' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    List
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setTaskViewMode('board')}
                                    className={`rounded-full px-3 py-1 text-xs font-semibold transition ${
                                        taskViewMode === 'board' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900'
                                    }`}
                                >
                                    Board
                                </button>
                            </div>
                        </div>

                        {tasks.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                No tasks found.
                            </div>
                        ) : taskViewMode === 'list' ? (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-left text-sm">
                                    <thead>
                                        <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            <th className="px-3 py-2">Task</th>
                                            <th className="px-3 py-2">Dates</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tasks.map((task) => {
                                            const currentStatus = normalizeStatus(task.status);
                                            const statusLabel = STATUS_LABELS[currentStatus] || 'To Do';
                                            const statusClass = STATUS_CLASSES[currentStatus] || STATUS_CLASSES.pending;
                                            const taskSubtasks = subtasksLabel(task);

                                            return (
                                                <tr key={task.id} className="border-t border-slate-100 align-top">
                                                    <td className="px-3 py-2">
                                                        <div className="font-semibold text-slate-900">
                                                            <a href={task.routes.show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                                {task.title}
                                                            </a>
                                                        </div>
                                                        <div className="mt-1 text-xs font-semibold text-slate-600">Task ID: {task.id ?? '--'}</div>
                                                        <div className="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                                            {taskTypeOptions[task.task_type] || task.task_type}
                                                        </div>
                                                        {task.description ? <div className="text-xs text-slate-500">{task.description}</div> : null}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-slate-600">
                                                        Start: {task.start_date_display}
                                                        <br />
                                                        Due: {task.due_date_display}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs text-slate-600">
                                                        <span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass}`}>
                                                            {statusLabel}
                                                        </span>
                                                        <div className="mt-2">Progress: {task.progress || 0}%</div>
                                                        {taskSubtasks ? <div className="mt-1">{taskSubtasks}</div> : null}
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
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="overflow-x-auto pb-2">
                                <div className="grid min-w-[920px] grid-cols-4 gap-4">
                                    {BOARD_COLUMNS.map((column) => (
                                        <div key={column.key} className="rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                                            <div className="mb-3 flex items-center justify-between">
                                                <span className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{column.label}</span>
                                                <span className="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-600">
                                                    {(groupedTasks[column.key] || []).length}
                                                </span>
                                            </div>
                                            <div className="space-y-3">
                                                {(groupedTasks[column.key] || []).length === 0 ? (
                                                    <div className="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-4 text-center text-xs text-slate-400">
                                                        No tasks
                                                    </div>
                                                ) : (
                                                    (groupedTasks[column.key] || []).map((task) => {
                                                        const currentStatus = normalizeStatus(task.status);
                                                        const statusLabel = STATUS_LABELS[currentStatus] || 'To Do';
                                                        const statusClass = STATUS_CLASSES[currentStatus] || STATUS_CLASSES.pending;
                                                        const taskSubtasks = subtasksLabel(task);

                                                        return (
                                                            <div key={task.id} className="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                                                                <a
                                                                    href={task.routes.show}
                                                                    data-native="true"
                                                                    className="line-clamp-2 text-sm font-semibold text-slate-900 hover:text-teal-700"
                                                                >
                                                                    {task.title}
                                                                </a>
                                                                <div className="mt-1 text-xs font-semibold text-slate-600">Task ID: {task.id ?? '--'}</div>
                                                                <div className="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-400">
                                                                    {taskTypeOptions[task.task_type] || task.task_type}
                                                                </div>
                                                                <div className="mt-2">
                                                                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ${statusClass}`}>
                                                                        {statusLabel}
                                                                    </span>
                                                                </div>
                                                                <div className="mt-2 text-xs text-slate-500">
                                                                    Start: {task.start_date_display}
                                                                    <br />
                                                                    Due: {task.due_date_display}
                                                                </div>
                                                                <div className="mt-2 text-xs text-slate-500">Progress: {task.progress || 0}%</div>
                                                                {taskSubtasks ? <div className="mt-1 text-xs text-slate-500">{taskSubtasks}</div> : null}
                                                                <div className="mt-3 flex items-center gap-2 text-xs font-semibold">
                                                                    <a href={task.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                                        Open
                                                                    </a>
                                                                    {task.can_edit && task.routes.edit_anchor ? (
                                                                        <a href={task.routes.edit_anchor} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                                            Edit
                                                                        </a>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        );
                                                    })
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
