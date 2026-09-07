import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase().trim();
    if (key.includes('paid') || key.includes('complete')) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (key.includes('process') || key.includes('payable')) return 'border-blue-200 bg-blue-50 text-blue-700';
    if (key.includes('pending') || key.includes('ongoing') || key.includes('hold')) return 'border-amber-200 bg-amber-50 text-amber-700';
    if (key.includes('reverse') || key.includes('cancel') || key.includes('reject')) return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-slate-200 bg-slate-100 text-slate-700';
};

export default function Index({ payouts = [], pagination = {}, routes = {} }) {
    const payoutTotal = payouts.reduce((sum, payout) => sum + Number(payout?.total_amount || 0), 0);
    const payoutCurrency = payouts.find((payout) => payout?.currency)?.currency || 'BDT';

    return (
        <>
            <Head title="My Payouts" />

            <div className="space-y-6">
                <div>
                    <DataTable
                        header="Payout history"
                        rows={payouts}
                        emptyMessage="No payouts yet."
                        columns={[
                            { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (payout) => `#${payout.id}` },
                            { key: 'type', header: 'Type', render: (payout) => payout.type_label },
                            { key: 'amount', header: 'Amount', cellClassName: 'text-sm text-slate-600', render: (payout) => `${Number(payout.total_amount || 0).toFixed(2)} ${payout.currency}` },
                            {
                                key: 'status',
                                header: 'Status',
                                render: (payout) => (
                                    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(payout.status_label)}`}>
                                        {payout.status_label}
                                    </span>
                                ),
                            },
                            { key: 'method', header: 'Method', cellClassName: 'text-sm text-slate-600', render: (payout) => payout.payout_method },
                            { key: 'paid_at', header: 'Paid at', cellClassName: 'text-sm text-slate-500', render: (payout) => payout.paid_at_display },
                        ]}
                        footer={
                            <tr>
                                <td colSpan={2} className="px-4 py-3 font-bold text-slate-800">Total</td>
                                <td className="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                    {payoutTotal.toFixed(2)} {payoutCurrency}
                                </td>
                                <td colSpan={3} className="px-4 py-3" />
                            </tr>
                        }
                        mobileFooter={
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm text-sm">
                                <span className="font-semibold text-slate-800">Total</span>
                                <span className="text-slate-700">Amount: <strong className="text-slate-900">{payoutTotal.toFixed(2)} {payoutCurrency}</strong></span>
                            </div>
                        }
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

                    {pagination?.last_page > 1 ? (
                        <div className="mt-4 flex items-center justify-between text-xs">
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
