import React from 'react';
import { Head } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'suspended') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-slate-100 text-slate-700';
};

export default function Index({
    pageTitle = 'Affiliates',
    filters = {},
    status_options = [],
    routes = {},
    affiliates = [],
    pagination = {},
}) {
    const hasFilters = Boolean((filters?.search ?? '') || (filters?.status ?? ''));

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Affiliate Management</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Manage affiliates</h1>
                    <p className="mt-2 text-sm text-slate-600">Track and manage your affiliate partners.</p>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white transition hover:bg-teal-400"
                >
                    Add affiliate
                </a>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-6 flex flex-wrap gap-4">
                    <input
                        type="text"
                        name="search"
                        defaultValue={filters?.search ?? ''}
                        placeholder="Search by name, email, or code..."
                        className="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    />
                    <select
                        name="status"
                        defaultValue={filters?.status ?? ''}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    >
                        {status_options.map((option) => (
                            <option key={option.value || 'all'} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <button type="submit" className="rounded-full bg-slate-900 px-6 py-2 text-sm font-semibold text-white">
                        Filter
                    </button>
                    {hasFilters ? (
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-600"
                        >
                            Clear
                        </a>
                    ) : null}
                </form>

                {affiliates.length === 0 ? (
                    <div className="rounded-xl border border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-600">
                        No affiliates found.
                    </div>
                ) : (
                    <>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[980px]">
                                <thead className="border-b border-slate-300 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="pb-3 font-semibold">Affiliate</th>
                                        <th className="pb-3 font-semibold">Code</th>
                                        <th className="pb-3 font-semibold">Status</th>
                                        <th className="pb-3 font-semibold">Commission</th>
                                        <th className="pb-3 font-semibold">Balance</th>
                                        <th className="pb-3 font-semibold">Referrals</th>
                                        <th className="pb-3 font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {affiliates.map((affiliate) => (
                                        <tr key={affiliate.id} className="hover:bg-slate-50">
                                            <td className="py-4">
                                                <div className="font-semibold text-slate-900">{affiliate.customer_name}</div>
                                                <div className="text-xs text-slate-500">{affiliate.customer_email}</div>
                                            </td>
                                            <td className="py-4">
                                                <code className="rounded bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700">
                                                    {affiliate.affiliate_code}
                                                </code>
                                            </td>
                                            <td className="py-4">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(
                                                        affiliate.status,
                                                    )}`}
                                                >
                                                    {affiliate.status_label}
                                                </span>
                                            </td>
                                            <td className="py-4">{affiliate.commission_display}</td>
                                            <td className="py-4 font-semibold">{affiliate.balance_display}</td>
                                            <td className="py-4">{affiliate.referrals_display}</td>
                                            <td className="py-4">
                                                <a
                                                    href={affiliate?.routes?.show}
                                                    data-native="true"
                                                    className="font-semibold text-teal-600 hover:text-teal-500"
                                                >
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
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
                )}
            </div>
        </>
    );
}
