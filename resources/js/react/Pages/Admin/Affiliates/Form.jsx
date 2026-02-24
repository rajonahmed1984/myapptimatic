import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({
    pageTitle = 'Affiliate',
    is_edit = false,
    affiliate = null,
    form = {},
    status_options = [],
    commission_type_options = [],
    customers = [],
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Affiliates</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">
                        {is_edit ? affiliate?.customer_name || 'Edit affiliate' : 'Create affiliate account'}
                    </h1>
                </div>
                <a
                    href={is_edit ? routes?.show : routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                >
                    {is_edit ? 'Back to affiliate' : 'Back to affiliates'}
                </a>
            </div>

            <div className="card p-8">
                <div className="section-label">{is_edit ? 'Edit Affiliate' : 'New Affiliate'}</div>

                <form method="POST" action={form?.action} data-native="true" className="mt-8 space-y-6">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <label className="text-sm text-slate-600">Customer *</label>
                            <select
                                name="customer_id"
                                required
                                defaultValue={fields?.customer_id ?? ''}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            >
                                <option value="">Select customer</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name} ({customer.email})
                                    </option>
                                ))}
                            </select>
                            {errors?.customer_id ? <p className="mt-1 text-xs text-rose-600">{errors.customer_id}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Status *</label>
                            <select
                                name="status"
                                required
                                defaultValue={fields?.status ?? 'active'}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            >
                                {status_options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Commission Type *</label>
                            <select
                                name="commission_type"
                                required
                                defaultValue={fields?.commission_type ?? 'percentage'}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            >
                                {commission_type_options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            {errors?.commission_type ? <p className="mt-1 text-xs text-rose-600">{errors.commission_type}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Commission Rate (%) *</label>
                            <input
                                type="number"
                                step="0.01"
                                name="commission_rate"
                                required
                                defaultValue={fields?.commission_rate ?? '10.00'}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                            {errors?.commission_rate ? <p className="mt-1 text-xs text-rose-600">{errors.commission_rate}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Fixed Commission Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                name="fixed_commission_amount"
                                defaultValue={fields?.fixed_commission_amount ?? ''}
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                            <p className="mt-1 text-xs text-slate-500">Only used if commission type is "Fixed Amount"</p>
                            {errors?.fixed_commission_amount ? (
                                <p className="mt-1 text-xs text-rose-600">{errors.fixed_commission_amount}</p>
                            ) : null}
                        </div>
                    </div>

                    <div>
                        <label className="text-sm text-slate-600">Payment Details</label>
                        <textarea
                            name="payment_details"
                            rows={3}
                            defaultValue={fields?.payment_details ?? ''}
                            className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        />
                        <p className="mt-1 text-xs text-slate-500">Bank account, PayPal email, or other payment information</p>
                        {errors?.payment_details ? <p className="mt-1 text-xs text-rose-600">{errors.payment_details}</p> : null}
                    </div>

                    <div>
                        <label className="text-sm text-slate-600">Notes</label>
                        <textarea
                            name="notes"
                            rows={3}
                            defaultValue={fields?.notes ?? ''}
                            className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                        />
                        {errors?.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                    </div>

                    <div className="flex justify-end gap-4 pt-6">
                        <a
                            href={is_edit ? routes?.show : routes?.index}
                            data-native="true"
                            className="rounded-full border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-600"
                        >
                            Cancel
                        </a>
                        <button type="submit" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white">
                            {is_edit ? 'Update affiliate' : 'Create affiliate'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
