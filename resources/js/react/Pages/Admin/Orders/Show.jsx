import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({ pageTitle = 'Order', order = {}, plan_options = [], interval_options = [], routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const canProcess = String(order?.status || '') === 'pending';

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">Order #{order?.order_number || '--'}</h1>
                            <p className="text-sm text-slate-500">{order?.status_label || '--'}</p>
                        </div>
                        <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                            Back to orders
                        </a>
                    </div>
                    <div className="grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                        <p>Customer: {order?.customer_name || '--'}</p>
                        <p>Email: {order?.customer_email || '--'}</p>
                        <p>Product: {order?.product_name || '--'}</p>
                        <p>Plan: {order?.plan_name || '--'}</p>
                        <p>Created: {order?.created_at_display || '--'}</p>
                        <p>Invoice: {order?.invoice_number || '--'}</p>
                        <p>Invoice Total: {order?.invoice_total_display || '--'}</p>
                        {order?.invoice_url ? (
                            <p>
                                <a href={order.invoice_url} data-native="true" className="font-medium text-teal-600 hover:text-teal-500">
                                    Open invoice
                                </a>
                            </p>
                        ) : null}
                    </div>
                </div>

                {canProcess ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-6">
                        <h2 className="mb-4 text-lg font-semibold text-slate-900">Approve Order</h2>
                        <form action={routes?.approve} method="POST" data-native="true" className="space-y-4">
                            <input type="hidden" name="_token" value={csrf} />
                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">License Key</label>
                                    <input name="license_key" defaultValue={order?.license_key || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                                    {errors?.license_key ? <p className="mt-1 text-xs text-rose-600">{errors.license_key}</p> : null}
                                </div>
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">License URL / Domain</label>
                                    <input name="license_url" defaultValue={order?.license_url || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                                    {errors?.license_url ? <p className="mt-1 text-xs text-rose-600">{errors.license_url}</p> : null}
                                </div>
                            </div>
                            <button type="submit" className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                Approve
                            </button>
                        </form>
                    </div>
                ) : null}

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Update Plan/Interval</h2>
                    <form action={routes?.update_plan} method="POST" data-native="true" className="grid gap-4 md:grid-cols-3">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="PATCH" />
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Plan</label>
                            <select name="plan_id" className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Keep current</option>
                                {plan_options.map((plan) => (
                                    <option key={plan.id} value={plan.id}>
                                        {plan.name} ({plan.interval})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Interval</label>
                            <select name="interval" className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Keep current</option>
                                {interval_options.map((interval) => (
                                    <option key={interval} value={interval}>
                                        {interval}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Update
                            </button>
                        </div>
                    </form>
                </div>

                {canProcess ? (
                    <div className="rounded-2xl border border-rose-200 bg-rose-50 p-6">
                        <h2 className="mb-3 text-lg font-semibold text-rose-800">Cancel Order</h2>
                        <form action={routes?.cancel} method="POST" data-native="true" className="inline-flex">
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">
                                Cancel Order
                            </button>
                        </form>
                    </div>
                ) : null}

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-3 text-lg font-semibold text-slate-900">Delete Order</h2>
                    <form action={routes?.destroy} method="POST" data-native="true" className="inline-flex">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button type="submit" className="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
