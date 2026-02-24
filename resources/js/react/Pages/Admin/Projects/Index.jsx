import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Projects',
    projects = [],
    statuses = [],
    types = [],
    filters = {},
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="card space-y-4 p-6">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-3 border-b-1">
                    <form method="GET" action={routes?.index} data-native="true" className="grid gap-3 p-2 md:grid-cols-4">
                        <div>
                            <label className="text-xs text-slate-500">Status</label>
                            <select
                                name="status"
                                defaultValue={filters?.status ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="">All</option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase())}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Type</label>
                            <select
                                name="type"
                                defaultValue={filters?.type ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="">All</option>
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase())}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="self-end">
                            <button
                                type="submit"
                                className="w-full rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                            >
                                Apply filters
                            </button>
                        </div>
                    </form>

                    <a
                        href={routes?.create}
                        data-native="true"
                        className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        New project
                    </a>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-300 bg-white/80">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm text-slate-700">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="w-16 px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Project</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Due</th>
                                    <th className="px-4 py-3 text-right">Tasks</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-6 text-center text-sm text-slate-500">
                                            No projects found.
                                        </td>
                                    </tr>
                                ) : (
                                    projects.map((project) => (
                                        <tr key={project.id} className="border-t border-slate-100 hover:bg-slate-50/80">
                                            <td className="px-4 py-3 font-semibold text-slate-900">#{project.id}</td>
                                            <td className="px-4 py-3">
                                                <a
                                                    href={project.routes?.show}
                                                    data-native="true"
                                                    className="font-semibold text-slate-900 hover:text-teal-700"
                                                >
                                                    {project.name}
                                                </a>
                                                {project.employees?.length > 0 || project.sales_reps?.length > 0 ? (
                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {project.employees?.length > 0 ? (
                                                            <div className="flex items-center gap-1">
                                                                <span className="font-medium">Employees:</span>
                                                                <span>{project.employees.join(', ')}</span>
                                                            </div>
                                                        ) : null}
                                                        {project.sales_reps?.length > 0 ? (
                                                            <div className="flex items-center gap-1">
                                                                <span className="font-medium">Sales:</span>
                                                                <span>{project.sales_reps.join(', ')}</span>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800">{project.customer_name}</div>
                                            </td>
                                            <td className="px-4 py-3">{project.type_label}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${project.status_class}`}
                                                >
                                                    {project.status_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-slate-600">{project.due_date}</td>
                                            <td
                                                className={`px-4 py-3 text-right text-sm ${
                                                    project.tasks?.has_open_work ? 'bg-amber-50 font-semibold text-amber-700' : 'text-slate-600'
                                                }`}
                                            >
                                                {project.tasks?.done_label ?? '--'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-sm">
                                                <div className="inline-flex items-center gap-2">
                                                    <a
                                                        href={project.routes?.show}
                                                        data-native="true"
                                                        className="font-semibold text-slate-700 hover:text-teal-700"
                                                    >
                                                        View
                                                    </a>
                                                    <a
                                                        href={project.routes?.edit}
                                                        data-native="true"
                                                        className="font-semibold text-slate-700 hover:text-teal-700"
                                                    >
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action={project.routes?.destroy}
                                                        data-native="true"
                                                        onSubmit={(event) => {
                                                            if (!window.confirm(`Delete project ${project.name}? This is permanent.`)) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="font-semibold text-rose-600 hover:text-rose-700">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-2 flex items-center justify-end gap-2 text-sm">
                        {pagination.previous_url ? (
                            <a
                                href={pagination.previous_url}
                                data-native="true"
                                className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
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
                                className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
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
