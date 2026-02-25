import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({ pageTitle = 'Accounting Entry', is_edit = false, form = {}, types = [], customers = [], invoices = [], gateways = [], routes = {} }) {
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
                        Back
                    </a>
                </div>

                <form action={form?.action} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Type</label>
                            <select name="type" defaultValue={fields?.type || 'payment'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Entry Date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="entry_date"
                                defaultValue={fields?.entry_date || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.entry_date ? <p className="mt-1 text-xs text-rose-600">{errors.entry_date}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                name="amount"
                                defaultValue={fields?.amount || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.amount ? <p className="mt-1 text-xs text-rose-600">{errors.amount}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                            <input name="currency" defaultValue={fields?.currency || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.currency ? <p className="mt-1 text-xs text-rose-600">{errors.currency}</p> : null}
                        </div>
                    </div>

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
                            <label className="mb-1 block text-sm font-medium text-slate-700">Invoice</label>
                            <select name="invoice_id" defaultValue={fields?.invoice_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Select invoice</option>
                                {invoices.map((invoice) => (
                                    <option key={invoice.id} value={invoice.id}>
                                        {invoice.label} - {invoice.customer_name}
                                    </option>
                                ))}
                            </select>
                            {errors?.invoice_id ? <p className="mt-1 text-xs text-rose-600">{errors.invoice_id}</p> : null}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Payment Gateway</label>
                        <select name="payment_gateway_id" defaultValue={fields?.payment_gateway_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Select gateway</option>
                            {gateways.map((gateway) => (
                                <option key={gateway.id} value={gateway.id}>
                                    {gateway.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Reference</label>
                            <input name="reference" defaultValue={fields?.reference || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <input name="description" defaultValue={fields?.description || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                    </div>

                    {form?.due_amount !== null && form?.due_amount !== undefined ? (
                        <p className="text-sm text-slate-500">Invoice due amount: {Number(form.due_amount).toFixed(2)}</p>
                    ) : null}

                    <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {is_edit ? 'Update Entry' : 'Create Entry'}
                    </button>
                </form>
            </div>
        </>
    );
}
