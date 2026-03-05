import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusBadgeClass = (status) => {
    if (status === 'paid') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'approved') {
        return 'bg-teal-100 text-teal-700';
    }

    if (status === 'cancelled') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-amber-100 text-amber-700';
};

export default function Index({
    pageTitle = 'Affiliate Commissions',
    filters = {},
    status_options = [],
    routes = {},
    affiliates = [],
    commissions = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [selectedIds, setSelectedIds] = useState([]);

    const hasFilters = useMemo(
        () => Boolean((filters?.affiliate_id ?? '') || (filters?.status ?? '')),
        [filters],
    );

    const toggleId = (id) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id],
        );
    };

    const toggleAll = () => {
        const pendingIds = commissions.filter((item) => item.can_decide).map((item) => item.id);
        if (pendingIds.length === 0) {
            return;
        }

        setSelectedIds((prev) =>
            prev.length === pendingIds.length ? [] : pendingIds,
        );
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Commission Management</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Affiliate commissions</h1>
                </div>
                <a
                    href={routes?.affiliates_index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back to affiliates
                </a>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.index} data-native="true" className="mb-6 flex flex-wrap gap-4">
                    <select
                        name="affiliate_id"
                        defaultValue={filters?.affiliate_id ?? ''}
                        className="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    >
                        <option value="">All affiliates</option>
                        {affiliates.map((affiliate) => (
                            <option key={affiliate.id} value={affiliate.id}>
                                {affiliate.name} ({affiliate.code})
                            </option>
                        ))}
                    </select>
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
                    <button
                        type="submit"
                        className="rounded-full bg-slate-900 px-6 py-2 text-sm font-semibold text-white"
                    >
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

                {commissions.length === 0 ? (
                    <div className="rounded-xl border border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-600">
                        No commissions found.
                    </div>
                ) : (
                    <>
                        <form method="POST" action={routes?.bulk_approve} data-native="true" className="mb-4">
                            <input type="hidden" name="_token" value={csrfToken} />
                            {selectedIds.map((id) => (
                                <input key={id} type="hidden" name="commission_ids[]" value={id} />
                            ))}
                            <div className="flex items-center justify-between gap-4">
                                <button
                                    type="button"
                                    onClick={toggleAll}
                                    className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                >
                                    Toggle pending
                                </button>
                                <button
                                    type="submit"
                                    disabled={selectedIds.length === 0}
                                    className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Approve selected ({selectedIds.length})
                                </button>
                            </div>
                        </form>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px]">
                                <thead className="border-b border-slate-300 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="pb-3 font-semibold">Select</th>
                                        <th className="pb-3 font-semibold">Date</th>
                                        <th className="pb-3 font-semibold">Affiliate</th>
                                        <th className="pb-3 font-semibold">Description</th>
                                        <th className="pb-3 font-semibold">Amount</th>
                                        <th className="pb-3 font-semibold">Status</th>
                                        <th className="pb-3 font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {commissions.map((commission) => (
                                        <tr key={commission.id}>
                                            <td className="py-4">
                                                {commission.can_decide ? (
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedIds.includes(commission.id)}
                                                        onChange={() => toggleId(commission.id)}
                                                    />
                                                ) : null}
                                            </td>
                                            <td className="py-4 text-slate-600">{commission.date_display}</td>
                                            <td className="py-4">
                                                <div className="font-semibold">{commission.affiliate_name}</div>
                                                <code className="text-xs text-slate-500">{commission.affiliate_code}</code>
                                            </td>
                                            <td className="py-4">{commission.description}</td>
                                            <td className="py-4 font-semibold">{commission.amount_display}</td>
                                            <td className="py-4">
                                                <span
                                                    className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(
                                                        commission.status,
                                                    )}`}
                                                >
                                                    {commission.status_label}
                                                </span>
                                            </td>
                                            <td className="py-4">
                                                {commission.can_decide ? (
                                                    <>
                                                        <form method="POST" action={commission?.routes?.approve} data-native="true" className="inline">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <button type="submit" className="text-teal-600 hover:text-teal-500">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <span className="mx-2 text-slate-300">|</span>
                                                        <form method="POST" action={commission?.routes?.reject} data-native="true" className="inline">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </>
                                                ) : null}
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
