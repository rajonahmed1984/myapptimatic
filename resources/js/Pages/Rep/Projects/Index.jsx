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
    const commissionTotal = projects.reduce((sum, project) => sum + Number(project?.commission_amount || 0), 0);
    const takenTotal = projects.reduce((sum, project) => sum + Number(project?.taken_commission_amount || 0), 0);
    const displayCurrency = projects.find((project) => project?.currency)?.currency || '';

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
                        render: (project) => (
                            <a href={project?.routes?.show} data-native="true" className="text-sm font-semibold text-teal-700 hover:text-teal-600">{project.name}</a>
                        ),
                    },
                    { key: 'customer', header: 'Customer', render: (project) => project.customer_name },
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
                        key: 'commission',
                        header: 'Commission',
                        cellClassName: 'text-sm text-slate-600',
                        render: (project) => project.commission_amount !== null ? `${Number(project.commission_amount).toFixed(2)} ${project.currency}` : '--',
                    },
                    {
                        key: 'taken_commission',
                        header: 'Taken Commission',
                        cellClassName: 'text-sm text-slate-600',
                        render: (project) => `${Number(project.taken_commission_amount || 0).toFixed(2)} ${project.currency}`,
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
                        title={
                            <a href={project?.routes?.show} data-native="true" className="hover:text-teal-600">{project.name}</a>
                        }
                        subtitle={`#${project.id} · ${project.customer_name}`}
                        badge={project.status_label}
                        badgeColor={getStatusClass(project.status_label)}
                        metrics={[
                            { label: 'Commission', value: project.commission_amount !== null ? `${Number(project.commission_amount).toFixed(2)} ${project.currency}` : '--' },
                            { label: 'Taken', value: `${Number(project.taken_commission_amount || 0).toFixed(2)} ${project.currency}` },
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

            {projects.length > 0 ? (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm text-sm">
                    <span className="font-semibold text-slate-800">Totals</span>
                    <span className="text-slate-700">Commission: <strong className="text-slate-900">{commissionTotal.toFixed(2)} {displayCurrency}</strong></span>
                    <span className="text-slate-700">Taken: <strong className="text-slate-900">{takenTotal.toFixed(2)} {displayCurrency}</strong></span>
                </div>
            ) : null}

            {pagination?.last_page > 1 ? (
                <div className="mt-4 flex items-center justify-between text-xs">
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
