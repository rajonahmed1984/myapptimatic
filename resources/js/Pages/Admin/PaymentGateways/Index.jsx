import React from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (active) =>
    active ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200';

export default function Index({ pageTitle = 'Payment Gateways', gateways = [], routes = {} }) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Payment Gateways</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Unified Tk in/out overview with gateway ledger and matched payment method transaction details.
                    </p>
                </div>
                {routes?.debug_log && (
                    <a
                        href={routes.debug_log}
                        data-native="true"
                        className="rounded-full bg-slate-900 hover:bg-slate-800 text-white font-medium px-4 py-2 text-xs flex items-center gap-1.5 transition-colors"
                    >
                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Debug Log
                    </a>
                )}
            </div>

            <DataTable
                rows={gateways}
                emptyMessage="No gateways found."
                columns={[
                    {
                        key: 'gateway',
                        header: 'Gateway',
                        render: (gateway) => (
                            <>
                                <div className="font-semibold text-slate-900">{gateway.name}</div>
                                <div className="mt-1 text-xs text-slate-500">{gateway.details_display || '--'}</div>
                            </>
                        ),
                    },
                    { key: 'tk_in', header: 'Tk In', cellClassName: 'font-medium text-emerald-700', render: (gateway) => gateway.financial_summary?.tk_in_display || '0.00' },
                    { key: 'tk_out', header: 'Tk Out', cellClassName: 'font-medium text-rose-700', render: (gateway) => gateway.financial_summary?.tk_out_display || '0.00' },
                    {
                        key: 'status',
                        header: 'Status',
                        render: (gateway) => <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(gateway.is_active)}`}>{gateway.is_active ? 'Active' : 'Inactive'}</span>,
                    },
                    {
                        key: 'action',
                        header: 'Action',
                        render: (gateway) => (
                            <>
                                <a href={gateway?.routes?.view || gateway?.routes?.edit} data-native="true" className="text-slate-600 hover:text-slate-500">View</a>
                                <span className="mx-2 text-slate-300">|</span>
                                <a href={gateway?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">Edit</a>
                            </>
                        ),
                    },
                ]}
                renderMobileCard={(gateway) => (
                    <MobileCard
                        title={gateway.name}
                        subtitle={gateway.details_display}
                        badge={gateway.is_active ? 'Active' : 'Inactive'}
                        badgeColor={statusClass(gateway.is_active)}
                        metrics={[
                            { label: 'Tk In', value: gateway.financial_summary?.tk_in_display || '0.00', tone: 'text-emerald-700' },
                            { label: 'Tk Out', value: gateway.financial_summary?.tk_out_display || '0.00', tone: 'text-rose-700' },
                        ]}
                        actions={
                            <>
                                <a
                                    href={gateway?.routes?.view || gateway?.routes?.edit}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                >
                                    View
                                </a>
                                <a
                                    href={gateway?.routes?.edit}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                >
                                    Edit
                                </a>
                            </>
                        }
                    />
                )}
            />
        </>
    );
}
