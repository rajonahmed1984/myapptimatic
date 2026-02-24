import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ projects = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Chat" />

            <div className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Chat</div>
                        <div className="text-sm text-slate-500">Select a project to open chat.</div>
                    </div>
                    <a href={routes?.projects_index} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-teal-600">Projects</a>
                </div>

                <div className="mt-6 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Project</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Unread</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {projects.length === 0 ? (
                                <tr><td colSpan={4} className="px-4 py-6 text-center text-slate-500">No projects available.</td></tr>
                            ) : projects.map((project) => (
                                <tr key={project.id}>
                                    <td className="px-4 py-3"><div className="font-semibold text-slate-900">{project.name}</div><div className="text-xs text-slate-500">#{project.id}</div></td>
                                    <td className="px-4 py-3 text-slate-600">{project.status_label}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${Number(project.unread_count || 0) > 0 ? 'border-amber-300 bg-amber-100 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                                            {project.unread_count || 0}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right"><a href={project?.routes?.chat} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Chat</a></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
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
