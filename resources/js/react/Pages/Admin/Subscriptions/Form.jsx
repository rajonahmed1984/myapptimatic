import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({
    pageTitle = 'Subscription',
    is_edit = false,
    customers = [],
    plans = [],
    sales_reps = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to list
                    </a>
                </div>

                <form action={form?.action} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                            <select name="customer_id" defaultValue={fields?.customer_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Select customer</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name}
                                    </option>
                                ))}
                            </select>
                            {errors?.customer_id ? <p className="mt-1 text-xs text-rose-600">{errors.customer_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Plan</label>
                            <select name="plan_id" defaultValue={fields?.plan_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Select plan</option>
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.product_name} - {plan.name} ({plan.interval})
                                    </option>
                                ))}
                            </select>
                            {errors?.plan_id ? <p className="mt-1 text-xs text-rose-600">{errors.plan_id}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep</label>
                            <select name="sales_rep_id" defaultValue={fields?.sales_rep_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">None</option>
                                {sales_reps.map((rep) => (
                                    <option key={rep.id} value={rep.id}>
                                        {rep.name} ({rep.status})
                                    </option>
                                ))}
                            </select>
                            {errors?.sales_rep_id ? <p className="mt-1 text-xs text-rose-600">{errors.sales_rep_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" defaultValue={fields?.status || 'active'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="active">Active</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                            <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={fields?.start_date || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.start_date ? <p className="mt-1 text-xs text-rose-600">{errors.start_date}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Current Period Start</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="current_period_start"
                                defaultValue={fields?.current_period_start || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.current_period_start ? <p className="mt-1 text-xs text-rose-600">{errors.current_period_start}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Current Period End</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="current_period_end"
                                defaultValue={fields?.current_period_end || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.current_period_end ? <p className="mt-1 text-xs text-rose-600">{errors.current_period_end}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Next Invoice Date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="next_invoice_at"
                                defaultValue={fields?.next_invoice_at || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.next_invoice_at ? <p className="mt-1 text-xs text-rose-600">{errors.next_invoice_at}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Access Override Until</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="access_override_until"
                                defaultValue={fields?.access_override_until || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.access_override_until ? <p className="mt-1 text-xs text-rose-600">{errors.access_override_until}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Cancelled At</label>
                            <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="cancelled_at" defaultValue={fields?.cancelled_at || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.cancelled_at ? <p className="mt-1 text-xs text-rose-600">{errors.cancelled_at}</p> : null}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                        <textarea name="notes" rows={4} defaultValue={fields?.notes || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>

                    <div className="flex flex-wrap items-center gap-5">
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="auto_renew" value="0" />
                            <input type="checkbox" name="auto_renew" value="1" defaultChecked={Boolean(fields?.auto_renew)} />
                            Auto renew
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="cancel_at_period_end" value="0" />
                            <input type="checkbox" name="cancel_at_period_end" value="1" defaultChecked={Boolean(fields?.cancel_at_period_end)} />
                            Cancel at period end
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {is_edit ? 'Update Subscription' : 'Create Subscription'}
                        </button>
                        <a href={routes?.index} data-native="true" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </>
    );
}
