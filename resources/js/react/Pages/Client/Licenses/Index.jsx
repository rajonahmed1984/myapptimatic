import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ licenses = [], routes = {} }) {
    return (
        <>
            <Head title="Licenses" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Licenses</h1>
                    <p className="mt-1 text-sm text-slate-500">Track your licensed domains, plan level, and status.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {licenses.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No licenses found.</div>
            ) : (
                <div className="card overflow-hidden">
                    <table className="w-full min-w-[720px] text-left text-xs">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Site</th>
                                <th className="px-4 py-3">Product</th>
                                <th className="px-4 py-3">Plan</th>
                                <th className="px-4 py-3">Installed on</th>
                                <th className="px-4 py-3">Version</th>
                                <th className="px-4 py-3">License</th>
                                <th className="px-4 py-3">Is Premium</th>
                                <th className="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {licenses.map((license) => (
                                <tr key={license.id} className="border-b border-slate-100 text-sm">
                                    <td className="px-4 py-3 text-slate-500">{license.id}</td>
                                    <td className="px-4 py-3">
                                        {license.site_url ? (
                                            <a href={license.site_url} target="_blank" rel="noreferrer" className="text-slate-700 hover:text-teal-600">
                                                {license.domain}
                                            </a>
                                        ) : (
                                            <span className="text-slate-400">--</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{license.product_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{license.plan_name}</td>
                                    <td className="px-4 py-3 text-slate-500">{license.installed_on}</td>
                                    <td className="px-4 py-3 text-slate-400">-</td>
                                    <td className="px-4 py-3 font-mono text-xs text-slate-700">{license.masked_key}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={
                                                license.is_premium
                                                    ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700'
                                                    : 'rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500'
                                            }
                                        >
                                            {license.is_premium ? 'Yes' : 'No'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={
                                                license.is_active
                                                    ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700'
                                                    : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700'
                                            }
                                        >
                                            {license.status_label}
                                        </span>
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
