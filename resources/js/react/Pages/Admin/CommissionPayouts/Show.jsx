import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({ pageTitle = 'Commission Payout', payout = {}, earnings = [], payout_methods = [], routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-6xl space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <h1 className="text-xl font-semibold text-slate-900">Payout #{payout?.id}</h1>
                        <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                            Back to payouts
                        </a>
                    </div>
                    <div className="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                        <p>Sales Rep: {payout?.sales_rep_name || '--'}</p>
                        <p>Status: {payout?.status_label || '--'}</p>
                        <p>Type: {payout?.type_label || '--'}</p>
                        <p>Total: {payout?.total_amount_display || '--'}</p>
                        <p>Method: {payout?.payout_method || '--'}</p>
                        <p>Reference: {payout?.reference || '--'}</p>
                        <p>Paid At: {payout?.paid_at_display || '--'}</p>
                        <p>Project: {payout?.project_name || '--'}</p>
                    </div>
                    {payout?.note ? <p className="mt-4 text-sm text-slate-600">{payout.note}</p> : null}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Mark Paid</h2>
                    <form action={routes?.mark_paid} method="POST" data-native="true" className="grid gap-4 md:grid-cols-3">
                        <input type="hidden" name="_token" value={csrf} />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Reference</label>
                            <input name="reference" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Payout Method</label>
                            <select name="payout_method" className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Select method</option>
                                {payout_methods.map((method) => (
                                    <option key={method.code} value={method.code}>
                                        {method.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Note</label>
                            <input name="note" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div className="md:col-span-3">
                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                Mark Paid
                            </button>
                        </div>
                    </form>
                    {errors?.payout ? <p className="mt-2 text-xs text-rose-600">{errors.payout}</p> : null}
                </div>

                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h2 className="mb-3 text-lg font-semibold text-amber-900">Reverse Payout</h2>
                    <form action={routes?.reverse} method="POST" data-native="true" className="space-y-3">
                        <input type="hidden" name="_token" value={csrf} />
                        <input name="note" placeholder="Reason" className="w-full rounded-lg border border-amber-300 px-3 py-2" />
                        <button type="submit" className="rounded-lg border border-amber-400 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                            Reverse
                        </button>
                    </form>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Earnings</h2>
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2">Date</th>
                                        <th className="px-3 py-2">Customer</th>
                                        <th className="px-3 py-2">Project</th>
                                        <th className="px-3 py-2">Invoice</th>
                                        <th className="px-3 py-2">Commission</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {earnings.map((earning) => (
                                        <tr key={earning.id} className="border-t border-slate-100">
                                            <td className="px-3 py-2">{earning.earned_at_display}</td>
                                            <td className="px-3 py-2">{earning.customer_name}</td>
                                            <td className="px-3 py-2">{earning.project_name}</td>
                                            <td className="px-3 py-2">{earning.invoice_label}</td>
                                            <td className="px-3 py-2 font-semibold">{earning.commission_display}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
