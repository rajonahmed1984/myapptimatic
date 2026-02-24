import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Create({ pageTitle = 'Create Support Ticket', customers = [], selected_customer_id = '', routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to tickets
                    </a>
                </div>

                <form action={routes?.store} method="POST" encType="multipart/form-data" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                        <select name="customer_id" defaultValue={selected_customer_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Select customer</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name} {customer.email ? `(${customer.email})` : ''}
                                </option>
                            ))}
                        </select>
                        {errors?.customer_id ? <p className="mt-1 text-xs text-rose-600">{errors.customer_id}</p> : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Subject</label>
                            <input name="subject" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.subject ? <p className="mt-1 text-xs text-rose-600">{errors.subject}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                            <select name="priority" defaultValue="medium" className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                            {errors?.priority ? <p className="mt-1 text-xs text-rose-600">{errors.priority}</p> : null}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Message</label>
                        <textarea name="message" rows={7} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.message ? <p className="mt-1 text-xs text-rose-600">{errors.message}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Attachment</label>
                        <input type="file" name="attachment" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.attachment ? <p className="mt-1 text-xs text-rose-600">{errors.attachment}</p> : null}
                    </div>

                    <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Create Ticket
                    </button>
                </form>
            </div>
        </>
    );
}
