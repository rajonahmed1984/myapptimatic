import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
};

const unreadBadgeClass = (unread) =>
    unread > 0
        ? 'border-amber-300 bg-amber-100 text-amber-800'
        : 'border-slate-300 bg-slate-50 text-slate-500';

const displayStatus = (status) => {
    if (!status) {
        return '--';
    }

    return String(status).replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

export default function Index({
    pageTitle = 'Chat',
    projects = { data: [], links: [] },
    pageUnreadTotal = 0,
    routes = {},
}) {
    const rows = projects?.data ?? [];

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <span className="text-xs font-semibold text-slate-500">
                        Showing {projects?.from ?? 1} – {projects?.to ?? rows.length} of {projects?.total ?? rows.length} projects
                    </span>
                    <div className="flex items-center gap-3">
                        <span className={`rounded-full border px-3 py-1 text-xs font-semibold ${unreadBadgeClass(pageUnreadTotal)}`}>
                            Unread on this page: {Number(pageUnreadTotal)}
                        </span>
                        <a
                            href={routes?.projects_index}
                            data-native="true"
                            className="text-xs font-semibold text-slate-500 hover:text-teal-600"
                        >
                            Projects
                        </a>
                    </div>
                </div>

                <DataTable
                    framed={false}
                    rows={rows}
                    emptyMessage="No projects available."
                        columns={[
                            { key: 'id', header: 'ID', render: (project) => <div className="text-xs text-slate-500">#{project.id}</div> },
                            { key: 'project', header: 'Project', render: (project) => <div className="font-semibold text-slate-900">{project.name}</div> },
                            { key: 'status', header: 'Status', cellClassName: 'text-slate-600', render: (project) => displayStatus(project.status) },
                            {
                                key: 'unread',
                                header: 'Unread',
                                render: (project) => (
                                    <span className={`inline-flex min-w-8 items-center justify-center rounded-full border px-2 py-0.5 text-xs font-semibold ${unreadBadgeClass(Number(project.unread_count ?? 0))}`}>
                                        {Number(project.unread_count ?? 0)}
                                    </span>
                                ),
                            },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (project) => <a href={project?.routes?.chat} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Chat</a>,
                            },
                        ]}
                        renderMobileCard={(project) => {
                            const unread = Number(project.unread_count ?? 0);
                            return (
                                <MobileCard
                                    title={project.name}
                                    subtitle={`#${project.id} · ${displayStatus(project.status)}`}
                                    badge={unread > 0 ? `${unread} unread` : null}
                                    badgeColor={unread > 0 ? 'bg-amber-100 text-amber-800' : undefined}
                                    actions={
                                        <a
                                            href={project?.routes?.chat}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                        >
                                            Open Chat
                                        </a>
                                    }
                                />
                            );
                        }}
                    />

                <Pagination
                    pagination={projects}
                    label="projects"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
