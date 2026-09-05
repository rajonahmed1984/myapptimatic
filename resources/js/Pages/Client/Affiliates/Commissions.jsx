import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Commissions({ commissions = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="Affiliate Commissions" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Commissions</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <DataTable
                    rows={commissions}
                    columns={[
                        { key: 'description', header: 'Description', render: (commission) => commission.description || '--' },
                        { key: 'invoice', header: 'Invoice', render: (commission) => commission.invoice_label },
                        { key: 'order', header: 'Order', render: (commission) => commission.order_label },
                        { key: 'amount', header: 'Amount', render: (commission) => `$${Number(commission.amount || 0).toFixed(2)}` },
                        { key: 'status', header: 'Status', render: (commission) => commission.status_label },
                        { key: 'date', header: 'Date', render: (commission) => commission.created_at_display },
                    ]}
                    renderMobileCard={(commission) => (
                        <MobileCard
                            title={commission.description || `${commission.invoice_label} · ${commission.order_label}`}
                            subtitle={`${commission.invoice_label} · ${commission.order_label}`}
                            badge={commission.status_label}
                            metrics={[
                                { label: 'Amount', value: `$${Number(commission.amount || 0).toFixed(2)}` },
                                { label: 'Date', value: commission.created_at_display },
                            ]}
                        />
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
