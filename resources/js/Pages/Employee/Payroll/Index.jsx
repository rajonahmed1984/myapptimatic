import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase();
    if (key.includes('paid')) return 'bg-emerald-100 text-emerald-700';
    if (key.includes('partial')) return 'bg-blue-100 text-blue-700';
    if (key.includes('unpaid') || key.includes('pending')) return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ items = [], pagination = {} }) {
    return (
        <>
            <Head title="Payroll" />

            <div className="card p-6">
                <div className="mt-4">
                    <DataTable
                        rows={items}
                        emptyMessage="No payroll items yet."
                        columns={[
                            { key: 'period', header: 'Period', render: (item) => item.period_key },
                            { key: 'gross', header: 'Gross', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.gross_pay || 0).toFixed(2)} ${item.currency}` },
                            { key: 'bonus', header: 'Bonus', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.bonus || 0).toFixed(2)} ${item.currency}` },
                            { key: 'penalty', header: 'Penalty', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.penalty || 0).toFixed(2)} ${item.currency}` },
                            { key: 'advance', header: 'Advance', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.advance || 0).toFixed(2)} ${item.currency}` },
                            { key: 'deduction', header: 'Deduction', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.deduction || 0).toFixed(2)} ${item.currency}` },
                            {
                                key: 'net_payable',
                                header: 'Net Payable',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right font-semibold text-slate-900',
                                render: (item) => `${Number(item.net_payable || 0).toFixed(2)} ${item.currency}`,
                            },
                            { key: 'paid', header: 'Paid', headerClassName: 'text-right', cellClassName: 'text-right', render: (item) => `${Number(item.paid || 0).toFixed(2)} ${item.currency}` },
                            {
                                key: 'remaining',
                                header: 'Remaining',
                                headerClassName: 'text-right',
                                cellClassName: 'text-right',
                                render: (item) => (
                                    <span className={`font-semibold ${Number(item.remaining || 0) > 0 ? 'text-amber-600' : 'text-emerald-600'}`}>
                                        {Number(item.remaining || 0).toFixed(2)} {item.currency}
                                    </span>
                                ),
                            },
                            { key: 'status', header: 'Status', render: (item) => item.status_label },
                            { key: 'paid_at', header: 'Paid at', render: (item) => item.paid_at_display },
                        ]}
                        renderMobileCard={(item) => (
                            <MobileCard
                                title={item.period_key}
                                badge={item.status_label}
                                badgeColor={statusClass(item.status_label)}
                                metrics={[
                                    { label: 'Net Payable', value: `${Number(item.net_payable || 0).toFixed(2)} ${item.currency}` },
                                    {
                                        label: 'Remaining',
                                        value: `${Number(item.remaining || 0).toFixed(2)} ${item.currency}`,
                                        tone: Number(item.remaining || 0) > 0 ? 'text-amber-600' : 'text-emerald-600',
                                    },
                                ]}
                            >
                                <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-500">
                                    <div>Gross: {Number(item.gross_pay || 0).toFixed(2)} {item.currency}</div>
                                    <div>Bonus: {Number(item.bonus || 0).toFixed(2)} {item.currency}</div>
                                    <div>Penalty: {Number(item.penalty || 0).toFixed(2)} {item.currency}</div>
                                    <div>Advance: {Number(item.advance || 0).toFixed(2)} {item.currency}</div>
                                    <div>Deduction: {Number(item.deduction || 0).toFixed(2)} {item.currency}</div>
                                    <div>Paid: {Number(item.paid || 0).toFixed(2)} {item.currency}</div>
                                    {item.paid_at_display ? <div className="col-span-2">Paid at: {item.paid_at_display}</div> : null}
                                </div>
                            </MobileCard>
                        )}
                    />
                </div>

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
        </>
    );
}
