import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
    danger: 'rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500',
};

const xTransferStatusClass = (status) => {
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    if (status === 'accepted') return 'bg-teal-100 text-teal-700';
    if (status === 'executed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'rejected' || status === 'cancelled' || status === 'expired') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ pageTitle = 'Ownership Transfers', transfers = [], pagination = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    const totalTransfers = Number(pagination?.total ?? transfers.length);
    const fromTransfer = pagination?.from !== undefined && pagination?.from !== null
        ? pagination.from
        : (totalTransfers > 0 ? 1 : 0);
    const toTransfer = pagination?.to !== undefined && pagination?.to !== null
        ? pagination.to
        : transfers.length;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <span className="text-xs font-semibold text-slate-500">
                        {totalTransfers > 0 ? (
                            <>
                                Showing <span className="font-semibold text-slate-700">{fromTransfer}</span> – <span className="font-semibold text-slate-700">{toTransfer}</span> of <span className="font-semibold text-slate-700">{totalTransfers}</span> transfers
                            </>
                        ) : (
                            'No transfers'
                        )}
                    </span>
                </div>

                <DataTable
                    framed={false}
                    rows={transfers}
                    emptyMessage="No ownership transfers yet."
                    columns={[
                        { key: 'item', header: 'Item', cellClassName: 'text-slate-800', render: (transfer) => transfer.project_name },
                        { key: 'from', header: 'From', cellClassName: 'text-slate-600', render: (transfer) => transfer.from_customer_name },
                        { key: 'to', header: 'To', cellClassName: 'text-slate-600', render: (transfer) => transfer.to_customer_name },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (transfer) => <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${xTransferStatusClass(transfer.status)}`}>{transfer.status_label}</span>,
                        },
                        { key: 'scheduled', header: 'Scheduled For', cellClassName: 'text-slate-500', render: (transfer) => transfer.scheduled_for },
                        { key: 'created', header: 'Created', cellClassName: 'text-slate-500', render: (transfer) => transfer.created_at },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (transfer) => (
                                transfer.can_cancel ? (
                                    <form action={transfer.routes?.cancel} method="POST" data-native="true">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <button
                                            type="submit"
                                            className={BTN.danger}
                                            onClick={(e) => { if (!confirm('Cancel this ownership transfer?')) e.preventDefault(); }}
                                        >
                                            Cancel
                                        </button>
                                    </form>
                                ) : null
                            ),
                        },
                    ]}
                    renderMobileCard={(transfer) => (
                        <MobileCard
                            title={transfer.project_name}
                            subtitle={`${transfer.from_customer_name} → ${transfer.to_customer_name}`}
                            badge={transfer.status_label}
                            badgeColor={xTransferStatusClass(transfer.status)}
                            metrics={[
                                { label: 'Scheduled For', value: transfer.scheduled_for || '--' },
                                { label: 'Created', value: transfer.created_at },
                            ]}
                            actions={
                                transfer.can_cancel ? (
                                    <form action={transfer.routes?.cancel} method="POST" data-native="true" className="flex-1">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <button
                                            type="submit"
                                            className="w-full py-2 px-3 rounded-xl bg-rose-600 text-xs font-bold text-white shadow-sm hover:bg-rose-500 transition active:scale-95"
                                            onClick={(e) => { if (!confirm('Cancel this ownership transfer?')) e.preventDefault(); }}
                                        >
                                            Cancel
                                        </button>
                                    </form>
                                ) : null
                            }
                        />
                    )}
                />

                <Pagination
                    pagination={pagination}
                    label="transfers"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
