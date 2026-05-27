import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Create({
    pageTitle = 'Create Commission Payout',
    sales_reps = [],
    selected_rep = '',
    rep_balance = null,
    payout_methods = [],
    earnings = [],
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const salesRepOptions = [
        { value: '', label: 'Select rep' },
        ...sales_reps.map((rep) => ({ value: String(rep.id), label: `${rep.name} (${rep.status})` })),
    ];
    const payoutMethodOptions = [
        { value: '', label: 'Select method' },
        ...payout_methods.map((method) => ({ value: String(method.code), label: method.name })),
    ];

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-6xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to payouts
                    </a>
                </div>

                {rep_balance ? (
                    <div className="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        Net payable: <strong>{Number(rep_balance.payable_balance || 0).toFixed(2)}</strong>
                    </div>
                ) : null}

                <form action={routes?.store} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep</label>
                            <SearchableSelect
                                name="sales_rep_id"
                                defaultValue={String(selected_rep || '')}
                                options={salesRepOptions}
                                className="mt-1"
                                placeholder="Select rep"
                                error={errors?.sales_rep_id}
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Payout Method</label>
                            <SearchableSelect
                                name="payout_method"
                                defaultValue=""
                                options={payoutMethodOptions}
                                className="mt-1"
                                placeholder="Select method"
                                error={errors?.payout_method}
                            />
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Note</label>
                            <input name="note" className="ui-input" />
                            {errors?.note ? <p className="mt-1 text-xs text-rose-600">{errors.note}</p> : null}
                        </div>
                    </div>

                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-3 py-2">Select</th>
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
                                            <td className="px-3 py-2">
                                                <input type="checkbox" name="earning_ids[]" value={earning.id} />
                                            </td>
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

                    {errors?.earning_ids ? <p className="text-xs text-rose-600">{errors.earning_ids}</p> : null}
                    {errors?.payout ? <p className="text-xs text-rose-600">{errors.payout}</p> : null}

                    <button type="submit" className="ui-btn-primary">
                        Create Payout Draft
                    </button>
                </form>
            </div>
        </>
    );
}
