import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';
import { statusBadgeClass as getStatusClass } from '../../../utils/statusColors';

export default function Index({ projects = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Chat" />

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
                    {
                        key: 'status',
                        header: 'Status',
                        render: (project) => (
                            <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${getStatusClass(project.status_label)}`}>
                                {project.status_label}
                            </span>
                        ),
                    },
                    {
                        key: 'unread',
                        header: 'Unread',
                        render: (project) => (
                            <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${Number(project.unread_count || 0) > 0 ? 'border-amber-300 bg-amber-100 text-amber-800' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                                {project.unread_count || 0}
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
                renderMobileCard={(project) => (
                    <MobileCard
                        title={project.name}
                        subtitle={`#${project.id}`}
                        badge={project.status_label}
                        badgeColor={getStatusClass(project.status_label)}
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
                )}
            />

            <Pagination
                pagination={pagination}
                label="chats"
                className="border-t border-slate-200 px-4 py-3"
            />
        </>
    );
}
