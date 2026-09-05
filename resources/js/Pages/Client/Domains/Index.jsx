import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({ has_customer = false, domains = [], routes = {} }) {
    return (
        <>
            <Head title="Domains" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Domains</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage licensed domains and monitor verification status.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {!has_customer ? (
                <div className="card p-6 text-sm text-slate-600">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            ) : domains.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No domains found.</div>
            ) : (
                <DataTable
                    rows={domains}
                    columns={[
                        {
                            key: 'domain',
                            header: 'Domain',
                            render: (d) => (
                                <>
                                    <div className="font-medium text-slate-900">{d.domain}</div>
                                    <div className="text-xs text-slate-400">{d.masked_key}</div>
                                </>
                            ),
                        },
                        { key: 'product', header: 'Product', cellClassName: 'text-slate-600', render: (d) => d.product_name },
                        { key: 'plan', header: 'Plan', cellClassName: 'text-slate-600', render: (d) => d.plan_name },
                        { key: 'status', header: 'Status', cellClassName: 'text-slate-600', render: (d) => d.status_label },
                        { key: 'verified', header: 'Verified', cellClassName: 'text-slate-500', render: (d) => d.verified_display },
                        { key: 'last_seen', header: 'Last Seen', cellClassName: 'text-slate-500', render: (d) => d.last_seen_display },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (d) => <a href={d.routes.show} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">View</a>,
                        },
                    ]}
                    renderMobileCard={(domain) => (
                        <MobileCard
                            title={domain.domain}
                            subtitle={`${domain.product_name} · ${domain.plan_name}`}
                            badge={domain.status_label}
                            metrics={[
                                { label: 'Verified', value: domain.verified_display },
                                { label: 'Last Seen', value: domain.last_seen_display },
                            ]}
                            actions={
                                <a
                                    href={domain.routes.show}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                >
                                    View
                                </a>
                            }
                        >
                            <div className="text-xs font-mono text-slate-400">{domain.masked_key}</div>
                        </MobileCard>
                    )}
                />
            )}
        </>
    );
}
