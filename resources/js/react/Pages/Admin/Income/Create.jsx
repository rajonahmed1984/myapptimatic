import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Create({
    pageTitle = 'Add Income',
    routes = {},
    categories = [],
    form = {},
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const fields = form?.fields || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">New income</div>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    Back
                </a>
            </div>

            <div className="card p-6">
                <form
                    method="POST"
                    action={routes?.store}
                    encType="multipart/form-data"
                    className="grid gap-4 text-sm"
                    data-native="true"
                >
                    <input type="hidden" name="_token" value={csrfToken} />

                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Category</label>
                            <select
                                name="income_category_id"
                                required
                                defaultValue={fields?.income_category_id ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="">Select category</option>
                                {categories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            {errors?.income_category_id ? (
                                <div className="mt-1 text-xs text-rose-600">{errors.income_category_id}</div>
                            ) : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Title</label>
                            <input
                                name="title"
                                required
                                defaultValue={fields?.title ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.title ? <div className="mt-1 text-xs text-rose-600">{errors.title}</div> : null}
                        </div>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                required
                                defaultValue={fields?.amount ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.amount ? <div className="mt-1 text-xs text-rose-600">{errors.amount}</div> : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Income date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="income_date"
                                required
                                defaultValue={fields?.income_date ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.income_date ? (
                                <div className="mt-1 text-xs text-rose-600">{errors.income_date}</div>
                            ) : null}
                        </div>
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Notes</label>
                        <textarea
                            name="notes"
                            rows={3}
                            defaultValue={fields?.notes ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors?.notes ? <div className="mt-1 text-xs text-rose-600">{errors.notes}</div> : null}
                    </div>
                    <div>
                        <label className="text-xs text-slate-500">Attachment (jpg/png/pdf)</label>
                        <input
                            type="file"
                            name="attachment"
                            accept=".jpg,.jpeg,.png,.pdf"
                            className="mt-1 block text-xs text-slate-600"
                        />
                        {errors?.attachment ? <div className="mt-1 text-xs text-rose-600">{errors.attachment}</div> : null}
                    </div>
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                        >
                            Save Income
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
