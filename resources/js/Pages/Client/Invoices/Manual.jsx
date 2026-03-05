import React from 'react';
import { Head } from '@inertiajs/react';

export default function Manual({
    invoice = {},
    attempt = {},
    gateway = {},
    payment_instructions = '',
    form = {},
    routes = {},
}) {
    return (
        <>
            <Head title="Manual Payment" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Manual Payment</h1>
                    <p className="mt-1 text-sm text-slate-500">Submit transfer details so we can verify your payment.</p>
                </div>
                <a href={routes?.back} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to invoice
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Invoice</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">Invoice #{invoice.number_display}</div>
                    <div className="mt-4 space-y-2 text-sm text-slate-600">
                        <div>
                            <span className="text-slate-500">Total:</span> {invoice.total_display}
                        </div>
                        <div>
                            <span className="text-slate-500">Due date:</span> {invoice.due_date_display}
                        </div>
                        <div>
                            <span className="text-slate-500">Service:</span> {invoice.service_name}
                        </div>
                    </div>

                    <div className="mt-4 text-xs text-slate-500">
                        <div>
                            <span className="font-semibold text-slate-700">Account name:</span> {gateway.account_name}
                        </div>
                        <div>
                            <span className="font-semibold text-slate-700">Account number:</span> {gateway.account_number}
                        </div>
                        <div>
                            <span className="font-semibold text-slate-700">Bank name:</span> {gateway.bank_name}
                        </div>
                        <div>
                            <span className="font-semibold text-slate-700">Branch:</span> {gateway.branch}
                        </div>
                        <div>
                            <span className="font-semibold text-slate-700">Routing:</span> {gateway.routing_number}
                        </div>
                    </div>

                    {gateway.instructions ? <div className="mt-4 whitespace-pre-line text-xs text-slate-500">{gateway.instructions}</div> : null}
                    {payment_instructions ? <div className="mt-4 whitespace-pre-line text-xs text-slate-500">{payment_instructions}</div> : null}
                </div>

                <div className="card p-6">
                    <div className="section-label">Payment Submission</div>
                    <form method="POST" action={routes?.store} encType="multipart/form-data" className="mt-4 space-y-4" data-native="true">
                        <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                        <div>
                            <label className="text-sm text-slate-600">Reference / Transaction ID</label>
                            <input
                                name="reference"
                                defaultValue={form.reference || ''}
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                placeholder="e.g. TRX123456"
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Amount paid</label>
                            <input
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={form.amount || ''}
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Payment date</label>
                            <input
                                name="paid_at"
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                defaultValue={form.paid_at || ''}
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Notes</label>
                            <textarea
                                name="notes"
                                rows="3"
                                defaultValue={form.notes || ''}
                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                                placeholder="Add any extra details"
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Transfer receipt image</label>
                            <input name="receipt" type="file" accept="image/*" className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
                            <p className="mt-2 text-xs text-slate-500">Upload a clear screenshot or photo of the transfer.</p>
                        </div>
                        <button type="submit" className="w-full rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white">
                            Submit for verification
                        </button>
                    </form>
                </div>
            </div>

            {(attempt.proofs || []).length > 0 ? (
                <div className="card mt-6 p-6">
                    <div className="section-label">Previous Submissions</div>
                    <div className="mt-4 space-y-4 text-sm text-slate-600">
                        {(attempt.proofs || []).map((proof) => (
                            <div key={proof.id} className="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="font-semibold text-slate-900">Status: {proof.status_label}</div>
                                        <div className="text-xs text-slate-500">Amount: {proof.amount_display}</div>
                                        <div className="text-xs text-slate-500">Reference: {proof.reference}</div>
                                    </div>
                                    {proof.attachment_url ? (
                                        <a href={proof.attachment_url} target="_blank" rel="noopener noreferrer" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                            View receipt
                                        </a>
                                    ) : null}
                                    {!proof.attachment_url && proof.has_attachment_path ? (
                                        <span className="text-xs font-semibold text-slate-400">Receipt unavailable</span>
                                    ) : null}
                                </div>
                                {proof.notes ? <div className="mt-2 text-xs text-slate-500">{proof.notes}</div> : null}
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}
        </>
    );
}
