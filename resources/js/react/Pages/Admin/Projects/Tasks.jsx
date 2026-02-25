import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const STATUS_LABELS = {
    pending: 'Open',
    todo: 'Open',
    in_progress: 'Inprogress',
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

export default function Tasks({
    pageTitle,
    project,
    summary = {},
    statusFilter = null,
    projectChatUnreadCount = 0,
    canCreateTask = false,
    taskCreateUrl = '',
    statusUrls = {},
    tasks = [],
    pagination = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const totalTasks = Math.max(0, Number(summary.total || 0));
    const completedTasks = Math.max(0, Number(summary.completed || 0));
    const completionPercent = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;
    const statusFilterLabel =
        statusFilter === 'in_progress'
            ? 'Inprogress'
            : statusFilter === 'completed'
                ? 'Completed'
                : statusFilter
                    ? statusFilter.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())
                    : 'All';

    return (
        <>
            <Head title={pageTitle || `Project #${project?.id || ''} Tasks`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Delivery</div>
                    <div className="text-2xl font-semibold text-slate-900">{project?.name}</div>
                    <div className="text-sm text-slate-500">
                        Status: {String(project?.status || '').replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <a
                        href={project?.routes?.show}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                    >
                        Back
                    </a>
                    <a
                        href={project?.routes?.chat}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                    >
                        Chat
                        <span
                            className={`ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                Number(projectChatUnreadCount) > 0 ? 'bg-rose-600 text-white' : 'bg-slate-200 text-slate-600'
                            }`}
                        >
                            {Number(projectChatUnreadCount)}
                        </span>
                    </a>
                    <a
                        href={project?.routes?.edit}
                        data-native="true"
                        className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                    >
                        Edit
                    </a>
                </div>
            </div>

            <div className="space-y-4">
                {canCreateTask ? (
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                                <div className="text-xs text-slate-500">Create a new task using the dedicated task form page.</div>
                            </div>
                            <a
                                href={taskCreateUrl}
                                data-native="true"
                                className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            >
                                + Add Task
                            </a>
                        </div>
                    </div>
                ) : null}

                <div id="projectTaskStats" className="grid gap-3 md:grid-cols-5">
                    <a
                        href={statusUrls?.all}
                        className={`rounded-2xl border px-4 py-3 transition ${statusFilter === null ? 'border-teal-300 bg-teal-50 ring-1 ring-teal-200' : 'border-slate-200 bg-white hover:border-teal-200'}`}
                    >
                        <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Total</div>
                        <div className="mt-1 text-lg font-semibold text-slate-900">{totalTasks}</div>
                    </a>
                    <a
                        href={statusUrls?.pending}
                        className={`rounded-2xl border px-4 py-3 transition ${statusFilter === 'pending' ? 'border-amber-300 bg-amber-100 ring-1 ring-amber-200' : 'border-amber-200 bg-amber-50 hover:border-amber-300'}`}
                    >
                        <div className="text-[11px] uppercase tracking-[0.2em] text-amber-600">Pending</div>
                        <div className="mt-1 text-lg font-semibold text-amber-900">{Number(summary.pending || 0)}</div>
                    </a>
                    <a
                        href={statusUrls?.in_progress}
                        className={`rounded-2xl border px-4 py-3 transition ${statusFilter === 'in_progress' ? 'border-sky-300 bg-sky-100 ring-1 ring-sky-200' : 'border-sky-200 bg-sky-50 hover:border-sky-300'}`}
                    >
                        <div className="text-[11px] uppercase tracking-[0.2em] text-sky-600">In Progress</div>
                        <div className="mt-1 text-lg font-semibold text-sky-900">{Number(summary.in_progress || 0)}</div>
                    </a>
                    <a
                        href={statusUrls?.blocked}
                        className={`rounded-2xl border px-4 py-3 transition ${statusFilter === 'blocked' ? 'border-rose-300 bg-rose-100 ring-1 ring-rose-200' : 'border-rose-200 bg-rose-50 hover:border-rose-300'}`}
                    >
                        <div className="text-[11px] uppercase tracking-[0.2em] text-rose-600">Blocked</div>
                        <div className="mt-1 text-lg font-semibold text-rose-900">{Number(summary.blocked || 0)}</div>
                    </a>
                    <a
                        href={statusUrls?.completed}
                        className={`rounded-2xl border px-4 py-3 transition ${statusFilter === 'completed' ? 'border-emerald-300 bg-emerald-100 ring-1 ring-emerald-200' : 'border-emerald-200 bg-emerald-50 hover:border-emerald-300'}`}
                    >
                        <div className="text-[11px] uppercase tracking-[0.2em] text-emerald-600">Complete</div>
                        <div className="mt-1 flex items-center justify-between gap-3">
                            <span className="text-lg font-semibold text-emerald-900">{completedTasks}</span>
                            <span className="text-xs font-semibold text-emerald-700">{completionPercent}%</span>
                        </div>
                    </a>
                </div>

                <div id="tasksTableWrap" className="card p-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div className="section-label">Tasks</div>
                            <div className="text-sm text-slate-500">Tasks for this project. Filter: {statusFilterLabel}</div>
                        </div>
                    </div>

                    <div className="mt-6 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Task ID</th>
                                    <th className="px-4 py-3">Created</th>
                                    <th className="px-4 py-3">Project Task</th>
                                    <th className="px-4 py-3">Created By</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {tasks.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-6 text-center text-slate-500">
                                            {statusFilter ? 'No tasks found for this status.' : 'No tasks found.'}
                                        </td>
                                    </tr>
                                ) : (
                                    tasks.map((task) => {
                                        const currentStatus = String(task.status || 'pending');
                                        const statusLabel =
                                            STATUS_LABELS[currentStatus] ||
                                            currentStatus.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                                        const statusClass = STATUS_CLASSES[currentStatus] || 'bg-slate-100 text-slate-600';
                                        const isInProgress = currentStatus === 'in_progress';
                                        const isCompleted = ['completed', 'done'].includes(currentStatus);

                                        return (
                                            <tr key={task.id} className="align-top">
                                                <td className="whitespace-nowrap px-4 py-3 font-semibold text-slate-600">{task.id ?? '--'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-slate-500">
                                                    <div className="whitespace-nowrap">{task.created_at_date || '--'}</div>
                                                    <div className="whitespace-nowrap text-xs text-slate-400">{task.created_at_time || '--'}</div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-slate-900">
                                                        <a href={task.routes?.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                            {task.title}
                                                        </a>
                                                    </div>
                                                    {task.description ? <div className="mt-1 whitespace-pre-line text-xs text-slate-500">{task.description}</div> : null}
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {task.task_type_label} | Assignee: {task.assignee_names} | Progress: {Number(task.progress || 0)}%
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">{task.creator_name}</td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass}`}>
                                                        {statusLabel}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex flex-col items-end gap-2 text-xs font-semibold">
                                                        <a
                                                            href={task.routes?.show}
                                                            data-native="true"
                                                            className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                        >
                                                            Open Task
                                                        </a>

                                                        {task.can_update ? (
                                                            <>
                                                                <a
                                                                    href={task.routes?.edit}
                                                                    data-native="true"
                                                                    className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-slate-300"
                                                                >
                                                                    Edit
                                                                </a>

                                                                {statusFilter !== 'in_progress' && !isInProgress && !isCompleted ? (
                                                                    <form method="POST" action={task.routes?.change_status} data-native="true">
                                                                        <input type="hidden" name="_token" value={csrf} />
                                                                        <input type="hidden" name="_method" value="PATCH" />
                                                                        <input type="hidden" name="task_status_filter" value={statusFilter || ''} />
                                                                        <input type="hidden" name="status" value="in_progress" />
                                                                        <input type="hidden" name="progress" value="50" />
                                                                        <button
                                                                            type="submit"
                                                                            className="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300"
                                                                        >
                                                                            Inprogress
                                                                        </button>
                                                                    </form>
                                                                ) : null}

                                                                {statusFilter !== 'completed' && !isCompleted ? (
                                                                    <form method="POST" action={task.routes?.change_status} data-native="true">
                                                                        <input type="hidden" name="_token" value={csrf} />
                                                                        <input type="hidden" name="_method" value="PATCH" />
                                                                        <input type="hidden" name="task_status_filter" value={statusFilter || ''} />
                                                                        <input type="hidden" name="status" value="completed" />
                                                                        <input type="hidden" name="progress" value="100" />
                                                                        <button
                                                                            type="submit"
                                                                            className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                                        >
                                                                            Complete
                                                                        </button>
                                                                    </form>
                                                                ) : null}
                                                            </>
                                                        ) : null}

                                                        {task.can_delete ? (
                                                            <form
                                                                method="POST"
                                                                action={task.routes?.destroy}
                                                                data-native="true"
                                                                onSubmit={(event) => {
                                                                    if (!window.confirm('Delete this task?')) {
                                                                        event.preventDefault();
                                                                    }
                                                                }}
                                                            >
                                                                <input type="hidden" name="_token" value={csrf} />
                                                                <input type="hidden" name="_method" value="DELETE" />
                                                                <input type="hidden" name="task_status_filter" value={statusFilter || ''} />
                                                                <button
                                                                    type="submit"
                                                                    className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:border-rose-300"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {pagination?.has_pages ? (
                        <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                            <a
                                href={pagination.previous_url || '#'}
                                data-native="true"
                                className={`rounded-full border px-3 py-1 ${pagination.previous_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                            >
                                Previous
                            </a>
                            <a
                                href={pagination.next_url || '#'}
                                data-native="true"
                                className={`rounded-full border px-3 py-1 ${pagination.next_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                            >
                                Next
                            </a>
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
