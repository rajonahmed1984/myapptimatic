import React from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('paid')) return 'bg-emerald-100 text-emerald-700';
    if (key.includes('pending')) return 'bg-amber-100 text-amber-700';
    if (key.includes('cancel') || key.includes('reject')) return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ earnings = [], status = '', status_options = [], assigned_projects = [], pagination = {}, routes = {} }) {
    const filterFormRef = React.useRef(null);
    const statusFilterOptions = [
        { value: '', label: 'All' },
        ...status_options.map((option) => ({
            value: String(option),
            label: String(option).charAt(0).toUpperCase() + String(option).slice(1),
        })),
    ];

    return (
        <>
            <Head title="My Earnings" />

            <div className="card space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="section-label">Commissions</div>
                        <h1 className="text-2xl font-semibold text-slate-900">Earnings</h1>
                        <div className="text-sm text-slate-500">Read-only view of your commission earnings.</div>
                    </div>
                    <a href={routes?.dashboard} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
                </div>

                <form ref={filterFormRef} method="GET" action={routes?.index} className="grid gap-3 md:grid-cols-4" data-native="true">
                    <div>
                        <label className="text-xs text-slate-500">Status</label>
                        <SearchableSelect
                            name="status"
                            defaultValue={String(status || '')}
                            options={statusFilterOptions}
                            className="mt-1"
                            placeholder="All"
                            onChange={() => filterFormRef.current?.submit()}
                        />
                    </div>
                </form>

                {assigned_projects.length > 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="text-xs uppercase text-slate-500">Assigned project commissions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead><tr className="text-xs uppercase text-slate-500"><th className="px-2 py-2">Project</th><th className="px-2 py-2">Customer</th><th className="px-2 py-2">Amount</th></tr></thead>
                                <tbody>
                                    {assigned_projects.map((project) => (
                                        <tr key={project.id} className="border-t border-slate-200">
                                            <td className="px-2 py-2">#{project.id} - {project.name}</td>
                                            <td className="px-2 py-2">{project.customer_name}</td>
                                            <td className="px-2 py-2">{project.commission_amount !== null ? `${Number(project.commission_amount).toFixed(2)} ${project.currency}` : '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div className="mt-3">
                        <DataTable
                            rows={earnings}
                            emptyMessage="No earnings found."
                            columns={[
                                { key: 'id', header: 'ID', render: (earning) => `#${earning.id}` },
                                { key: 'source', header: 'Source', render: (earning) => `${earning.source_type}${earning.source_label ? ` (${earning.source_label})` : ''}` },
                                { key: 'customer', header: 'Customer', render: (earning) => earning.customer_name },
                                { key: 'paid_amount', header: 'Paid amount', render: (earning) => `${Number(earning.paid_amount || 0).toFixed(2)} ${earning.currency}` },
                                { key: 'commission', header: 'Commission', render: (earning) => `${Number(earning.commission_amount || 0).toFixed(2)} ${earning.currency}` },
                                { key: 'status', header: 'Status', render: (earning) => earning.status_label },
                                { key: 'earned', header: 'Earned', render: (earning) => earning.earned_at_display },
                            ]}
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
                    </div>

                    {pagination?.last_page > 1 ? (
                        <div className="mt-3 flex items-center justify-between text-xs">
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
