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

const statusFilters = [
    { key: '', label: 'All' },
    { key: 'open', label: 'Open' },
    { key: 'in_progress', label: 'Inprogress' },
    { key: 'completed', label: 'Completed' },
];

export default function Index({
    pageTitle = 'Tasks',
    status_filter = '',
    search = '',
    status_counts = {},
    tasks = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const countFor = (key) => {
        if (key === '') return Number(status_counts.total || 0);
        if (key === 'open') return Number(status_counts.open || 0);
        if (key === 'in_progress') return Number(status_counts.in_progress || 0);
        if (key === 'completed') return Number(status_counts.completed || 0);
        return 0;
    };

    const buildFilterUrl = (status) => {
        if (!routes?.index) return '#';
        const params = new URLSearchParams();
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        const query = params.toString();
        return query ? `${routes.index}?${query}` : routes.index;
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Tasks</div>
                        <div className="text-sm text-slate-500">All tasks you are allowed to see.</div>
                    </div>
                    <div className="flex items-center gap-3 text-xs font-semibold">
                        <a href={routes?.projects} data-native="true" className="text-slate-500 hover:text-teal-600">
                            Projects
                        </a>
                        <a href={routes?.index} data-native="true" className="text-teal-600 hover:text-teal-500">
                            Reset filters
                        </a>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2 text-xs">
                        {statusFilters.map((filter) => {
                            const active = status_filter === filter.key || (!status_filter && filter.key === '');
                            return (
                                <a
                                    key={filter.key || 'all'}
                                    href={buildFilterUrl(filter.key)}
                                    data-native="true"
                                    className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 font-semibold ${active ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200 hover:text-teal-600'}`}
                                >
                                    <span>{filter.label}</span>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{countFor(filter.key)}</span>
                                </a>
                            );
                        })}
                    </div>
                    <form method="GET" action={routes?.index} className="flex items-center gap-2" data-native="true">
                        {status_filter ? <input type="hidden" name="status" value={status_filter} /> : null}
                        <input
                            type="text"
                            name="search"
                            defaultValue={search}
                            placeholder="Search tasks"
                            className="w-48 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600 focus:border-teal-300 focus:outline-none"
                        />
                        <button type="submit" className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Search
                        </button>
                    </form>
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
                                        No tasks found.
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
                                                    {task.routes?.task_show ? (
                                                        <a href={task.routes.task_show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                            {task.title}
                                                        </a>
                                                    ) : (
                                                        task.title
                                                    )}
                                                </div>
                                                {task.description ? <div className="mt-1 text-xs text-slate-500">{task.description}</div> : null}
                                                {task.project?.name && task.routes?.project_show ? (
                                                    <a href={task.routes.project_show} data-native="true" className="mt-1 inline-block text-xs text-slate-500 hover:text-teal-600">
                                                        {task.project.name}
                                                    </a>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{task.creator_name || 'System'}</td>
                                            <td className="px-4 py-3">
                                                <span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass}`}>
                                                    {statusLabel}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex flex-col items-end gap-2 text-xs font-semibold">
                                                    {task.routes?.task_show ? (
                                                        <a
                                                            href={task.routes.task_show}
                                                            data-native="true"
                                                            className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                        >
                                                            Open Task
                                                        </a>
                                                    ) : null}
                                                    {task.can_start && task.routes?.task_update && !isInProgress && !isCompleted ? (
                                                        <form method="POST" action={task.routes.task_update} data-native="true">
                                                            <input type="hidden" name="_token" value={csrf} />
                                                            <input type="hidden" name="_method" value="PATCH" />
                                                            <input type="hidden" name="status" value="in_progress" />
                                                            <button
                                                                type="submit"
                                                                className="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300"
                                                            >
                                                                Inprogress
                                                            </button>
                                                        </form>
                                                    ) : null}
                                                    {task.can_complete && task.routes?.task_update && !isCompleted ? (
                                                        <form method="POST" action={task.routes.task_update} data-native="true">
                                                            <input type="hidden" name="_token" value={csrf} />
                                                            <input type="hidden" name="_method" value="PATCH" />
                                                            <input type="hidden" name="status" value="completed" />
                                                            <button
                                                                type="submit"
                                                                className="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                            >
                                                                Complete
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

                <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                    <a
                        href={pagination.prev_page_url || '#'}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 ${pagination.prev_page_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Previous
                    </a>
                    <a
                        href={pagination.next_page_url || '#'}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 ${pagination.next_page_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Next
                    </a>
                </div>
            </div>
        </>
    );
}
