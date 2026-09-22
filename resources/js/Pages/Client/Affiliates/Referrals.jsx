import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Referrals({ referrals = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Referrals" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
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

                <Pagination
                    pagination={pagination}
                    label="referrals"
                    className="mt-4 border-t border-slate-200 pt-4"
                />
            </div>
        </>
    );
}
