import React from 'react';
import { Head } from '@inertiajs/react';

const xTransferStatusClass = (status) => {
    if (status === 'pending') return 'bg-amber-100 text-amber-700';
    if (status === 'accepted') return 'bg-teal-100 text-teal-700';
    if (status === 'executed') return 'bg-emerald-100 text-emerald-700';
    if (status === 'rejected' || status === 'cancelled' || status === 'expired') return 'bg-rose-100 text-rose-700';
    return 'bg-slate-100 text-slate-600';
};

export default function Index({ pageTitle = 'Incoming Transfers', transfers = [] }) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6">
                <div className="text-2xl font-semibold text-slate-900">Incoming Transfers</div>
                <div className="mt-1 text-sm text-slate-500">
                    Projects other customers have offered to transfer to your account. Check your email for the accept/reject link on each invite.
                </div>
            </div>

            <div className="card overflow-x-auto p-0">
                <table className="w-full min-w-[700px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">Item</th>
                            <th className="px-4 py-3">From</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Requested</th>
                        </tr>
                    </thead>
                    <tbody>
                        {transfers.length === 0 ? (
                            <tr>
                                <td colSpan={4} className="px-4 py-6 text-center text-slate-500">
                                    No incoming transfers.
                                </td>
                            </tr>
                        ) : (
                            transfers.map((transfer) => (
                                <tr key={transfer.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-800">{transfer.project_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{transfer.from_customer_name}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ${xTransferStatusClass(transfer.status)}`}>
                                            {transfer.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{transfer.created_at}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
