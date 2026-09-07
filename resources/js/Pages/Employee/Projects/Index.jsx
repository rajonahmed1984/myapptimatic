import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const getStatusClass = (status) => {
    const s = String(status || '').toLowerCase();
    if (s.includes('complete') || s.includes('done')) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }
    if (s.includes('ongoing') || s.includes('progress') || s.includes('pending') || s.includes('active')) {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }
    if (s.includes('cancel') || s.includes('reject')) {
        return 'border-rose-200 bg-rose-50 text-rose-700';
    }
    return 'border-slate-200 bg-slate-100 text-slate-700';
};

export default function Index({ projects = [], pagination = {} }) {
    return (
        <>
            <Head title="Projects" />

            <DataTable
                rows={projects}
                emptyMessage="No projects assigned."
                columns={[
                    { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (project) => `#${project.id}` },
                    {
                        key: 'project',
                        header: 'Project',
                        render: (project) => <a href={project?.routes?.show} data-native="true" className="text-sm font-semibold text-teal-700 hover:text-teal-600">{project.name}</a>,
                    },
                    { key: 'tasks', header: 'Tasks', cellClassName: 'text-xs text-slate-600', render: (project) => `${project.tasks_count} total / ${project.completed_tasks_count} completed` },
                    { key: 'subtasks', header: 'Subtasks', cellClassName: 'text-xs text-slate-600', render: (project) => `${project.subtasks_count} total / ${project.completed_subtasks_count} completed` },
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
                        key: 'actions',
                        header: 'Actions',
                        headerClassName: 'text-right',
                        cellClassName: 'text-right',
                        render: (project) => <a href={project?.routes?.show} data-native="true" className="text-sm font-semibold text-teal-700 hover:text-teal-600">View</a>,
                    },
                ]}
                renderMobileCard={(project) => (
                    <MobileCard
                        title={<a href={project?.routes?.show} data-native="true" className="hover:text-teal-600">{project.name}</a>}
                        subtitle={`#${project.id}`}
                        badge={project.status_label}
                        badgeColor={getStatusClass(project.status_label)}
                        metrics={[
                            { label: 'Tasks', value: `${project.completed_tasks_count}/${project.tasks_count}` },
                            { label: 'Subtasks', value: `${project.completed_subtasks_count}/${project.subtasks_count}` },
                        ]}
                        actions={
                            <a
                                href={project?.routes?.show}
                                data-native="true"
                                className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                            >
                                View
                            </a>
                        }
                    />
                )}
            />

            {pagination?.last_page > 1 ? (
                <div className="mt-4 flex items-center justify-between text-xs px-2">
                    <span className="text-slate-500">Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}</span>
                    <div className="flex items-center gap-2">
                        {pagination.prev_page_url ? <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Previous</a> : null}
                        {pagination.next_page_url ? <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">Next</a> : null}
                    </div>
                </div>
            ) : null}
        </>
    );
}
