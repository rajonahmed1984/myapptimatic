import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

const statusClass = (status) => {
    if (status === 'accepted') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (status === 'rejected') return 'bg-rose-100 text-rose-700 border-rose-200';
    if (status === 'pending') return 'bg-amber-100 text-amber-700 border-amber-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};

const TABS = [
    { key: 'pending', label: 'Pending' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
];

function ReviewPanel({ request, csrfToken }) {
    const [mode, setMode] = useState(null);
    const immediate = request.type === 'immediate';

    if (!mode) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    onClick={() => setMode('accept')}
                    className="rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500"
                >
                    Accept
                </button>
                <button
                    type="button"
                    onClick={() => setMode('reject')}
                    className="rounded-full border border-rose-200 px-4 py-1.5 text-xs font-semibold text-rose-600 hover:border-rose-300"
                >
                    Reject
                </button>
            </div>
        );
    }

    const accepting = mode === 'accept';

    return (
        <form
            method="POST"
            action={accepting ? request?.routes?.accept : request?.routes?.reject}
            data-native="true"
            className="w-full space-y-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
        >
            <input type="hidden" name="_token" value={csrfToken} />

            <p className="text-xs font-semibold text-slate-700">
                {accepting
                    ? immediate
                        ? 'Accepting will cancel this service immediately. Open invoices stay payable.'
                        : `Accepting keeps the service running until ${request.period_end_display}, with no further invoices. Open invoices stay payable.`
                    : 'Tell the customer why the request was declined.'}
            </p>

            <textarea
                name="admin_note"
                rows={3}
                required={!accepting}
                placeholder={accepting ? 'Optional note for the customer' : 'Reason for declining (required)'}
                className="ui-input w-full"
            />

            <div className="flex flex-wrap items-center gap-2">
                <button
                    type="submit"
                    className={
                        accepting
                            ? 'rounded-full bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500'
                            : 'rounded-full bg-rose-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-rose-500'
                    }
                >
                    {accepting ? 'Confirm cancellation' : 'Confirm rejection'}
                </button>
                <button
                    type="button"
                    onClick={() => setMode(null)}
                    className="rounded-full border border-slate-300 px-4 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-400"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Index({ filters = {}, counts = {}, requests = [], currency = 'BDT' }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const indexUrl = '/admin/cancellation-requests';
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: filters?.search || '',
        url: indexUrl,
    });

    return (
        <>
            <Head title="Cancellation Requests" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-slate-900">Cancellation Requests</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Accepting stops future invoices. Anything already invoiced stays due.
                </p>
            </div>

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <form
                    method="GET"
                    action={indexUrl}
                    className="flex-1"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submitSearch();
                    }}
                >
                    <input type="hidden" name="status" value={filters?.status || 'pending'} />
                    <input
                        type="text"
                        name="search"
                        value={searchTerm}
                        onChange={(event) => setSearchTerm(event.target.value)}
                        placeholder="Search by customer, reason or ID..."
                        className="ui-input w-full max-w-sm"
                    />
                </form>

                <div className="flex items-center gap-2 text-xs">
                    {TABS.map((tab) => (
                        <a
                            key={tab.key}
                            href={`${indexUrl}?status=${tab.key}`}
                            data-native="true"
                            className={
                                (filters?.status || 'pending') === tab.key
                                    ? 'rounded-full bg-slate-900 px-3 py-1 text-white'
                                    : 'rounded-full border border-slate-300 px-3 py-1 text-slate-600 hover:border-teal-300 hover:text-teal-600'
                            }
                        >
                            {tab.label}
                            {counts?.[tab.key] ? ` (${counts[tab.key]})` : ''}
                        </a>
                    ))}
                </div>
            </div>

            {requests.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No cancellation requests here.</div>
            ) : (
                <div className="space-y-4">
                    {requests.map((request) => (
                        <div key={request.id} className="card p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="text-base font-semibold text-slate-900">{request.service_name}</h2>
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(request.status)}`}>
                                            {request.status_label}
                                        </span>
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                                            request.type === 'immediate'
                                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                                : 'border-slate-200 bg-slate-50 text-slate-600'
                                        }`}>
                                            {request.type_label}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {request.customer_name} · {request.customer_email} · {request.plan_name}
                                    </p>
                                </div>

                                <div className="text-right text-xs text-slate-500">
                                    <div>Requested {request.requested_at_display}</div>
                                    {request.routes?.subscription ? (
                                        <a
                                            href={request.routes.subscription}
                                            data-native="true"
                                            className="mt-1 inline-block font-semibold text-teal-600 hover:text-teal-500"
                                        >
                                            Open subscription #{request.subscription_id}
                                        </a>
                                    ) : null}
                                </div>
                            </div>

                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <div className="rounded-xl border border-slate-200 bg-white/70 p-3">
                                    <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Period ends</div>
                                    <div className="mt-1 text-sm font-semibold text-slate-800">{request.period_end_display}</div>
                                </div>
                                <div className={`rounded-xl border p-3 ${
                                    request.due_at_request > 0
                                        ? 'border-amber-200 bg-amber-50'
                                        : 'border-slate-200 bg-white/70'
                                }`}>
                                    <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Outstanding</div>
                                    <div className={`mt-1 text-sm font-semibold ${request.due_at_request > 0 ? 'text-amber-700' : 'text-slate-800'}`}>
                                        {currency} {Number(request.due_at_request).toFixed(2)}
                                    </div>
                                </div>
                                <div className="rounded-xl border border-slate-200 bg-white/70 p-3">
                                    <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Subscription</div>
                                    <div className="mt-1 text-sm font-semibold capitalize text-slate-800">{request.subscription_status}</div>
                                </div>
                            </div>

                            <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                                <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Customer's reason</div>
                                <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{request.reason}</p>
                            </div>

                            {request.admin_note ? (
                                <div className="mt-3 rounded-xl border border-slate-200 bg-white/70 p-3">
                                    <div className="text-[11px] uppercase tracking-[0.2em] text-slate-400">Admin note</div>
                                    <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{request.admin_note}</p>
                                </div>
                            ) : null}

                            <div className="mt-4">
                                {request.status === 'pending' ? (
                                    <ReviewPanel request={request} csrfToken={csrfToken} />
                                ) : (
                                    <p className="text-xs text-slate-400">
                                        {request.status_label} by {request.reviewer_name} on {request.reviewed_at_display}
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
