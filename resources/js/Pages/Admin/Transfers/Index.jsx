import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const BTN = {
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
    danger: 'rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-500',
};

const xTransferStatusClass = (status) => {
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    if (status === 'accepted') return 'bg-teal-100 text-teal-700';
    if (status === 'executed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'rejected' || status === 'cancelled' || status === 'expired') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ pageTitle = 'Ownership Transfers', transfers = [], pagination = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between">
                <div className="text-2xl font-semibold text-slate-900">Ownership Transfers</div>
            </div>

            <div className="card overflow-x-auto p-0">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">From</th>
                            <th className="px-4 py-3">To</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Scheduled For</th>
                            <th className="px-4 py-3">Created</th>
                            <th className="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transfers.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-4 py-6 text-center text-slate-500">
                                    No ownership transfers yet.
                                </td>
                            </tr>
                        ) : (
                            transfers.map((transfer) => (
                                <tr key={transfer.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-800">{transfer.project_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{transfer.from_customer_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{transfer.to_customer_name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${xTransferStatusClass(transfer.status)}`}>
                                            {transfer.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{transfer.scheduled_for}</td>
                                    <td className="px-4 py-3 text-slate-500">{transfer.created_at}</td>
                                    <td className="px-4 py-3 text-right">
                                        {transfer.can_cancel ? (
                                            <form action={transfer.routes?.cancel} method="POST" data-native="true">
                                                <input type="hidden" name="_token" value={csrf} />
                                                <button
                                                    type="submit"
                                                    className={BTN.danger}
                                                    onClick={(e) => {
                                                        if (!confirm('Cancel this ownership transfer?')) {
                                                            e.preventDefault();
                                                        }
                                                    }}
                                                >
                                                    Cancel
                                                </button>
                                            </form>
                                        ) : null}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>

                {pagination?.has_pages ? (
                    <div className="flex items-center justify-end gap-2 p-4 text-sm">
                        {pagination?.previous_url ? (
                            <a href={pagination.previous_url} data-native="true" className={BTN.secondary}>Previous</a>
                        ) : (
                            <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                        )}
                        {pagination?.next_url ? (
                            <a href={pagination.next_url} data-native="true" className={BTN.secondary}>Next</a>
                        ) : (
                            <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}
