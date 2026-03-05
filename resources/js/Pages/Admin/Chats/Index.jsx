import React from 'react';
import { Head } from '@inertiajs/react';

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

                <div className="mt-6 overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Project</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Unread</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {rows.length > 0 ? (
                                rows.map((project) => {
                                    const unread = Number(project.unread_count ?? 0);

                                    return (
                                        <tr key={project.id} className="align-top">
                                            <td className="px-4 py-3">
                                                <div className="text-xs text-slate-500">#{project.id}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900">{project.name}</div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">{displayStatus(project.status)}</td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex min-w-8 items-center justify-center rounded-full border px-2 py-0.5 text-xs font-semibold ${unreadBadgeClass(
                                                        unread,
                                                    )}`}
                                                >
                                                    {unread}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <a
                                                    href={project?.routes?.chat}
                                                    data-native="true"
                                                    className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                                >
                                                    Open Chat
                                                </a>
                                            </td>
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                                        No projects available.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
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
                                            : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600'
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
