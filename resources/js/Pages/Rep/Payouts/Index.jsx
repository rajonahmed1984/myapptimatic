import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('paid')) return 'bg-emerald-100 text-emerald-700';
    if (key.includes('pending')) return 'bg-amber-100 text-amber-700';
    if (key.includes('cancel') || key.includes('reject')) return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ payouts = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="My Payouts" />

            <div className="card space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="section-label">Commissions</div>
                        <h1 className="text-2xl font-semibold text-slate-900">Payout history</h1>
                        <div className="text-sm text-slate-500">Read-only view of your payouts.</div>
                    </div>
                    <a href={routes?.dashboard} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div className="mt-3">
                        <DataTable
                            rows={payouts}
                            emptyMessage="No payouts yet."
                            columns={[
                                { key: 'id', header: 'ID', render: (payout) => `#${payout.id}` },
                                { key: 'type', header: 'Type', render: (payout) => payout.type_label },
                                { key: 'amount', header: 'Amount', render: (payout) => `${Number(payout.total_amount || 0).toFixed(2)} ${payout.currency}` },
                                { key: 'status', header: 'Status', render: (payout) => payout.status_label },
                                { key: 'method', header: 'Method', render: (payout) => payout.payout_method },
                                { key: 'paid_at', header: 'Paid at', render: (payout) => payout.paid_at_display },
                            ]}
                            renderMobileCard={(payout) => (
                                <MobileCard
                                    title={`#${payout.id} · ${payout.type_label}`}
                                    subtitle={payout.payout_method}
                                    badge={payout.status_label}
                                    badgeColor={statusClass(payout.status_label)}
                                    metrics={[
                                        { label: 'Amount', value: `${Number(payout.total_amount || 0).toFixed(2)} ${payout.currency}` },
                                        { label: 'Paid at', value: payout.paid_at_display || '--' },
                                    ]}
                                />
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
