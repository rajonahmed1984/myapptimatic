import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Payouts({ payouts = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Payouts" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Payouts</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <DataTable
                    rows={payouts}
                    columns={[
                        { key: 'number', header: 'Payout #', render: (payout) => payout.payout_number || '--' },
                        { key: 'amount', header: 'Amount', render: (payout) => `$${Number(payout.amount || 0).toFixed(2)}` },
                        { key: 'status', header: 'Status', render: (payout) => payout.status_label },
                        { key: 'method', header: 'Method', render: (payout) => payout.payment_method || '--' },
                        { key: 'processed', header: 'Processed', render: (payout) => payout.processed_at_display },
                        { key: 'completed', header: 'Completed', render: (payout) => payout.completed_at_display },
                    ]}
                    renderMobileCard={(payout) => (
                        <MobileCard
                            title={payout.payout_number || `Payout #${payout.id}`}
                            subtitle={payout.payment_method || '--'}
                            badge={payout.status_label}
                            metrics={[
                                { label: 'Amount', value: `$${Number(payout.amount || 0).toFixed(2)}` },
                                { label: 'Processed', value: payout.processed_at_display || '--' },
                            ]}
                        >
                            {payout.completed_at_display ? <div className="text-xs text-slate-500">Completed: {payout.completed_at_display}</div> : null}
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
