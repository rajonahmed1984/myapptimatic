import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500',
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
};

export default function Index({
    pageTitle = 'All Projects',
    projects = [],
    statuses = [],
    types = [],
    filters = {},
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const statusOptions = [
        { value: '', label: 'All' },
        ...statuses.map((status) => ({
            value: String(status),
            label: status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        })),
    ];
    const typeOptions = [
        { value: '', label: 'All' },
        ...types.map((type) => ({
            value: String(type),
            label: type.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase()),
        })),
    ];

    return (
        <>
            <Head title={pageTitle} />

            <div className="card space-y-4 p-6">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b-1">
                    <form method="GET" action={routes?.index} data-native="true" className="grid gap-3 p-2 md:grid-cols-4">
                        <div>
                            <label className="text-xs text-slate-500">Status</label>
                            <SearchableSelect
                                name="status"
                                defaultValue={String(filters?.status ?? '')}
                                options={statusOptions}
                                className="mt-1"
                                placeholder="All"
                            />
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Type</label>
                            <SearchableSelect
                                name="type"
                                defaultValue={String(filters?.type ?? '')}
                                options={typeOptions}
                                className="mt-1"
                                placeholder="All"
                            />
                        </div>
                        <div className="self-end">
                            <button
                                type="submit"
                                className={`w-full ${BTN.secondary}`}
                            >
                                Apply filters
                            </button>
                        </div>
                    </form>

                    <div className="flex items-center gap-2">
                        <a
                            href={routes?.create}
                            data-native="true"
                            className={BTN.primary}
                        >
                            New project
                        </a>
                    </div>
                </div>

                <DataTable
                    rows={projects}
                    emptyMessage="No projects found."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (project) => `#${project.id}` },
                        {
                            key: 'project',
                            header: 'Project',
                            render: (project) => (
                                <>
                                    <a href={project.routes?.show} data-native="true" className="font-semibold text-slate-900 hover:text-teal-700 block">{project.name}</a>
                                    {project.sales_reps?.length > 0 ? (
                                        <div className="mt-0.5 text-xs text-slate-500"><span className="font-medium">Sales: </span>{project.sales_reps.join(', ')}</div>
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            key: 'customer',
                            header: 'Customer',
                            render: (project) => (
                                <>
                                    <div className="font-medium text-slate-800">{project.customer_name}</div>
                                    {project.customer_company ? <div className="text-xs text-slate-500">{project.customer_company}</div> : null}
                                </>
                            ),
                        },
                        { key: 'type', header: 'Type', render: (project) => project.type_label },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (project) => <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${project.status_class}`}>{project.status_label}</span>,
                        },
                        { key: 'due', header: 'Due', cellClassName: 'text-sm text-slate-600', render: (project) => project.due_date },
                        {
                            key: 'tasks',
                            header: 'Tasks',
                            headerClassName: 'text-right',
                            cellClassName: (project) => `text-right text-sm ${project.tasks?.has_open_work ? 'bg-amber-50 font-semibold text-amber-700' : 'text-slate-600'}`,
                            render: (project) => project.tasks?.done_label ?? '--',
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right text-sm',
                            render: (project) => (
                                <div className="inline-flex items-center gap-2">
                                    <a href={project.routes?.show} data-native="true" className="font-semibold text-slate-700 hover:text-teal-700">View</a>
                                    <a href={project.routes?.edit} data-native="true" className="font-semibold text-slate-700 hover:text-teal-700">Edit</a>
                                    <form
                                        method="POST"
                                        action={project.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm(`Delete project ${project.name}? This is permanent.`)) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="font-semibold text-rose-600 hover:text-rose-700">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(project) => (
                        <MobileCard
                            title={<a href={project.routes?.show} data-native="true" className="hover:text-teal-600">{project.name}</a>}
                            subtitle={`#${project.id} · ${project.customer_name}`}
                            badge={project.status_label}
                            badgeColor={project.tasks?.has_open_work ? 'bg-amber-100 text-amber-700' : undefined}
                            metrics={[
                                { label: 'Type', value: project.type_label },
                                { label: 'Tasks', value: project.tasks?.done_label ?? '--', tone: project.tasks?.has_open_work ? 'text-amber-700' : undefined },
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
                                        onSubmit={(event) => { if (!window.confirm(`Delete project ${project.name}? This is permanent.`)) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        >
                            <div className="text-xs text-slate-500">Due: {project.due_date || '--'}</div>
                        </MobileCard>
                    )}
                />

                {pagination?.has_pages ? (
                    <div className="mt-2 flex items-center justify-end gap-2 text-sm">
                        {pagination.previous_url ? (
                            <a
                                href={pagination.previous_url}
                                data-native="true"
                                className={BTN.secondary}
                            >
                                Previous
                            </a>
                        ) : (
                            <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                        )}

                        {pagination.next_url ? (
                            <a
                                href={pagination.next_url}
                                data-native="true"
                                className={BTN.secondary}
                            >
                                Next
                            </a>
                        ) : (
                            <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
