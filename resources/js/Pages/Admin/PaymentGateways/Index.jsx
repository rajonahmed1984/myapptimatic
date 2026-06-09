import React from 'react';
import { Head } from '@inertiajs/react';

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

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[1100px] text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Gateway</th>
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
                                        <div className="mt-1 text-xs text-slate-500">
                                            {gateway.details_display || '--'}
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
                                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
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
