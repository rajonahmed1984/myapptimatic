import React, { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import Pagination from '../../../Components/Table/Pagination';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3.5 py-1.5 font-semibold text-white hover:bg-teal-500 shadow-sm transition',
    secondary: 'border border-slate-300 rounded-full text-xs px-3.5 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600 transition',
};

export default function Index({
    pageTitle = 'All Projects',
    projects = [],
    statuses = [],
    types = [],
    filters = {},
    search = '',
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const [searchTerm, setSearchTerm] = useState(String(search || filters?.search || ''));
    const isFirstRender = useRef(true);

    const statusOptions = [
        { value: '', label: 'All Statuses' },
        ...statuses.map((status) => ({
            value: String(status),
            label: status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        })),
    ];

    const typeOptions = [
        { value: '', label: 'All Types' },
        ...types.map((type) => ({
            value: String(type),
            label: type.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        })),
    ];

    useEffect(() => {
        setSearchTerm(String(search || filters?.search || ''));
    }, [search, filters?.search]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const current = String(searchTerm || '').trim();
        const server = String(search || filters?.search || '').trim();
        if (current === server) {
            return;
        }

        const timeout = window.setTimeout(() => {
            const params = {};
            if (current) params.search = current;
            if (filters?.status) params.status = filters.status;
            if (filters?.type) params.type = filters.type;

            router.get(
                routes?.index || '/admin/projects',
                params,
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [searchTerm, search, filters?.search, filters?.status, filters?.type, routes?.index]);

    const handleFilterChange = (key, value) => {
        const params = {};
        const currentSearch = String(searchTerm || '').trim();
        if (currentSearch) params.search = currentSearch;

        const nextStatus = key === 'status' ? value : filters?.status;
        const nextType = key === 'type' ? value : filters?.type;

        if (nextStatus) params.status = nextStatus;
        if (nextType) params.type = nextType;

        router.get(routes?.index || '/admin/projects', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hasActiveFilters = Boolean(
        String(searchTerm || '').trim() || filters?.status || filters?.type
    );

    const handleResetFilters = () => {
        setSearchTerm('');
        router.get(routes?.index || '/admin/projects', {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                        <input
                            type="text"
                            name="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search projects..."
                            className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />

                        <select
                            value={String(filters?.status ?? '')}
                            onChange={(event) => handleFilterChange('status', event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            aria-label="Filter by Status"
                        >
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>

                        <select
                            value={String(filters?.type ?? '')}
                            onChange={(event) => handleFilterChange('type', event.target.value)}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            aria-label="Filter by Type"
                        >
                            {typeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>

                        {hasActiveFilters && (
                            <button
                                type="button"
                                onClick={handleResetFilters}
                                className="text-xs font-medium text-slate-500 hover:text-rose-600 transition"
                            >
                                Reset
                            </button>
                        )}

                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            {Number(pagination?.total || 0)} total
                        </span>
                    </div>

                    <a
                        href={routes?.create}
                        data-native="true"
                        className={BTN.primary}
                    >
                        New project
                    </a>
                </div>

                <DataTable
                    framed={false}
                    rows={projects}
                    emptyMessage="No projects found."
                    columns={[
                        {
                            key: 'id',
                            header: 'ID',
                            render: (project) => (
                                <a
                                    href={project.routes?.show}
                                    data-native="true"
                                    className="font-semibold text-slate-700 hover:text-teal-600"
                                >
                                    #{project.id}
                                </a>
                            ),
                        },
                        {
                            key: 'project',
                            header: 'Project Info',
                            render: (project) => (
                                <div className="space-y-0.5 min-w-[170px]">
                                    <a
                                        href={project.routes?.show}
                                        data-native="true"
                                        className="font-semibold text-slate-900 hover:text-teal-600 block"
                                    >
                                        {project.name}
                                    </a>
                                    <div className="flex items-center gap-1.5 flex-wrap text-xs text-slate-500">
                                        <span className="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600">
                                            {project.type_label}
                                        </span>
                                        {project.sales_reps?.length > 0 && (
                                            <span>· Sales: {project.sales_reps.join(', ')}</span>
                                        )}
                                    </div>
                                </div>
                            ),
                        },
                        {
                            key: 'customer',
                            header: 'Customer',
                            render: (project) => (
                                <div className="space-y-0.5 min-w-[140px]">
                                    <div className="font-semibold text-slate-900">
                                        {project.customer_show_route ? (
                                            <a
                                                href={project.customer_show_route}
                                                data-native="true"
                                                className="hover:text-teal-600"
                                            >
                                                {project.customer_name}
                                            </a>
                                        ) : (
                                            <span>{project.customer_name}</span>
                                        )}
                                    </div>
                                    {project.customer_company ? (
                                        <div className="text-xs text-slate-500">{project.customer_company}</div>
                                    ) : null}
                                    {project.customer_email ? (
                                        <div className="text-xs text-slate-400">{project.customer_email}</div>
                                    ) : null}
                                </div>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (project) => (
                                <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${project.status_class || 'bg-slate-100 text-slate-700 ring-slate-200'}`}>
                                    {project.status_label}
                                </span>
                            ),
                        },
                        {
                            key: 'due',
                            header: 'Due Date',
                            render: (project) => (
                                <span className="text-xs text-slate-600 whitespace-nowrap font-medium">
                                    {project.due_date}
                                </span>
                            ),
                        },
                        {
                            key: 'tasks',
                            header: 'Tasks',
                            render: (project) => (
                                <span className={`inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-medium ${
                                    project.tasks?.has_open_work
                                        ? 'bg-amber-50 text-amber-700 border border-amber-200/60 font-semibold'
                                        : 'bg-slate-50 text-slate-600 border border-slate-200/60'
                                }`}>
                                    {project.tasks?.done_label ?? '--'}
                                </span>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (project) => (
                                <div className="inline-flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a
                                        href={project.routes?.show}
                                        data-native="true"
                                        className="text-xs font-semibold text-slate-600 hover:text-teal-600 transition"
                                    >
                                        View
                                    </a>
                                    <span className="text-slate-300">·</span>
                                    <a
                                        href={project.routes?.edit}
                                        data-native="true"
                                        className="text-xs font-semibold text-slate-600 hover:text-teal-600 transition"
                                    >
                                        Edit
                                    </a>
                                    <span className="text-slate-300">·</span>
                                    <form
                                        method="POST"
                                        action={project.routes?.destroy}
                                        data-native="true"
                                        className="inline"
                                        onSubmit={(event) => {
                                            if (!window.confirm(`Delete project "${project.name}"? This is permanent.`)) {
                                                event.preventDefault();
                                            }
                                        }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            className="text-xs font-semibold text-rose-600 hover:text-rose-700 transition"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(project) => (
                        <MobileCard
                            title={
                                <a href={project.routes?.show} data-native="true" className="hover:text-teal-600">
                                    {project.name}
                                </a>
                            }
                            subtitle={`#${project.id} · ${project.customer_name}${project.customer_company ? ` (${project.customer_company})` : ''}`}
                            badge={project.status_label}
                            badgeColor={project.tasks?.has_open_work ? 'bg-amber-100 text-amber-700' : undefined}
                            metrics={[
                                { label: 'Type', value: project.type_label },
                                {
                                    label: 'Tasks',
                                    value: project.tasks?.done_label ?? '--',
                                    tone: project.tasks?.has_open_work ? 'text-amber-700' : undefined,
                                },
                                { label: 'Due', value: project.due_date || '--' },
                            ]}
                            actions={
                                <>
                                    <a
                                        href={project.routes?.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={project.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action={project.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => {
                                            if (!window.confirm(`Delete project "${project.name}"? This is permanent.`)) {
                                                event.preventDefault();
                                            }
                                        }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </>
                            }
                        />
                    )}
                />

                <Pagination
                    pagination={pagination}
                    label="projects"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
