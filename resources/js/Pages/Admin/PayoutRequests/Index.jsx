import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import Pagination from '../../../Components/Table/Pagination';
import { taka } from '../../../Components/Commission/CommissionStatement';
import PayoutRequestActions from '../../../Components/Commission/PayoutRequestActions';

const statusBadge = {
    pending: ['Waiting', 'border-amber-200 bg-amber-50 text-amber-700'],
    paid: ['Paid', 'border-emerald-200 bg-emerald-50 text-emerald-700'],
    rejected: ['Declined', 'border-rose-200 bg-rose-50 text-rose-700'],
    cancelled: ['Cancelled by rep', 'border-slate-200 bg-slate-100 text-slate-600'],
};

function RequestCard({ item, csrf, paymentMethods }) {
    const [label, badge] = statusBadge[item.status] || statusBadge.cancelled;
    const pending = item.status === 'pending';
    const short = pending && Number(item.payable_now || 0) + 0.009 < Number(item.amount || 0);

    return (
        <div className={`px-4 py-4 sm:px-5 ${pending ? 'bg-white' : 'bg-slate-50/40'}`}>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        {item.rep_url ? (
                            <a href={item.rep_url} data-native="true" className="font-semibold text-slate-900 hover:text-teal-600">{item.rep_name}</a>
                        ) : (
                            <span className="font-semibold text-slate-900">{item.rep_name}</span>
                        )}
                        <span className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${badge}`}>{label}</span>
                        <span className="text-xs text-slate-400">#{item.id}</span>
                    </div>
                    <div className="mt-0.5 text-xs text-slate-500">
                        Requested {item.requested_at}
                        {item.note ? <> · <span className="text-slate-700">“{item.note}”</span></> : null}
                    </div>
                    {item.status === 'paid' ? (
                        <div className="mt-1 text-xs text-emerald-700">
                            Paid {taka(item.paid_amount)} by <strong>{item.method || '--'}</strong>
                            {item.reference ? ` · ref ${item.reference}` : ''} · {item.processed_at}
                            {item.processed_by ? ` · ${item.processed_by}` : ''}
                        </div>
                    ) : null}
                    {!pending && item.status !== 'paid' ? (
                        <div className="mt-1 text-xs text-slate-600">
                            {item.processed_at}{item.processed_by ? ` · ${item.processed_by}` : ''}{item.admin_note ? ` · ${item.admin_note}` : ''}
                        </div>
                    ) : null}
                </div>

                <div className="shrink-0 text-left sm:text-right">
                    <div className="text-xl font-bold tabular-nums text-slate-900">{taka(item.amount)}</div>
                    {pending ? (
                        <div className={`text-xs ${short ? 'font-semibold text-rose-700' : 'text-slate-500'}`}>
                            Payable now {taka(item.payable_now)}
                        </div>
                    ) : null}
                </div>
            </div>

            {pending ? (
                <div className="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <PayoutRequestActions request={item} csrf={csrf} paymentMethods={paymentMethods} />
                </div>
            ) : null}
        </div>
    );
}

export default function Index({
    pageTitle = 'Payout Requests',
    status = 'pending',
    search = '',
    tabs = [],
    waiting_total: waitingTotal = 0,
    requests = [],
    paymentMethods = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const error = props?.errors?.payout_request;
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
        extraData: { status },
    });
    const waitingCount = tabs.find((tab) => tab.key === 'pending')?.count || 0;

    const tabHref = (key) => {
        const params = new URLSearchParams();
        params.set('status', key);
        if (search) params.set('search', search);
        return `${routes?.index}?${params.toString()}`;
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <div className="section-label">Sales</div>
                    <h1 className="text-2xl font-semibold text-slate-900">Payout Requests</h1>
                    <p className="text-sm text-slate-500">Requests from sales reps for the commission available to them.</p>
                </div>
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2 text-right">
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-amber-700">Waiting</div>
                    <div className="text-lg font-bold tabular-nums text-amber-800">
                        {waitingCount} · {taka(waitingTotal)}
                    </div>
                </div>
            </div>

            {error ? <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div> : null}

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex flex-wrap gap-2">
                        {tabs.map((tab) => (
                            <a
                                key={tab.key}
                                href={tabHref(tab.key)}
                                data-native="true"
                                className={`rounded-full border px-3 py-1 text-xs font-semibold ${status === tab.key ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50'}`}
                            >
                                {tab.label} <span className="ml-1 text-slate-400">{tab.count}</span>
                            </a>
                        ))}
                    </div>
                    <form
                        method="GET"
                        action={routes?.index}
                        className="w-full max-w-xs"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <input
                            type="text"
                            name="search"
                            value={searchTerm}
                            onChange={(event) => setSearchTerm(event.target.value)}
                            placeholder="Search sales rep"
                            className="w-full rounded-full border border-slate-300 px-4 py-2 text-sm"
                        />
                    </form>
                </div>

                {requests.length === 0 ? (
                    <div className="px-4 py-10 text-center text-sm text-slate-500">
                        {status === 'pending' ? 'No payout requests are waiting.' : 'No payout requests here.'}
                    </div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {requests.map((item) => (
                            <RequestCard key={item.id} item={item} csrf={csrf} paymentMethods={paymentMethods} />
                        ))}
                    </div>
                )}

                <Pagination pagination={pagination} label="requests" className="border-t border-slate-200 px-4 py-3" />
            </div>
        </>
    );
}
