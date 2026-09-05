import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form
                        id="paymentProofsSearchForm"
                        method="GET"
                        action={routes?.index}
                        className="flex items-center gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <input type="hidden" name="status" value={status} />
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search payment proofs..."
                                className="ui-input"
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
                    <div className="card overflow-hidden">
                        <DataTable
                            rows={payment_proofs}
                            columns={[
                                {
                                    key: 'invoice',
                                    header: 'Invoice',
                                    cellClassName: 'font-medium text-slate-900',
                                    render: (proof) => (
                                        proof.invoice_url ? (
                                            <a href={proof.invoice_url} data-native="true" className="hover:text-teal-600">{proof.invoice_number}</a>
                                        ) : <span>{proof.invoice_number}</span>
                                    ),
                                },
                                { key: 'customer', header: 'Customer', cellClassName: 'text-slate-600', render: (proof) => proof.customer_name },
                                { key: 'gateway', header: 'Gateway', cellClassName: 'text-slate-600', render: (proof) => proof.gateway_name },
                                { key: 'amount', header: 'Amount', cellClassName: 'text-slate-700', render: (proof) => proof.amount_display },
                                { key: 'reference', header: 'Reference', cellClassName: 'text-slate-500', render: (proof) => proof.reference },
                                {
                                    key: 'status',
                                    header: 'Status',
                                    render: (proof) => <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(proof.status)}`}>{proof.status_label}</span>,
                                },
                                { key: 'submitted', header: 'Submitted', cellClassName: 'text-slate-500', render: (proof) => proof.submitted_at_display },
                                {
                                    key: 'actions',
                                    header: 'Actions',
                                    headerClassName: 'text-right',
                                    cellClassName: 'text-right',
                                    render: (proof) => (
                                        <div className="flex flex-wrap items-center justify-end gap-2 text-xs">
                                            {proof.has_receipt ? (
                                                <a href={proof?.routes?.receipt} target="_blank" rel="noopener" className="rounded-full border border-slate-300 px-3 py-1 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">View receipt</a>
                                            ) : null}
                                            {proof.can_review ? (
                                                <>
                                                    <form method="POST" action={proof?.routes?.approve} data-native="true">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">Approve</button>
                                                    </form>
                                                    <form method="POST" action={proof?.routes?.reject} data-native="true">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 hover:border-rose-300">Reject</button>
                                                    </form>
                                                </>
                                            ) : proof.reviewer_name ? (
                                                <span className="text-xs text-slate-400">Reviewed by {proof.reviewer_name}</span>
                                            ) : null}
                                        </div>
                                    ),
                                },
                            ]}
                            renderMobileCard={(proof) => (
                                <MobileCard
                                    title={proof.invoice_url ? (
                                        <a href={proof.invoice_url} data-native="true" className="hover:text-teal-600">{proof.invoice_number}</a>
                                    ) : proof.invoice_number}
                                    subtitle={`${proof.customer_name} · ${proof.gateway_name}`}
                                    badge={proof.status_label}
                                    badgeColor={statusClass(proof.status)}
                                    metrics={[
                                        { label: 'Amount', value: proof.amount_display },
                                        { label: 'Submitted', value: proof.submitted_at_display },
                                    ]}
                                    actions={
                                        <div className="flex flex-wrap gap-2 w-full">
                                            {proof.has_receipt ? (
                                                <a
                                                    href={proof?.routes?.receipt}
                                                    target="_blank"
                                                    rel="noopener"
                                                    className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                                >
                                                    View receipt
                                                </a>
                                            ) : null}
                                            {proof.can_review ? (
                                                <>
                                                    <form method="POST" action={proof?.routes?.approve} data-native="true" className="flex-1">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="w-full py-2 px-3 rounded-xl bg-emerald-600 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition active:scale-95">Approve</button>
                                                    </form>
                                                    <form method="POST" action={proof?.routes?.reject} data-native="true" className="flex-1">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button type="submit" className="w-full py-2 px-3 rounded-xl border border-rose-200 bg-rose-50 text-xs font-bold text-rose-600 hover:bg-rose-100 transition active:scale-95">Reject</button>
                                                    </form>
                                                </>
                                            ) : proof.reviewer_name ? (
                                                <span className="text-xs text-slate-400">Reviewed by {proof.reviewer_name}</span>
                                            ) : null}
                                        </div>
                                    }
                                >
                                    {proof.reference ? <div className="text-xs text-slate-500">Ref: {proof.reference}</div> : null}
                                </MobileCard>
                            )}
                        />
                    </div>
                )}
            </div>
        </>
    );
}
