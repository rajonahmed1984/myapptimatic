import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';
import { statusBadgeClass as xTransferStatusClass } from '../../../utils/statusColors';

export default function Index({ pageTitle = 'Incoming Transfers', transfers = [] }) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6">
                <div className="text-2xl font-semibold text-slate-900">Incoming Transfers</div>
                <div className="mt-1 text-sm text-slate-500">
                    Projects other customers have offered to transfer to your account. Check your email for the accept/reject link on each invite.
                </div>
            </div>

            <DataTable
                rows={transfers}
                emptyMessage="No incoming transfers."
                columns={[
                    { key: 'item', header: 'Item', cellClassName: 'text-slate-800', render: (transfer) => transfer.project_name },
                    { key: 'from', header: 'From', cellClassName: 'text-slate-600', render: (transfer) => transfer.from_customer_name },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (transfer) => (
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${xTransferStatusClass(transfer.status)}`}>
                                {transfer.status_label}
                            </span>
                        ),
                    },
                    { key: 'requested', header: 'Requested', cellClassName: 'text-slate-500', render: (transfer) => transfer.created_at },
                ]}
                renderMobileCard={(transfer) => (
                    <MobileCard
                        title={transfer.project_name}
                        subtitle={`From: ${transfer.from_customer_name}`}
                        badge={transfer.status_label}
                        badgeColor={xTransferStatusClass(transfer.status)}
                    >
                        <div className="text-xs text-slate-500">Requested: {transfer.created_at}</div>
                    </MobileCard>
                )}
            />
        </>
    );
}
