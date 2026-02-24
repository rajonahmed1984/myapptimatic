import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ has_customer = false, domains = [], routes = {} }) {
    return (
        <>
            <Head title="Domains" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Domains</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage licensed domains and monitor verification status.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {!has_customer ? (
                <div className="card p-6 text-sm text-slate-600">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            ) : domains.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No domains found.</div>
            ) : (
                <div className="card overflow-hidden">
                    <table className="w-full min-w-[860px] text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">Domain</th>
                                <th className="px-4 py-3">Product</th>
                                <th className="px-4 py-3">Plan</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Verified</th>
                                <th className="px-4 py-3">Last Seen</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {domains.map((domain) => (
                                <tr key={domain.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-900">{domain.domain}</div>
                                        <div className="text-xs text-slate-400">{domain.masked_key}</div>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{domain.product_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{domain.plan_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{domain.status_label}</td>
                                    <td className="px-4 py-3 text-slate-500">{domain.verified_display}</td>
                                    <td className="px-4 py-3 text-slate-500">{domain.last_seen_display}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3 text-xs">
                                            <a href={domain.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
