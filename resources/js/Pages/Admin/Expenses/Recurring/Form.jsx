import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({
    pageTitle,
    heading,
    routes = {},
    submit = { label: 'Save', http_method: 'POST' },
    categories = [],
    form = {},
}) {
    const { csrf_token: csrfToken, errors = {} } = usePage().props;
    const method = String(submit?.http_method || 'POST').toUpperCase();

    return (
        <>
            <Head title={pageTitle || 'Recurring Expense'} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">{heading || 'Recurring expense'}</div>
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
                <form method="POST" action={routes?.submit} className="grid gap-4 text-sm" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    {method !== 'POST' ? <input type="hidden" name="_method" value={method} /> : null}

                    <div>
                        <label className="text-xs text-slate-500">Category</label>
                        <select
                            name="category_id"
                            required
                            defaultValue={form.category_id ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        >
                            <option value="">Select category</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {errors.category_id ? <div className="mt-1 text-xs text-rose-600">{errors.category_id}</div> : null}
                    </div>

                    <div>
                        <label className="text-xs text-slate-500">Title</label>
                        <input
                            name="title"
                            defaultValue={form.title ?? ''}
                            required
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                        {errors.title ? <div className="mt-1 text-xs text-rose-600">{errors.title}</div> : null}
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                defaultValue={form.amount ?? ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors.amount ? <div className="mt-1 text-xs text-rose-600">{errors.amount}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Recurrence</label>
                            <div className="mt-1 flex gap-2">
                                <select
                                    name="recurrence_type"
                                    defaultValue={form.recurrence_type ?? 'monthly'}
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                                <input
                                    type="number"
                                    min="1"
                                    name="recurrence_interval"
                                    defaultValue={form.recurrence_interval ?? '1'}
                                    className="w-24 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                                    title="Interval"
                                />
                            </div>
                            {errors.recurrence_type ? <div className="mt-1 text-xs text-rose-600">{errors.recurrence_type}</div> : null}
                            {errors.recurrence_interval ? (
                                <div className="mt-1 text-xs text-rose-600">{errors.recurrence_interval}</div>
                            ) : null}
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-2">
                        <div>
                            <label className="text-xs text-slate-500">Start date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="start_date"
                                defaultValue={form.start_date ?? ''}
                                required
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors.start_date ? <div className="mt-1 text-xs text-rose-600">{errors.start_date}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">End date (optional)</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="end_date"
                                defaultValue={form.end_date ?? ''}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            {errors.end_date ? <div className="mt-1 text-xs text-rose-600">{errors.end_date}</div> : null}
                        </div>
                    </div>

                    <div>
                        <label className="text-xs text-slate-500">Notes</label>
                        <textarea
                            name="notes"
                            rows={3}
                            defaultValue={form.notes ?? ''}
                            className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                        {errors.notes ? <div className="mt-1 text-xs text-rose-600">{errors.notes}</div> : null}
                    </div>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                        >
                            {submit?.label || 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
