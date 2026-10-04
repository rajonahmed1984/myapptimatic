import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { taka } from '../../../Components/Commission/CommissionStatement';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusClass = (label) => {
    const key = String(label || '').toLowerCase().trim();
    if (key.includes('paid') || key.includes('complete')) return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    if (key.includes('process') || key.includes('payable')) return 'border-blue-200 bg-blue-50 text-blue-700';
    if (key.includes('pending') || key.includes('ongoing') || key.includes('hold')) return 'border-amber-200 bg-amber-50 text-amber-700';
    if (key.includes('reverse') || key.includes('cancel') || key.includes('reject')) return 'border-rose-200 bg-rose-50 text-rose-700';
    return 'border-slate-200 bg-slate-100 text-slate-700';
};

const requestStatus = {
    pending: { label: 'Waiting for admin', className: 'border-amber-200 bg-amber-50 text-amber-700' },
    paid: { label: 'Paid', className: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    rejected: { label: 'Declined', className: 'border-rose-200 bg-rose-50 text-rose-700' },
    cancelled: { label: 'Cancelled', className: 'border-slate-200 bg-slate-100 text-slate-600' },
};

function AvailablePayout({ availability = {}, action, csrf, errors = {} }) {
    const available = Number(availability.available || 0);
    const negative = available < -0.009;
    const canRequest = Boolean(availability.can_request);

    return (
        <div className={`overflow-hidden rounded-2xl border shadow-sm ${negative ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-white'}`}>
            <div className="grid gap-4 p-5 md:grid-cols-[1fr_1.3fr]">
                <div>
                    <div className="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Available payout</div>
                    <div className={`mt-1 text-3xl font-bold tabular-nums ${negative ? 'text-rose-700' : 'text-emerald-700'}`}>
                        {negative ? '−' : ''}{taka(available)}
                    </div>
                    <div className="mt-1 text-xs text-slate-600">
                        {negative
                            ? 'You have received this much more than the commission clients have paid for. It is settled from future commission.'
                            : 'Commission clients have paid for, on finished work, that you have not received yet.'}
                    </div>
                    {Number(availability.on_hold || 0) > 0 ? (
                        <div className="mt-2 text-xs text-slate-500">
                            {taka(availability.on_hold)} more becomes available when the related projects are completed.
                        </div>
                    ) : null}
                </div>

                <div className="rounded-xl border border-slate-200 bg-white p-4">
                    {canRequest ? (
                        <form method="POST" action={action} data-native="true" className="space-y-3">
                            <input type="hidden" name="_token" value={csrf} />
                            <div className="text-sm font-semibold text-slate-800">Request a payout</div>
                            <label className="block">
                                <span className="mb-1 block text-xs text-slate-500">Amount (up to {taka(available)})</span>
                                <input
                                    type="number"
                                    name="amount"
                                    required
                                    min="0.01"
                                    max={available}
                                    step="0.01"
                                    defaultValue={available.toFixed(2)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs text-slate-500">Note for the admin (optional)</span>
                                <input type="text" name="note" maxLength={1000} placeholder="e.g. Please send to my bKash" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                            </label>
                            {errors?.amount ? <div className="text-xs text-rose-600">{errors.amount}</div> : null}
                            <button type="submit" className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Request payout
                            </button>
                        </form>
                    ) : (
                        <div className="space-y-1">
                            <div className="text-sm font-semibold text-slate-800">Request a payout</div>
                            <div className="text-sm text-slate-600">{availability.reason}</div>
                            {errors?.amount ? <div className="text-xs text-rose-600">{errors.amount}</div> : null}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function PayoutRequests({ requests = [], csrf }) {
    if (!requests.length) {
        return null;
    }

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3 text-sm font-semibold text-slate-800">Your payout requests</div>
            <div className="divide-y divide-slate-100">
                {requests.map((item) => {
                    const status = requestStatus[item.status] || requestStatus.cancelled;

                    return (
                        <div key={item.id} className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold tabular-nums text-slate-900">{taka(item.amount)}</span>
                                    <span className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${status.className}`}>{status.label}</span>
                                </div>
                                <div className="text-xs text-slate-500">
                                    Requested {item.requested_at}
                                    {item.note ? ` · “${item.note}”` : ''}
                                </div>
                                {item.status === 'paid' ? (
                                    <div className="mt-0.5 text-xs text-emerald-700">
                                        Paid {taka(item.paid_amount)} by <strong>{item.method || '--'}</strong>
                                        {item.reference ? ` (ref ${item.reference})` : ''} on {item.processed_at}
                                    </div>
                                ) : null}
                                {item.admin_note && item.status !== 'pending' ? (
                                    <div className="mt-0.5 text-xs text-slate-600">Admin: {item.admin_note}</div>
                                ) : null}
                            </div>
                            {item.cancel_url ? (
                                <form method="POST" action={item.cancel_url} data-native="true">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <button type="submit" className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        Cancel request
                                    </button>
                                </form>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default function Index({ availability = {}, payout_requests: payoutRequests = [], payouts = [], pagination = {}, routes = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const errors = props?.errors || {};
    const payoutTotal = payouts.reduce((sum, payout) => sum + Number(payout?.total_amount || 0), 0);
    const payoutCurrency = payouts.find((payout) => payout?.currency)?.currency || 'BDT';

    return (
        <>
            <Head title="My Payouts" />

            <div className="space-y-6">
                <AvailablePayout availability={availability} action={routes?.request} csrf={csrf} errors={errors} />

                <PayoutRequests requests={payoutRequests} csrf={csrf} />

                <div>
                    <DataTable
                        header="Payout history"
                        rows={payouts}
                        emptyMessage="No payouts yet."
                        columns={[
                            { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (payout) => `#${payout.id}` },
                            { key: 'type', header: 'Type', render: (payout) => payout.type_label },
                            { key: 'amount', header: 'Amount', cellClassName: 'text-sm text-slate-600', render: (payout) => `${Number(payout.total_amount || 0).toFixed(2)} ${payout.currency}` },
                            {
                                key: 'status',
                                header: 'Status',
                                render: (payout) => (
                                    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(payout.status_label)}`}>
                                        {payout.status_label}
                                    </span>
                                ),
                            },
                            { key: 'method', header: 'Method', cellClassName: 'text-sm text-slate-600', render: (payout) => payout.payout_method },
                            { key: 'paid_at', header: 'Paid at', cellClassName: 'text-sm text-slate-500', render: (payout) => payout.paid_at_display },
                        ]}
                        footer={
                            <tr>
                                <td colSpan={2} className="px-4 py-3 font-bold text-slate-800">Total</td>
                                <td className="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">
                                    {payoutTotal.toFixed(2)} {payoutCurrency}
                                </td>
                                <td colSpan={3} className="px-4 py-3" />
                            </tr>
                        }
                        mobileFooter={
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm text-sm">
                                <span className="font-semibold text-slate-800">Total</span>
                                <span className="text-slate-700">Amount: <strong className="text-slate-900">{payoutTotal.toFixed(2)} {payoutCurrency}</strong></span>
                            </div>
                        }
                        renderMobileCard={(payout) => (
                            <MobileCard
                                title={`#${payout.id} · ${payout.type_label}`}
                                subtitle={payout.payout_method}
                                badge={payout.status_label}
                                badgeColor={statusClass(payout.status_label)}
                                metrics={[
                                    { label: 'Amount', value: `${Number(payout.total_amount || 0).toFixed(2)} ${payout.currency}` },
                                    { label: 'Paid at', value: payout.paid_at_display || '--' },
                                ]}
                            />
                        )}
                    />

                    <Pagination
                        pagination={pagination}
                        label="payouts"
                        className="border-t border-slate-200 px-4 py-3"
                    />
                </div>
            </div>
        </>
    );
}
