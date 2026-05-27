import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Create({ pageTitle = 'Create Support Ticket', customers = [], selected_customer_id = '', routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const customerOptions = [
        { value: '', label: 'Select customer' },
        ...customers.map((customer) => ({
            value: String(customer.id),
            label: `${customer.name}${customer.email ? ` (${customer.email})` : ''}`,
        })),
    ];
    const priorityOptions = [
        { value: 'low', label: 'Low' },
        { value: 'medium', label: 'Medium' },
        { value: 'high', label: 'High' },
    ];

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
                        <SearchableSelect
                            name="customer_id"
                            defaultValue={String(selected_customer_id || '')}
                            options={customerOptions}
                            className="mt-1"
                            placeholder="Select customer"
                            error={errors?.customer_id}
                        />
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Subject</label>
                            <input name="subject" className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.subject ? <p className="mt-1 text-xs text-rose-600">{errors.subject}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                            <SearchableSelect
                                name="priority"
                                defaultValue="medium"
                                options={priorityOptions}
                                className="mt-1"
                                placeholder="Select priority"
                                error={errors?.priority}
                            />
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
