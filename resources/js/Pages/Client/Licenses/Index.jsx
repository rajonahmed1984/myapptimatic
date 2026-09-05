import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({ licenses = [], routes = {} }) {
    return (
        <>
            <Head title="Licenses" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Licenses</h1>
                    <p className="mt-1 text-sm text-slate-500">Track your licensed domains, plan level, and status.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {licenses.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No licenses found.</div>
            ) : (
                <DataTable
                    rows={licenses}
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'text-slate-500', render: (l) => l.id },
                        {
                            key: 'site',
                            header: 'Site',
                            render: (l) => (l.site_url ? (
                                <a href={l.site_url} target="_blank" rel="noreferrer" className="text-slate-700 hover:text-teal-600">{l.domain}</a>
                            ) : <span className="text-slate-400">--</span>),
                        },
                        { key: 'product', header: 'Product', cellClassName: 'text-slate-600', render: (l) => l.product_name },
                        { key: 'plan', header: 'Plan', cellClassName: 'text-slate-600', render: (l) => l.plan_name },
                        { key: 'installed', header: 'Installed on', cellClassName: 'text-slate-500', render: (l) => l.installed_on },
                        { key: 'version', header: 'Version', cellClassName: 'text-slate-400', render: () => '-' },
                        { key: 'license', header: 'License', cellClassName: 'font-mono text-xs text-slate-700', render: (l) => l.masked_key },
                        {
                            key: 'premium',
                            header: 'Is Premium',
                            render: (l) => (
                                <span className={l.is_premium ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700' : 'rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500'}>
                                    {l.is_premium ? 'Yes' : 'No'}
                                </span>
                            ),
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (l) => (
                                <span className={l.is_active ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700' : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700'}>
                                    {l.status_label}
                                </span>
                            ),
                        },
                    ]}
                    renderMobileCard={(license) => (
                        <MobileCard
                            title={license.site_url ? (
                                <a href={license.site_url} target="_blank" rel="noreferrer" className="hover:text-teal-600">{license.domain}</a>
                            ) : (license.domain || '--')}
                            subtitle={`${license.product_name} · ${license.plan_name}`}
                            badge={license.status_label}
                            badgeColor={license.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}
                            metrics={[
                                { label: 'Installed on', value: license.installed_on || '--' },
                                { label: 'Premium', value: license.is_premium ? 'Yes' : 'No' },
                            ]}
                        >
                            <div className="text-xs font-mono text-slate-500">{license.masked_key}</div>
                        </MobileCard>
                    )}
                />
            )}
        </>
    );
}
