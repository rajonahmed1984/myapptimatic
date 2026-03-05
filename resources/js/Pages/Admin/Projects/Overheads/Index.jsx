import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({ pageTitle = 'Overhead fees', project = null, overheads = [], unpaid_count = 0, routes = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Project</div>
                    <div className="text-lg font-semibold text-slate-900">{project?.name}</div>
                    <div className="text-xs text-slate-600">ID: {project?.id} | Status: {project?.status_label}</div>
                </div>
                <a href={routes?.project_show} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                    Back
                </a>
            </div>

            <div className="space-y-4 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm font-semibold text-slate-800">Overhead fees</div>
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Project remaining: {project?.remaining_budget_display}</div>
                </div>

                {overheads.length === 0 ? (
                    <div className="text-xs text-slate-500">No overhead line items yet.</div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="px-3 py-2">Invoice</th>
                                    <th className="px-3 py-2">Details</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Date</th>
                                    <th className="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {overheads.map((overhead) => (
                                    <tr key={overhead.id} className="border-t border-slate-100">
                                        <td className="px-3 py-2">
                                            {overhead.invoice_show_route ? (
                                                <a href={overhead.invoice_show_route} data-native="true" className="font-semibold text-teal-700 hover:text-teal-600">
                                                    {overhead.invoice_number}
                                                </a>
                                            ) : (
                                                <span className="text-xs text-slate-400">--</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 w-2/5">{overhead.details}</td>
                                        <td className="px-3 py-2 text-right">{overhead.amount_display}</td>
                                        <td className="px-3 py-2">{overhead.date}</td>
                                        <td className="px-3 py-2">{overhead.status_label}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="border-t border-slate-100 pt-4">
                    {unpaid_count > 0 ? (
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="text-xs text-slate-500">{unpaid_count} unpaid overhead{unpaid_count > 1 ? 's' : ''} can be invoiced.</div>
                            <form method="POST" action={routes?.invoice_pending} data-native="true">
                                <input type="hidden" name="_token" value={csrf} />
                                <button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-500">
                                    Invoice unpaid overheads
                                </button>
                            </form>
                        </div>
                    ) : null}

                    <form method="POST" action={routes?.store} data-native="true" className="space-y-3 text-xs text-slate-500">
                        <input type="hidden" name="_token" value={csrf} />
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="md:col-span-2">
                                <label className="text-xs text-slate-500">Details</label>
                                <input name="short_details" required className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Feature fee or description" />
                            </div>
                            <div>
                                <label className="text-xs text-slate-500">Amount</label>
                                <input name="amount" required type="number" step="0.01" min="0" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" />
                            </div>
                            <div className="md:col-span-3 flex justify-end">
                                <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                    Add overhead fee
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
