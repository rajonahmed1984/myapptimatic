import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase().trim();
    if (key.includes('earned')) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (key.includes('payable')) return 'border-blue-200 bg-blue-50 text-blue-700';
    if (key.includes('paid') || key.includes('complete')) return 'border-teal-200 bg-teal-50 text-teal-700';
    if (key.includes('pending') || key.includes('progress') || key.includes('ongoing')) return 'border-amber-200 bg-amber-50 text-amber-700';
    if (key.includes('reverse') || key.includes('cancel') || key.includes('reject')) return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-slate-200 bg-slate-100 text-slate-700';
};

export default function Index({ earnings = [], assigned_projects = [], pagination = {}, routes = {} }) {
    const assignedCommissionTotal = assigned_projects.reduce((sum, project) => sum + Number(project?.commission_amount || 0), 0);
    const assignedCurrency = assigned_projects.find((project) => project?.currency)?.currency || 'BDT';

    const paidTotal = earnings.reduce((sum, earning) => sum + Number(earning?.paid_amount || 0), 0);
    const commissionTotal = earnings.reduce((sum, earning) => sum + Number(earning?.commission_amount || 0), 0);
    const earningsCurrency = earnings.find((earning) => earning?.currency)?.currency || 'BDT';

    return (
        <>
            <Head title="My Earnings" />

            <div className="space-y-6">
                {assigned_projects.length > 0 ? (
                    <div className="card overflow-hidden">
                        <div className="px-4 py-3 border-b border-slate-200 text-xs uppercase font-bold text-slate-500">
                            Assigned project commissions
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">Project</th>
                                        <th className="px-4 py-3">Customer</th>
                                        <th className="px-4 py-3">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {assigned_projects.map((project) => (
                                        <tr key={project.id} className="border-b border-slate-100 transition hover:bg-slate-50/60">
                                            <td className="px-4 py-3 font-medium text-slate-900">#{project.id} - {project.name}</td>
                                            <td className="px-4 py-3 text-slate-600">{project.customer_name}</td>
                                            <td className="px-4 py-3 text-slate-700">{project.commission_amount !== null ? `${Number(project.commission_amount).toFixed(2)} ${project.currency}` : '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="border-t-2 border-slate-200 bg-slate-50/80 font-semibold text-slate-800">
                                    <tr>
                                        <td colSpan={2} className="px-4 py-3 font-bold text-slate-800">Total</td>
                                        <td className="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                            {assignedCommissionTotal.toFixed(2)} {assignedCurrency}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                ) : null}

                <div>
                    <DataTable
                        header="Commission earnings"
                        rows={earnings}
                        emptyMessage="No earnings found."
                        columns={[
                            { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (earning) => `#${earning.id}` },
                            { key: 'source', header: 'Source', render: (earning) => `${earning.source_type}${earning.source_label ? ` (${earning.source_label})` : ''}` },
                            { key: 'customer', header: 'Customer', render: (earning) => earning.customer_name },
                            { key: 'paid_amount', header: 'Paid amount', cellClassName: 'text-sm text-slate-600', render: (earning) => `${Number(earning.paid_amount || 0).toFixed(2)} ${earning.currency}` },
                            { key: 'commission', header: 'Commission', cellClassName: 'text-sm text-slate-600', render: (earning) => `${Number(earning.commission_amount || 0).toFixed(2)} ${earning.currency}` },
                            {
                                key: 'status',
                                header: 'Status',
                                render: (earning) => (
                                    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(earning.status_label)}`}>
                                        {earning.status_label}
                                    </span>
                                ),
                            },
                            { key: 'earned', header: 'Earned', cellClassName: 'text-sm text-slate-500', render: (earning) => earning.earned_at_display },
                        ]}
                        footer={
                            <tr>
                                <td colSpan={3} className="px-4 py-3 font-bold text-slate-800">Total</td>
                                <td className="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                    {paidTotal.toFixed(2)} {earningsCurrency}
                                </td>
                                <td className="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                    {commissionTotal.toFixed(2)} {earningsCurrency}
                                </td>
                                <td colSpan={2} className="px-4 py-3" />
                            </tr>
                        }
                        mobileFooter={
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm text-sm">
                                <span className="font-semibold text-slate-800">Total</span>
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="text-slate-700">Paid amount: <strong className="text-slate-900">{paidTotal.toFixed(2)} {earningsCurrency}</strong></span>
                                    <span className="text-slate-700">Commission: <strong className="text-slate-900">{commissionTotal.toFixed(2)} {earningsCurrency}</strong></span>
                                </div>
                            </div>
                        }
                        renderMobileCard={(earning) => (
                            <MobileCard
                                title={`${earning.source_type}${earning.source_label ? ` (${earning.source_label})` : ''}`}
                                subtitle={earning.customer_name}
                                badge={earning.status_label}
                                badgeColor={statusClass(earning.status_label)}
                                metrics={[
                                    { label: 'Commission', value: `${Number(earning.commission_amount || 0).toFixed(2)} ${earning.currency}` },
                                    { label: 'Paid amount', value: `${Number(earning.paid_amount || 0).toFixed(2)} ${earning.currency}` },
                                ]}
                            >
                                <div className="text-xs text-slate-500">Earned: {earning.earned_at_display}</div>
                            </MobileCard>
                        )}
                    />

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
            </div>
        </>
    );
}
