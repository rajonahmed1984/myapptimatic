import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

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
    task_insights = {},
    tasks = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const searchExtras = React.useMemo(() => (status_filter ? { status: status_filter } : null), [status_filter]);
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
        extraData: searchExtras,
    });

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
        if (searchTerm.trim()) params.set('search', searchTerm.trim());
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
                        <a href={routes?.projects} data-native="true" className="whitespace-nowrap text-slate-500 hover:text-teal-600">
                            Projects
                        </a>
                        <a href={routes?.index} data-native="true" className="whitespace-nowrap text-teal-600 hover:text-teal-500">
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
                                    className={`inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-3 py-1 font-semibold ${active ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200 hover:text-teal-600'}`}
                                >
                                    <span>{filter.label}</span>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{countFor(filter.key)}</span>
                                </a>
                            );
                        })}
                    </div>
                    <form
                        method="GET"
                        action={routes?.index}
                        className="flex items-center gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        {status_filter ? <input type="hidden" name="status" value={status_filter} /> : null}
                        <input
                            type="text"
                            name="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search tasks"
                            className="w-48 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600 focus:border-teal-300 focus:outline-none"
                        />
                        <button type="submit" className="whitespace-nowrap rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Search
                        </button>
                    </form>
                </div>

                <div className="mt-5 grid gap-4 xl:grid-cols-3">
                    <SummaryCard
                        title="Overall Progress"
                        percent={Number(task_insights?.overview?.completion_rate || 0)}
                        tone="teal"
                        stats={[
                            { label: 'Work Items', value: Number(task_insights?.overview?.total_items || 0) },
                            { label: 'Completed', value: Number(task_insights?.overview?.completed_items || 0) },
                            { label: 'Overdue', value: Number(task_insights?.overview?.overdue_items || 0), tone: 'rose' },
                        ]}
                    />
                    <SummaryCard
                        title="Tasks Snapshot"
                        percent={Number(task_insights?.tasks?.completion_rate || 0)}
                        tone="amber"
                        stats={[
                            { label: 'Total', value: Number(task_insights?.tasks?.total || 0) },
                            { label: 'Open', value: Number(task_insights?.tasks?.open || 0) },
                            { label: 'Inprogress', value: Number(task_insights?.tasks?.in_progress || 0), tone: 'amber' },
                            { label: 'Blocked', value: Number(task_insights?.tasks?.blocked || 0), tone: 'rose' },
                            { label: 'Completed', value: Number(task_insights?.tasks?.completed || 0), tone: 'emerald' },
                            { label: 'Overdue', value: Number(task_insights?.tasks?.overdue || 0), tone: 'rose' },
                        ]}
                    />
                    <SummaryCard
                        title="Subtasks Snapshot"
                        percent={Number(task_insights?.subtasks?.completion_rate || 0)}
                        tone="sky"
                        stats={[
                            { label: 'Total', value: Number(task_insights?.subtasks?.total || 0) },
                            { label: 'Open', value: Number(task_insights?.subtasks?.open || 0) },
                            { label: 'Completed', value: Number(task_insights?.subtasks?.completed || 0), tone: 'emerald' },
                            { label: 'Overdue', value: Number(task_insights?.subtasks?.overdue || 0), tone: 'rose' },
                        ]}
                    />
                </div>

                <div className="mt-6 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Task ID &amp; Created</th>
                                <th className="px-4 py-3">Project Task &amp; Created By</th>
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
                                    const currentStatus = String(task.status || 'pending');
                                    const statusLabel =
                                        STATUS_LABELS[currentStatus] ||
                                        currentStatus.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                                    const statusClass = STATUS_CLASSES[currentStatus] || 'bg-slate-100 text-slate-600';
                                    const isInProgress = currentStatus === 'in_progress';
                                    const isCompleted = ['completed', 'done'].includes(currentStatus);

                                    return (
                                        <tr key={task.id} className="align-top">
                                            <td className="px-4 py-3 text-slate-500">
                                                <div className="font-semibold text-slate-700">{task.id ?? '--'}</div>
                                                <div className="mt-1 whitespace-nowrap text-xs text-slate-500">{task.created_at_date || '--'}</div>
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
                                                {task.project?.name && task.routes?.project_show ? (
                                                    <a href={task.routes.project_show} data-native="true" className="mt-1 inline-block text-xs text-slate-500 hover:text-teal-600">
                                                        {task.project.name}
                                                    </a>
                                                ) : null}
                                                <div className="mt-2 text-xs text-slate-500">
                                                    Created by <span className="font-medium text-slate-700">{task.creator_name || 'System'}</span>
                                                </div>
                                            </td>
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
                                                            className="whitespace-nowrap rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300"
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
                                                                className="whitespace-nowrap rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300"
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

                <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                    <a
                        href={pagination.prev_page_url || '#'}
                        data-native="true"
                        className={`whitespace-nowrap rounded-full border px-3 py-1 ${pagination.prev_page_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Previous
                    </a>
                    <a
                        href={pagination.next_page_url || '#'}
                        data-native="true"
                        className={`whitespace-nowrap rounded-full border px-3 py-1 ${pagination.next_page_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Next
                    </a>
                </div>
            </div>
        </>
    );
}

function SummaryCard({ title, percent = 0, tone = 'teal', stats = [] }) {
    const progressWidth = Math.max(0, Math.min(100, Number(percent || 0)));
    const progressClass =
        {
            teal: 'bg-teal-500',
            amber: 'bg-amber-500',
            sky: 'bg-sky-500',
        }[tone] || 'bg-teal-500';

    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
            <div className="flex items-center justify-between gap-3">
                <div className="text-sm font-semibold text-slate-800">{title}</div>
                <div className="text-sm font-semibold text-slate-700">{progressWidth}%</div>
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-200">
                <div className={`h-full rounded-full ${progressClass}`} style={{ width: `${progressWidth}%` }} />
            </div>
            <div className="mt-4 flex flex-wrap gap-2">
                {stats.map((stat) => (
                    <SummaryStat
                        key={stat.label}
                        label={stat.label}
                        value={stat.value}
                        tone={stat.tone}
                    />
                ))}
            </div>
        </div>
    );
}

function SummaryStat({ label, value, tone = 'slate' }) {
    const toneClass =
        {
            slate: 'border-slate-200 bg-white text-slate-700',
            teal: 'border-teal-200 bg-teal-50 text-teal-700',
            amber: 'border-amber-200 bg-amber-50 text-amber-700',
            emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            rose: 'border-rose-200 bg-rose-50 text-rose-700',
            sky: 'border-sky-200 bg-sky-50 text-sky-700',
        }[tone] || 'border-slate-200 bg-white text-slate-700';

    return (
        <div className={`rounded-xl border px-3 py-2 ${toneClass}`}>
            <div className="text-[11px] uppercase tracking-[0.18em] opacity-70">{label}</div>
            <div className="mt-1 text-base font-semibold">{value}</div>
        </div>
    );
}
