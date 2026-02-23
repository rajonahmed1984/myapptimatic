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
                        Enable gateways and store credentials for manual and online payments.
                    </p>
                </div>
            </div>

            <div className="card overflow-x-auto">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Gateway</th>
                            <th className="px-4 py-3">Driver</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {gateways.length > 0 ? (
                            gateways.map((gateway) => (
                                <tr key={gateway.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 font-medium text-slate-900">{gateway.name}</td>
                                    <td className="px-4 py-3 text-slate-500">{gateway.driver}</td>
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
                                <td colSpan={4} className="px-4 py-6 text-center text-slate-500">
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
