import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ projects = [], pagination = {} }) {
    return (
        <>
            <Head title="Projects" />

            <div className="mb-6">
                <div className="section-label">Projects</div>
                <div className="text-2xl font-semibold text-slate-900">Your projects</div>
                <div className="text-sm text-slate-500">Projects associated with your account.</div>
            </div>

            <div className="card p-6">
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white/80">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm text-slate-700">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="w-20 px-4 py-3">ID</th>
                                    <th className="px-4 py-3">Project</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Maintenance</th>
                                    <th className="px-4 py-3">Billing Type</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-6 text-center text-sm text-slate-500">
                                            No projects found.
                                        </td>
                                    </tr>
                                ) : (
                                    projects.map((project) => (
                                        <React.Fragment key={project.id}>
                                            <tr className="border-t border-slate-100 bg-slate-50/60">
                                                <td className="px-4 py-3 font-semibold text-slate-900">#{project.id}</td>
                                                <td className="px-4 py-3">
                                                    <a href={project.routes.show} data-native="true" className="font-medium text-teal-600 hover:text-teal-500">
                                                        {project.name}
                                                    </a>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{project.status_label}</span>
                                                </td>
                                                <td className="px-4 py-3 text-slate-400">--</td>
                                                <td className="px-4 py-3 text-slate-400">--</td>
                                                <td className="px-4 py-3 font-semibold text-slate-900">{project.amount_label}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <a href={project.routes.show} data-native="true" className="text-sm font-semibold text-teal-700 hover:text-teal-600">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                            {project.maintenances.map((maintenance) => (
                                                <tr key={maintenance.id} className="border-t border-slate-100 hover:bg-slate-50/70">
                                                    <td className="px-4 py-3 text-slate-400">--</td>
                                                    <td className="px-4 py-3 text-slate-500">Maintenance</td>
                                                    <td className="px-4 py-3">
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
                                                    <td className="px-4 py-3 text-slate-900">{maintenance.title}</td>
                                                    <td className="px-4 py-3">{maintenance.billing_cycle_label}</td>
                                                    <td className="px-4 py-3 font-semibold text-teal-600">{maintenance.amount_label}</td>
                                                    <td className="px-4 py-3 text-right text-slate-400">--</td>
                                                </tr>
                                            ))}
                                        </React.Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
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
