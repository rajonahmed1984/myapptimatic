import React from 'react';
import { Head } from '@inertiajs/react';

const statusBadgeClass = (status) =>
    status === 'active'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-slate-300 bg-slate-50 text-slate-600';

export default function Index({
    pageTitle = 'Sales Representatives',
    filters = {},
    reps = [],
    routes = {},
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form method="GET" action={routes?.index} data-native="true" className="flex items-center gap-3">
                        <div className="relative">
                            <input
                                type="text"
                                name="search"
                                defaultValue={filters?.search ?? ''}
                                placeholder="Search sales reps..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </form>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                >
                    Add sales rep
                </a>
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] divide-y divide-slate-200 text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">ID</th>
                                <th className="px-4 py-3 text-left">Name</th>
                                <th className="px-4 py-3 text-left">Services</th>
                                <th className="px-4 py-3 text-right">Projects & Maintenance</th>
                                <th className="px-4 py-3 text-left">Login</th>
                                <th className="px-4 py-3 text-right">Total earned</th>
                                <th className="px-4 py-3 text-right">Payable (Net)</th>
                                <th className="px-4 py-3 text-right">Paid (Incl. Advance)</th>
                                <th className="px-4 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {reps.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-6 text-center text-slate-500">
                                        No sales representatives yet.
                                    </td>
                                </tr>
                            ) : (
                                reps.map((rep) => (
                                    <tr key={rep.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-semibold text-slate-900">{rep.id}</td>
                                        <td className="px-4 py-3">
                                            <div>
                                                <div className="font-semibold text-slate-900">
                                                    <a href={rep.routes?.show} data-native="true" className="hover:text-teal-600">
                                                        {rep.name}
                                                    </a>
                                                </div>
                                                <div className="text-xs text-slate-500">{rep.email || '--'}</div>
                                                {rep.employee_name ? (
                                                    <div className="text-xs text-emerald-600">Employee: {rep.employee_name}</div>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="text-sm text-slate-700">
                                                {rep.active_subscriptions_count} ({rep.subscriptions_count})
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="text-sm text-slate-700">Projects: {rep.projects_count}</div>
                                            <div className="text-xs text-slate-500">Maintenance: {rep.maintenances_count}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="text-[11px] text-slate-400">Last login: {rep.last_login_label}</div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold">{rep.total_earned}</td>
                                        <td className="px-4 py-3 text-right">{rep.total_payable}</td>
                                        <td className="px-4 py-3 text-right">{rep.total_paid}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(rep.status)}`}>
                                                {rep.status_label}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
