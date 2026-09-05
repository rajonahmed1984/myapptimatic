import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({ has_customer = false, subscriptions = [], routes = {} }) {
    return (
        <>
            <Head title="Services" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Services</h1>
                    <p className="mt-1 text-sm text-slate-500">Review active services and billing cycle details.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {!has_customer ? (
                <div className="card p-6 text-sm text-slate-600">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            ) : subscriptions.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No active services found.</div>
            ) : (
                <DataTable
                    rows={subscriptions}
                    columns={[
                        { key: 'sl', header: 'SL', cellClassName: 'text-slate-600', render: (s) => s.serial },
                        { key: 'service', header: 'Service', cellClassName: 'font-medium text-slate-900', render: (s) => s.service_name },
                        { key: 'plan', header: 'Plan', cellClassName: 'text-slate-600', render: (s) => s.plan_name },
                        { key: 'status', header: 'Status', cellClassName: 'text-slate-600', render: (s) => s.status_label },
                        { key: 'cycle', header: 'Cycle', cellClassName: 'text-slate-500', render: (s) => s.cycle_label },
                        { key: 'next_due', header: 'Next Due', cellClassName: 'text-slate-500', render: (s) => s.next_due_display },
                        { key: 'auto_renew', header: 'Auto Renew', cellClassName: 'text-slate-500', render: (s) => s.auto_renew_label },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (s) => <a href={s.routes.show} data-native="true" className="text-xs text-teal-600 hover:text-teal-500">View</a>,
                        },
                    ]}
                    renderMobileCard={(subscription) => (
                        <MobileCard
                            title={subscription.service_name}
                            subtitle={subscription.plan_name}
                            badge={subscription.status_label}
                            metrics={[
                                { label: 'Cycle', value: subscription.cycle_label },
                                { label: 'Next Due', value: subscription.next_due_display },
                            ]}
                            actions={
                                <a
                                    href={subscription.routes.show}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                >
                                    View
                                </a>
                            }
                        >
                            <div className="text-xs text-slate-500">Auto renew: {subscription.auto_renew_label}</div>
                        </MobileCard>
                    )}
                />
            )}
        </>
    );
}
