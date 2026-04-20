import React from 'react';
import { Head } from '@inertiajs/react';

const statusClass = (active) =>
    active ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200';

export default function Index({ pageTitle = 'Payment Gateways', gateways = [] }) {
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
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[1100px] text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Gateway</th>
                            <th className="px-4 py-3">Transaction Details</th>
                            <th className="px-4 py-3">Tk In</th>
                            <th className="px-4 py-3">Tk Out</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {gateways.length > 0 ? (
                            gateways.map((gateway) => (
                                <tr key={gateway.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 align-top">
                                        <div className="font-semibold text-slate-900">{gateway.name}</div>
                                        <div className="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">
                                            {gateway.driver} / {gateway.slug}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 align-top text-xs text-slate-700">
                                        <div className="font-semibold text-slate-800">
                                            {gateway.financial_summary?.transactions_count || 0} transactions
                                        </div>
                                        <div className="mt-1">
                                            Method entries: {gateway.legacy_summary?.entries_count || 0}
                                        </div>
                                        <div className="mt-1">
                                            Method amount: {gateway.legacy_summary?.total_display || '0.00'}
                                        </div>
                                        <div className="mt-1">
                                            Last activity: {gateway.financial_summary?.last_activity_display || '--'}
                                        </div>
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 align-top font-medium text-emerald-700">
                                        {gateway.financial_summary?.tk_in_display || '0.00'}
                                    </td>
                                    <td className="px-4 py-3 align-top">
                                        <div className="whitespace-nowrap font-medium text-rose-700">
                                            {gateway.financial_summary?.tk_out_display || '0.00'}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(
                                                gateway.is_active,
                                            )}`}
                                        >
                                            {gateway.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <a
                                            href={gateway?.routes?.view || gateway?.routes?.edit}
                                            data-native="true"
                                            className="text-slate-600 hover:text-slate-500"
                                        >
                                            View
                                        </a>
                                        <span className="mx-2 text-slate-300">|</span>
                                        <a
                                            href={gateway?.routes?.edit}
                                            data-native="true"
                                            className="text-teal-600 hover:text-teal-500"
                                        >
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={6} className="px-4 py-6 text-center text-slate-500">
                                    No gateways found.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
