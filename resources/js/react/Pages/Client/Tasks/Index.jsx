import React from 'react';
import { Head, usePage } from '@inertiajs/react';

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

export default function Index({ status_filter = '', search = '', status_counts = {}, tasks = [], pagination = {}, routes = {} }) {
    const { csrf_token: csrfToken } = usePage().props;
    const filters = [
        { key: '', label: 'All', count: status_counts.total || 0 },
        { key: 'open', label: 'Open', count: status_counts.open || 0 },
        { key: 'in_progress', label: 'Inprogress', count: status_counts.in_progress || 0 },
        { key: 'completed', label: 'Completed', count: status_counts.completed || 0 },
    ];

    return (
        <>
            <Head title="Tasks" />

            <div id="tasksIndex" className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Tasks</div>
                        <div className="text-sm text-slate-500">All tasks you are allowed to see.</div>
                    </div>
                    <div className="flex items-center gap-3 text-xs font-semibold">
                        <a href={routes.projects} data-native="true" className="text-slate-500 hover:text-teal-600">
                            Projects
                        </a>
                        <a href={routes.index} data-native="true" className="text-teal-600 hover:text-teal-500">
                            Reset filters
                        </a>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2 text-xs">
                        {filters.map((filter) => {
                            const isActive = status_filter === filter.key || (filter.key === '' && status_filter === '');
                            const query = new URLSearchParams();
                            if (filter.key !== '') query.set('status', filter.key);
                            if (search !== '') query.set('search', search);
                            const href = query.toString() ? `${routes.index}?${query.toString()}` : routes.index;

                            return (
                                <a
                                    key={filter.key || 'all'}
                                    href={href}
                                    data-native="true"
                                    className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 font-semibold ${
                                        isActive
                                            ? 'border-teal-300 bg-teal-50 text-teal-700'
                                            : 'border-slate-200 text-slate-600 hover:border-teal-200 hover:text-teal-600'
                                    }`}
                                >
                                    <span>{filter.label}</span>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{filter.count}</span>
                                </a>
                            );
                        })}
                    </div>

                    <form method="GET" action={routes.index} data-native="true" className="flex items-center gap-2">
                        {status_filter !== '' ? <input type="hidden" name="status" value={status_filter} /> : null}
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
                                <th className="px-4 py-3">Created</th>
                                <th className="px-4 py-3">Project Task</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {tasks.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-slate-500">
                                        No tasks found.
                                    </td>
                                </tr>
                            ) : (
                                tasks.map((task) => {
                                    const isInProgress = task.status === 'in_progress';
                                    const isCompleted = task.status === 'completed' || task.status === 'done';

                                    return (
                                        <tr key={task.id} className="align-top">
                                            <td className="whitespace-nowrap px-4 py-3 text-slate-500">
                                                <div>{task.created_date}</div>
                                                <div className="text-xs text-slate-400">{task.created_time}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900">
                                                    {task.project ? (
                                                        <a href={task.project.routes.task_show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                            {task.title}
                                                        </a>
                                                    ) : (
                                                        task.title
                                                    )}
                                                </div>
                                                {task.description ? <div className="mt-1 text-xs text-slate-500">{task.description}</div> : null}
                                                {task.project ? (
                                                    <a href={task.project.routes.show} data-native="true" className="text-xs text-slate-500 hover:text-teal-600">
                                                        {task.project.name}
                                                    </a>
                                                ) : null}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3">
                                                <span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass(task.status)}`}>
                                                    {task.status_label}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 text-right">
                                                <div className="flex flex-col items-end gap-2 text-xs font-semibold">
                                                    {task.project ? (
                                                        <a
                                                            href={task.project.routes.task_show}
                                                            data-native="true"
                                                            className="whitespace-nowrap rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
                                                        >
                                                            Open Task
                                                        </a>
                                                    ) : null}
                                                    {task.can_start && task.project && status_filter !== 'in_progress' && !isInProgress && !isCompleted ? (
                                                        <form method="POST" action={task.project.routes.task_update} data-native="true">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <input type="hidden" name="_method" value="PATCH" />
                                                            <input type="hidden" name="status" value="in_progress" />
                                                            <button
                                                                type="submit"
                                                                className="whitespace-nowrap rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300"
                                                            >
                                                                Inprogress
                                                            </button>
                                                        </form>
                                                    ) : null}
                                                    {task.can_complete && task.project && status_filter !== 'completed' && !isCompleted ? (
                                                        <form method="POST" action={task.project.routes.task_update} data-native="true">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <input type="hidden" name="_method" value="PATCH" />
                                                            <input type="hidden" name="status" value="completed" />
                                                            <button
                                                                type="submit"
                                                                className="whitespace-nowrap rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
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

                {pagination.last_page > 1 ? (
                    <div className="mt-4 flex items-center justify-between text-xs">
                        <span className="text-slate-500">
                            Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}
                        </span>
                        <div className="flex items-center gap-2">
                            {pagination.prev_page_url ? (
                                <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                    Previous
                                </a>
                            ) : null}
                            {pagination.next_page_url ? (
                                <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                    Next
                                </a>
                            ) : null}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
