import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Edit({
    pageTitle = 'Edit Expense',
    categories = [],
    expense = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">Edit one-time expense</div>
                </div>
                <a
                    href={routes?.back}
                    data-native="true"
                    className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    Back
                </a>
            </div>

            <div className="card max-w-3xl p-6">
                <form method="POST" action={routes?.update} enctype="multipart/form-data" className="grid gap-4 text-sm" data-native="true">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />

                    <div>
                        <label className="text-xs text-slate-500">Category</label>
                        <select
                            name="category_id"
                            required
                            defaultValue={expense?.category_id ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="">Select category</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {errors?.category_id ? <div className="mt-1 text-xs text-rose-600">{errors.category_id}</div> : null}
                    </div>

                    <div>
                        <label className="text-xs text-slate-500">Title</label>
                        <input
                            name="title"
                            defaultValue={expense?.title ?? ''}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                        {errors?.title ? <div className="mt-1 text-xs text-rose-600">{errors.title}</div> : null}
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                defaultValue={expense?.amount ?? ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.amount ? <div className="mt-1 text-xs text-rose-600">{errors.amount}</div> : null}
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Expense date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="expense_date"
                                defaultValue={expense?.expense_date ?? ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors?.expense_date ? <div className="mt-1 text-xs text-rose-600">{errors.expense_date}</div> : null}
                        </div>
                    </div>

                    <div>
                        <label className="text-xs text-slate-500">Notes</label>
                        <textarea
                            name="notes"
                            rows={3}
                            defaultValue={expense?.notes ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                        {errors?.notes ? <div className="mt-1 text-xs text-rose-600">{errors.notes}</div> : null}
                    </div>

                    <div>
                        <label className="text-xs text-slate-500">Receipt (jpg/png/pdf)</label>
                        <input
                            type="file"
                            name="attachment"
                            accept=".jpg,.jpeg,.png,.pdf"
                            className="mt-1 block text-xs text-slate-600"
                        />
                        {expense?.attachment_url ? (
                            <a
                                href={expense.attachment_url}
                                data-native="true"
                                className="mt-2 inline-block text-xs font-semibold text-teal-600 hover:text-teal-500"
                            >
                                View current receipt
                            </a>
                        ) : null}
                        {errors?.attachment ? <div className="mt-1 text-xs text-rose-600">{errors.attachment}</div> : null}
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Update Expense
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
