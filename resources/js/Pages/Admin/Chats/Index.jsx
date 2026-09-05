import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
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
    const links = projects?.links ?? [];

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div className="section-label">Chat</div>
                        <div className="text-sm text-slate-500">Select a project to open chat.</div>
                    </div>
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

                <div className="mt-6">
                    <DataTable
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
                </div>

                {links.length > 0 ? (
                    <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        {links.map((link, idx) =>
                            link.url ? (
                                <a
                                    key={`${idx}-${link.label}`}
                                    href={link.url}
                                    data-native="true"
                                    className={`rounded-full border px-3 py-1 ${
                                        link.active
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : BTN.secondary
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={`${idx}-${link.label}`}
                                    className="rounded-full border border-slate-200 px-3 py-1 text-slate-300"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
