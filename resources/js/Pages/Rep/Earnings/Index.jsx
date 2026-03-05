import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ earnings = [], status = '', status_options = [], assigned_projects = [], pagination = {}, routes = {} }) {
    return (
        <>
            <Head title="My Earnings" />

            <div className="card space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="section-label">Commissions</div>
                        <h1 className="text-2xl font-semibold text-slate-900">Earnings</h1>
                        <div className="text-sm text-slate-500">Read-only view of your commission earnings.</div>
                    </div>
                    <a href={routes?.dashboard} data-native="true" className="text-sm text-slate-600 hover:text-slate-800">Dashboard</a>
                </div>

                <form method="GET" action={routes?.index} className="grid gap-3 md:grid-cols-4" data-native="true">
                    <div>
                        <label className="text-xs text-slate-500">Status</label>
                        <select name="status" defaultValue={status || ''} className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" onChange={(e) => e.target.form.submit()}>
                            <option value="">All</option>
                            {status_options.map((option) => <option key={option} value={option}>{String(option).charAt(0).toUpperCase() + String(option).slice(1)}</option>)}
                        </select>
                    </div>
                </form>

                {assigned_projects.length > 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="text-xs uppercase text-slate-500">Assigned project commissions</div>
                        <div className="mt-3 overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead><tr className="text-xs uppercase text-slate-500"><th className="px-2 py-2">Project</th><th className="px-2 py-2">Customer</th><th className="px-2 py-2">Amount</th></tr></thead>
                                <tbody>
                                    {assigned_projects.map((project) => (
                                        <tr key={project.id} className="border-t border-slate-200">
                                            <td className="px-2 py-2">#{project.id} - {project.name}</td>
                                            <td className="px-2 py-2">{project.customer_name}</td>
                                            <td className="px-2 py-2">{project.commission_amount !== null ? `${Number(project.commission_amount).toFixed(2)} ${project.currency}` : '--'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}

                <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                    <div className="mt-3 overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase text-slate-500">
                                    <th className="px-2 py-2">ID</th>
                                    <th className="px-2 py-2">Source</th>
                                    <th className="px-2 py-2">Customer</th>
                                    <th className="px-2 py-2">Paid amount</th>
                                    <th className="px-2 py-2">Commission</th>
                                    <th className="px-2 py-2">Status</th>
                                    <th className="px-2 py-2">Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                {earnings.length === 0 ? (
                                    <tr><td colSpan={7} className="px-2 py-3 text-slate-500">No earnings found.</td></tr>
                                ) : earnings.map((earning) => (
                                    <tr key={earning.id} className="border-t border-slate-200">
                                        <td className="px-2 py-2">#{earning.id}</td>
                                        <td className="px-2 py-2">{earning.source_type}{earning.source_label ? ` (${earning.source_label})` : ''}</td>
                                        <td className="px-2 py-2">{earning.customer_name}</td>
                                        <td className="px-2 py-2">{Number(earning.paid_amount || 0).toFixed(2)} {earning.currency}</td>
                                        <td className="px-2 py-2">{Number(earning.commission_amount || 0).toFixed(2)} {earning.currency}</td>
                                        <td className="px-2 py-2">{earning.status_label}</td>
                                        <td className="px-2 py-2">{earning.earned_at_display}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
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
