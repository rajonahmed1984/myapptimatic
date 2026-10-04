import React from 'react';
import { taka } from './CommissionStatement';

/**
 * Pay or decline one waiting payout request. Used on the Payout Requests
 * page and on the sales rep's own admin page.
 */
export default function PayoutRequestActions({ request, csrf, paymentMethods = [] }) {
    const payable = Number(request?.payable_now || 0);

    return (
        <div className="space-y-3">
            {payable > 0.009 ? (
                <form method="POST" action={request?.routes?.approve} data-native="true" className="grid gap-3 md:grid-cols-6">
                    <input type="hidden" name="_token" value={csrf} />
                    <label className="md:col-span-1">
                        <span className="mb-1 block text-xs font-semibold text-slate-600">Pay (৳)</span>
                        <input
                            type="number"
                            name="amount"
                            required
                            min="0.01"
                            max={payable}
                            step="0.01"
                            defaultValue={payable.toFixed(2)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label className="md:col-span-1">
                        <span className="mb-1 block text-xs font-semibold text-slate-600">Paid by</span>
                        <select name="payout_method" required defaultValue="" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="" disabled>Method…</option>
                            {paymentMethods.map((method) => (
                                <option key={method.code} value={method.code}>{method.name}</option>
                            ))}
                        </select>
                    </label>
                    <label className="md:col-span-1">
                        <span className="mb-1 block text-xs font-semibold text-slate-600">Reference</span>
                        <input type="text" name="reference" placeholder="Txn ID" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <label className="md:col-span-2">
                        <span className="mb-1 block text-xs font-semibold text-slate-600">Note to rep</span>
                        <input type="text" name="admin_note" className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </label>
                    <div className="flex items-end md:col-span-1">
                        <button type="submit" className="w-full rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                            Mark as paid
                        </button>
                    </div>
                </form>
            ) : (
                <div className="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    Nothing is payable now (the rep's balance changed since the request, now {taka(request?.balance)}). Decline it with a note.
                </div>
            )}

            <form method="POST" action={request?.routes?.reject} data-native="true" className="flex flex-wrap items-center gap-2">
                <input type="hidden" name="_token" value={csrf} />
                <input
                    type="text"
                    name="admin_note"
                    placeholder="Reason for declining (shown to the rep)"
                    className="min-w-[14rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs"
                />
                <button type="submit" className="rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                    Decline
                </button>
            </form>
        </div>
    );
}
