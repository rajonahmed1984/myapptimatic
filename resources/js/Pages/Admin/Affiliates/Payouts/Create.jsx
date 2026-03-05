import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Create({
    pageTitle = 'Create Affiliate Payout',
    selected_affiliate_id = '',
    affiliates = [],
    selected_affiliate = null,
    approved_commissions = [],
    suggested_amount = 0,
    routes = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [selectedIds, setSelectedIds] = useState(approved_commissions.map((item) => item.id));

    const selectedTotal = useMemo(
        () =>
            approved_commissions
                .filter((item) => selectedIds.includes(item.id))
                .reduce((total, item) => total + Number(item.amount || 0), 0),
        [approved_commissions, selectedIds],
    );

    const toggleId = (id) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id],
        );
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Payout Management</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Create affiliate payout</h1>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    Back to payouts
                </a>
            </div>

            <div className="card p-6">
                <form method="GET" action={routes?.create} data-native="true" className="mb-6 flex flex-wrap gap-3">
                    <select
                        name="affiliate_id"
                        defaultValue={selected_affiliate_id}
                        className="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                    >
                        <option value="">Select affiliate</option>
                        {affiliates.map((affiliate) => (
                            <option key={affiliate.id} value={affiliate.id}>
                                {affiliate.name} ({affiliate.code}) - {affiliate.balance_display}
                            </option>
                        ))}
                    </select>
                    <button
                        type="submit"
                        className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                    >
                        Load commissions
                    </button>
                </form>

                {selected_affiliate ? (
                    <form method="POST" action={routes?.store} data-native="true" className="space-y-5">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="affiliate_id" value={selected_affiliate.id} />
                        {selectedIds.map((id) => (
                            <input key={id} type="hidden" name="commission_ids[]" value={id} />
                        ))}

                        <div className="rounded-xl border border-slate-300 bg-slate-50 p-4 text-sm text-slate-700">
                            <div className="font-semibold text-slate-900">{selected_affiliate.name}</div>
                            <div>Code: {selected_affiliate.code}</div>
                            <div>Balance: {selected_affiliate.balance_display}</div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <label className="block">
                                <span className="mb-1 block text-sm font-medium text-slate-700">Amount</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="amount"
                                    defaultValue={selectedTotal > 0 ? selectedTotal.toFixed(2) : Number(suggested_amount || 0).toFixed(2)}
                                    className="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm"
                                    required
                                />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-sm font-medium text-slate-700">Payment method</span>
                                <input
                                    type="text"
                                    name="payment_method"
                                    className="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm"
                                    placeholder="Bank transfer / bKash / PayPal"
                                />
                            </label>
                        </div>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Payment details</span>
                            <textarea
                                name="payment_details"
                                rows={3}
                                className="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm"
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-slate-700">Notes</span>
                            <textarea
                                name="notes"
                                rows={3}
                                className="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm"
                            />
                        </label>

                        <div>
                            <div className="mb-2 text-sm font-semibold text-slate-900">
                                Approved commissions ({approved_commissions.length})
                            </div>
                            {approved_commissions.length === 0 ? (
                                <div className="rounded-xl border border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                                    No approved commissions available for this affiliate.
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-xl border border-slate-200">
                                    <table className="w-full min-w-[800px] text-sm">
                                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                            <tr>
                                                <th className="px-3 py-2">Select</th>
                                                <th className="px-3 py-2">Description</th>
                                                <th className="px-3 py-2">Invoice</th>
                                                <th className="px-3 py-2">Order</th>
                                                <th className="px-3 py-2">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {approved_commissions.map((commission) => (
                                                <tr key={commission.id} className="border-t border-slate-100">
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedIds.includes(commission.id)}
                                                            onChange={() => toggleId(commission.id)}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">{commission.description}</td>
                                                    <td className="px-3 py-2">{commission.invoice_number}</td>
                                                    <td className="px-3 py-2">{commission.order_number}</td>
                                                    <td className="px-3 py-2">{commission.amount_display}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="mt-2 text-sm text-slate-600">
                                Selected total: <span className="font-semibold">${selectedTotal.toFixed(2)}</span>
                            </div>
                        </div>

                        <button
                            type="submit"
                            className="rounded-full bg-teal-600 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-500"
                        >
                            Create payout
                        </button>
                    </form>
                ) : (
                    <div className="rounded-xl border border-slate-300 bg-slate-50 p-6 text-sm text-slate-600">
                        Select an affiliate to load approved commissions and create payout.
                    </div>
                )}
            </div>
        </>
    );
}
