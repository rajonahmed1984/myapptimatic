import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (status) => {
    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }

    if (status === 'rejected') {
        return 'bg-rose-100 text-rose-700 border-rose-200';
    }

    if (status === 'pending') {
        return 'bg-amber-100 text-amber-700 border-amber-200';
    }

    return 'bg-slate-100 text-slate-600 border-slate-200';
};

export default function Index({
    pageTitle = 'Manual Payments',
    status = 'all',
    search = '',
    routes = {},
    filter_links = [],
    payment_proofs = [],
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form id="paymentProofsSearchForm" method="GET" action={routes?.index} className="flex items-center gap-3" data-native="true">
                        <input type="hidden" name="status" value={status} />
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search payment proofs..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                onInput={(event) => {
                                    const input = event.currentTarget;
                                    clearTimeout(input.__searchTimer);
                                    input.__searchTimer = setTimeout(() => input.form?.requestSubmit(), 300);
                                }}
                            />
                        </div>
                    </form>
                </div>
                <div className="flex items-center gap-2 text-xs">
                    {filter_links.map((filter) => (
                        <a
                            key={filter.key}
                            href={filter.href}
                            data-native="true"
                            className={
                                filter.active
                                    ? 'rounded-full bg-slate-900 px-3 py-1 text-white'
                                    : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600'
                            }
                        >
                            {filter.label}
                        </a>
                    ))}
                </div>
            </div>

            <div id="paymentProofsTable">
                {payment_proofs.length === 0 ? (
                    <div className="card p-6 text-sm text-slate-500">No manual payment submissions found.</div>
                ) : (
                    <div className="card overflow-x-auto">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Invoice</th>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Gateway</th>
                                    <th className="px-4 py-3">Amount</th>
                                    <th className="px-4 py-3">Reference</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Submitted</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payment_proofs.map((proof) => (
                                    <tr key={proof.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 font-medium text-slate-900">
                                            {proof.invoice_url ? (
                                                <a href={proof.invoice_url} data-native="true" className="hover:text-teal-600">
                                                    {proof.invoice_number}
                                                </a>
                                            ) : (
                                                <span>{proof.invoice_number}</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{proof.customer_name}</td>
                                        <td className="px-4 py-3 text-slate-600">{proof.gateway_name}</td>
                                        <td className="px-4 py-3 text-slate-700">{proof.amount_display}</td>
                                        <td className="px-4 py-3 text-slate-500">{proof.reference}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(proof.status)}`}>
                                                {proof.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{proof.submitted_at_display}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex flex-wrap items-center justify-end gap-2 text-xs">
                                                {proof.has_receipt ? (
                                                    <a
                                                        href={proof?.routes?.receipt}
                                                        target="_blank"
                                                        rel="noopener"
                                                        className="rounded-full border border-slate-300 px-3 py-1 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                                    >
                                                        View receipt
                                                    </a>
                                                ) : null}

                                                {proof.can_review ? (
                                                    <>
                                                        <form method="POST" action={proof?.routes?.approve} data-native="true">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <button type="submit" className="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">
                                                                Approve
                                                            </button>
                                                        </form>
                                                        <form method="POST" action={proof?.routes?.reject} data-native="true">
                                                            <input type="hidden" name="_token" value={csrfToken} />
                                                            <button
                                                                type="submit"
                                                                className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300"
                                                            >
                                                                Reject
                                                            </button>
                                                        </form>
                                                    </>
                                                ) : proof.reviewer_name ? (
                                                    <span className="text-xs text-slate-400">Reviewed by {proof.reviewer_name}</span>
                                                ) : null}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}