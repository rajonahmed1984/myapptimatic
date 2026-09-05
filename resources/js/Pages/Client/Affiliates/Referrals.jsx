import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Referrals({ referrals = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Referrals" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Referrals</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <DataTable
                    rows={referrals}
                    columns={[
                        { key: 'customer', header: 'Customer', render: (referral) => referral.customer_name },
                        { key: 'status', header: 'Status', render: (referral) => referral.status_label },
                        { key: 'date', header: 'Date', render: (referral) => referral.created_at_display },
                    ]}
                    renderMobileCard={(referral) => (
                        <MobileCard
                            title={referral.customer_name}
                            badge={referral.status_label}
                        >
                            <div className="text-xs text-slate-500">Referred: {referral.created_at_display}</div>
                        </MobileCard>
                    )}
                />

                {pagination.last_page > 1 ? (
                    <div className="mt-4 flex items-center gap-2 text-xs">
                        {pagination.prev_page_url ? (
                            <a href={pagination.prev_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                Previous
                            </a>
                        ) : null}
                        {pagination.next_page_url ? (
                            <a href={pagination.next_page_url} data-native="true" className="rounded-full border border-slate-200 px-3 py-1 text-slate-600">
                                Next
                            </a>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </>
    );
}
