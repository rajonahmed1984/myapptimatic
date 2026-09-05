import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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

const query = (base, params) => {
    const url = new URL(base, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, value);
        }
    });
    return `${url.pathname}${url.search}`;
};

export default function Index({ status_filter = '', search = '', status_counts = {}, tasks = [], pagination = {}, routes = {} }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const searchExtras = React.useMemo(() => (status_filter ? { status: status_filter } : null), [status_filter]);
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
        extraData: searchExtras,
    });
    const filters = [
        { key: '', label: 'All', count: status_counts?.total || 0 },
        { key: 'open', label: 'Open', count: status_counts?.open || 0 },
        { key: 'in_progress', label: 'In Progress', count: status_counts?.in_progress || 0 },
        { key: 'completed', label: 'Completed', count: status_counts?.completed || 0 },
    ];

    return (
        <>
            <Head title="Tasks" />

            <div className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Tasks</div>
                        <div className="text-sm text-slate-500">All tasks you are allowed to see.</div>
                    </div>
                    <div className="flex items-center gap-3 text-xs font-semibold">
                        <a href={routes?.projects_index} data-native="true" className="text-slate-500 hover:text-teal-600">Projects</a>
                        <a href={routes?.index} data-native="true" className="text-teal-600 hover:text-teal-500">Reset</a>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2 text-xs">
                        {filters.map((filter) => {
                            const active = status_filter === filter.key || (!status_filter && filter.key === '');
                            return (
                                <a
                                    key={filter.key || 'all'}
                                    href={query(routes?.index || '/sales/tasks', { status: filter.key, search: searchTerm.trim() })}
                                    data-native="true"
                                    className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 font-semibold ${active ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200 hover:text-teal-600'}`}
                                >
                                    <span>{filter.label}</span>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-[10px] text-slate-500">{filter.count}</span>
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
                        <input type="text" name="search" value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder="Search tasks" className="w-48 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600" />
                        <button type="submit" className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">Search</button>
                    </form>
                </div>

                <div className="mt-6">
                    <DataTable
                        rows={tasks}
                        emptyMessage="No tasks found."
                        columns={[
                            { key: 'id', header: 'Task ID', cellClassName: 'font-semibold text-slate-600', render: (task) => task.id ?? '--' },
                            {
                                key: 'created',
                                header: 'Created',
                                cellClassName: 'text-slate-500',
                                render: (task) => (
                                    <>
                                        <div>{task.created_at_date || '--'}</div>
                                        <div className="text-xs text-slate-400">{task.created_at_time || '--'}</div>
                                    </>
                                ),
                            },
                            {
                                key: 'task',
                                header: 'Project Task',
                                render: (task) => (
                                    <>
                                        <div className="font-semibold text-slate-900">
                                            <a href={task?.routes?.task_show} data-native="true" className="text-teal-600 hover:text-teal-500">{task.title}</a>
                                        </div>
                                        {task.description ? <div className="mt-1 text-xs text-slate-500">{task.description}</div> : null}
                                        {task.project ? <a href={task?.routes?.project_show} data-native="true" className="text-xs text-slate-500 hover:text-teal-600">{task.project.name}</a> : '--'}
                                    </>
                                ),
                            },
                            {
                                key: 'status',
                                header: 'Status',
                                render: (task) => (
                                    <span className={`inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ${statusClass(task.status)}`}>
                                        {statusLabel(task.status)}
                                    </span>
                                ),
                            },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (task) => {
                                    const currentStatus = String(task?.status || '').toLowerCase();
                                    const isInProgress = currentStatus === 'in_progress';
                                    const isCompleted = ['completed', 'done'].includes(currentStatus);
                                    return (
                                        <div className="flex flex-col items-end gap-2 text-xs font-semibold">
                                            <a href={task?.routes?.task_show} data-native="true" className="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700">Open Task</a>
                                            {task.can_start && !isInProgress ? (
                                                <form method="POST" action={task?.routes?.task_update} data-native="true">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <input type="hidden" name="_method" value="PATCH" />
                                                    <input type="hidden" name="status" value="in_progress" />
                                                    <button type="submit" className="rounded-full border border-amber-200 px-3 py-1 text-amber-700">In Progress</button>
                                                </form>
                                            ) : null}
                                            {task.can_complete && !isCompleted ? (
                                                <form method="POST" action={task?.routes?.task_update} data-native="true">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <input type="hidden" name="_method" value="PATCH" />
                                                    <input type="hidden" name="status" value="completed" />
                                                    <button type="submit" className="rounded-full border border-emerald-200 px-3 py-1 text-emerald-700">Complete</button>
                                                </form>
                                            ) : null}
                                        </div>
                                    );
                                },
                            },
                        ]}
                        renderMobileCard={(task) => {
                            const currentStatus = String(task?.status || '').toLowerCase();
                            const isInProgress = currentStatus === 'in_progress';
                            const isCompleted = ['completed', 'done'].includes(currentStatus);
                            return (
                                <MobileCard
                                    title={<a href={task?.routes?.task_show} data-native="true" className="hover:text-teal-600">{task.title}</a>}
                                    subtitle={task.project ? task.project.name : null}
                                    badge={statusLabel(task.status)}
                                    badgeColor={statusClass(task.status)}
                                    metrics={[
                                        { label: 'Created', value: task.created_at_date || '--' },
                                        { label: 'ID', value: task.id ?? '--' },
                                    ]}
                                    actions={
                                        <div className="flex flex-wrap gap-2 w-full">
                                            <a
                                                href={task?.routes?.task_show}
                                                data-native="true"
                                                className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                            >
                                                Open Task
                                            </a>
                                            {task.can_start && !isInProgress ? (
                                                <form method="POST" action={task?.routes?.task_update} data-native="true" className="flex-1">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <input type="hidden" name="_method" value="PATCH" />
                                                    <input type="hidden" name="status" value="in_progress" />
                                                    <button type="submit" className="w-full py-2 px-3 rounded-xl border border-amber-200 bg-amber-50 text-xs font-bold text-amber-700 hover:bg-amber-100 transition active:scale-95">In Progress</button>
                                                </form>
                                            ) : null}
                                            {task.can_complete && !isCompleted ? (
                                                <form method="POST" action={task?.routes?.task_update} data-native="true" className="flex-1">
                                                    <input type="hidden" name="_token" value={csrfToken} />
                                                    <input type="hidden" name="_method" value="PATCH" />
                                                    <input type="hidden" name="status" value="completed" />
                                                    <button type="submit" className="w-full py-2 px-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition active:scale-95">Complete</button>
                                                </form>
                                            ) : null}
                                        </div>
                                    }
                                >
                                    {task.description ? <div className="text-xs text-slate-500">{task.description}</div> : null}
                                </MobileCard>
                            );
                        }}
                    />
                </div>

                {pagination?.last_page > 1 ? (
                    <div className="mt-4 flex items-center justify-between text-xs">
                        <span className="text-slate-500">Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}</span>
                        <div className="flex items-center gap-2">
                            {pagination.prev_page_url ? <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Previous</a> : null}
                            {pagination.next_page_url ? <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Next</a> : null}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
