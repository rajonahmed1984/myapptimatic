import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'paused') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
};

export default function Index({
    pageTitle = 'Project Maintenance',
    filters = {},
    maintenances = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form method="GET" action={routes?.index} data-native="true" className="flex items-center gap-3">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={filters?.search ?? ''}
                                placeholder="Search maintenance..."
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
                    Add maintenance
                </a>
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1000px] divide-y divide-slate-200 text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3 text-left">ID</th>
                                <th className="px-4 py-3 text-left">Project</th>
                                <th className="px-4 py-3 text-left">Customer</th>
                                <th className="px-4 py-3 text-left">Title</th>
                                <th className="px-4 py-3 text-left">Cycle</th>
                                <th className="px-4 py-3 text-left">Next Billing</th>
                                <th className="px-4 py-3 text-left">Status</th>
                                <th className="px-4 py-3 text-right">Amount</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-slate-700">
                            {maintenances.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-6 text-center text-slate-500">
                                        No maintenance plans yet.
                                    </td>
                                </tr>
                            ) : (
                                maintenances.map((maintenance) => (
                                    <tr key={maintenance.id} className="transition hover:bg-slate-50">
                                        <td className="px-4 py-3 font-semibold text-slate-900">#{maintenance.id}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-semibold text-slate-900">
                                                {maintenance.project_route ? (
                                                    <a
                                                        href={maintenance.project_route}
                                                        data-native="true"
                                                        className="text-teal-700 hover:text-teal-600"
                                                    >
                                                        {maintenance.project_name}
                                                    </a>
                                                ) : (
                                                    '--'
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {maintenance.customer_route ? (
                                                <a
                                                    href={maintenance.customer_route}
                                                    data-native="true"
                                                    className="text-teal-700 hover:text-teal-600"
                                                >
                                                    {maintenance.customer_name}
                                                </a>
                                            ) : (
                                                '--'
                                            )}
                                        </td>
                                        <td className="px-4 py-3">{maintenance.title}</td>
                                        <td className="px-4 py-3">{maintenance.billing_cycle_label}</td>
                                        <td className="px-4 py-3 text-xs text-slate-600">{maintenance.next_billing_date}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(
                                                    maintenance.status,
                                                )}`}
                                            >
                                                {maintenance.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold">{maintenance.amount_display}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2 text-xs font-semibold">
                                                <a href={maintenance.routes?.show} data-native="true" className="text-slate-700 hover:text-teal-600">
                                                    View
                                                </a>
                                                <a href={maintenance.routes?.edit} data-native="true" className="text-teal-700 hover:text-teal-600">
                                                    Edit
                                                </a>

                                                {maintenance.can_pause ? (
                                                    <form method="POST" action={maintenance.routes?.update} data-native="true">
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="PATCH" />
                                                        <input type="hidden" name="quick_status" value="1" />
                                                        <input type="hidden" name="status" value="paused" />
                                                        <button type="submit" className="text-amber-700 hover:text-amber-600">
                                                            Pause
                                                        </button>
                                                    </form>
                                                ) : null}

                                                {maintenance.can_resume ? (
                                                    <form method="POST" action={maintenance.routes?.update} data-native="true">
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="PATCH" />
                                                        <input type="hidden" name="quick_status" value="1" />
                                                        <input type="hidden" name="status" value="active" />
                                                        <button type="submit" className="text-emerald-700 hover:text-emerald-600">
                                                            Resume
                                                        </button>
                                                    </form>
                                                ) : null}

                                                {maintenance.can_cancel ? (
                                                    <form method="POST" action={maintenance.routes?.update} data-native="true">
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="PATCH" />
                                                        <input type="hidden" name="quick_status" value="1" />
                                                        <input type="hidden" name="status" value="cancelled" />
                                                        <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {pagination?.has_pages ? (
                <div className="mt-6 flex items-center justify-end gap-2 text-sm">
                    {pagination?.previous_url ? (
                        <a
                            href={pagination.previous_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Previous
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                    )}

                    {pagination?.next_url ? (
                        <a
                            href={pagination.next_url}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                        >
                            Next
                        </a>
                    ) : (
                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                    )}
                </div>
            ) : null}
        </>
    );
}
