import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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
                    <a href={routes.projects} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-teal-600">
                        Projects
                    </a>
                </div>

                <div className="mt-6">
                    <DataTable
                        rows={projects}
                        emptyMessage="No projects available."
                        columns={[
                            {
                                key: 'project',
                                header: 'Project',
                                render: (project) => (
                                    <>
                                        <div className="font-semibold text-slate-900">{project.name}</div>
                                        <div className="text-xs text-slate-500">#{project.id}</div>
                                    </>
                                ),
                            },
                            { key: 'status', header: 'Status', cellClassName: 'text-slate-600', render: (project) => project.status_label },
                            {
                                key: 'unread',
                                header: 'Unread',
                                cellClassName: 'text-slate-700',
                                render: (project) => (
                                    <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-[10px] font-semibold text-slate-500">
                                        {project.unread_count}
                                    </span>
                                ),
                            },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (project) => (
                                    <a href={project.routes.chat} data-native="true" className="whitespace-nowrap rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">Open Chat</a>
                                ),
                            },
                        ]}
                        renderMobileCard={(project) => (
                            <MobileCard
                                title={project.name}
                                subtitle={`#${project.id} · ${project.status_label}`}
                                badge={Number(project.unread_count || 0) > 0 ? `${project.unread_count} unread` : null}
                                badgeColor={Number(project.unread_count || 0) > 0 ? 'bg-amber-100 text-amber-800' : undefined}
                                actions={
                                    <a
                                        href={project.routes.chat}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Open Chat
                                    </a>
                                }
                            />
                        )}
                    />
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
