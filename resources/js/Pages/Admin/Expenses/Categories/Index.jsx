import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 text-emerald-700 bg-emerald-50';
    }

    return 'border-slate-300 text-slate-600 bg-slate-50';
};

export default function Index({
    pageTitle = 'Expense Categories',
    heading = 'Expense categories',
    routes = {},
    form = {},
    categories = [],
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const method = String(form?.method || 'POST').toUpperCase();
    const isEditing = Boolean(form?.editing);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">{heading}</div>
                </div>
                <a
                    href={routes?.back}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    Back to Expenses
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div className="card p-6">
                    <div className="section-label">{form?.title || 'Add category'}</div>
                    {isEditing ? (
                        <div className="mt-2 text-xs text-slate-500">
                            Editing:{' '}
                            <span className="font-semibold text-slate-700">
                                {form?.editing_name || ''}
                            </span>
                        </div>
                    ) : null}

                    <form method="POST" action={form?.action} className="mt-4 grid gap-3 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        {method !== 'POST' ? <input type="hidden" name="_method" value={method} /> : null}
                        {isEditing ? <input type="hidden" name="edit_id" value={form?.fields?.edit_id || ''} /> : null}

                        <div>
                            <input
                                name="name"
                                defaultValue={form?.fields?.name || ''}
                                placeholder="Category name"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        </div>

                        <div>
                            <select
                                name="status"
                                defaultValue={form?.fields?.status || 'active'}
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            {errors.status ? <div className="mt-1 text-xs text-rose-600">{errors.status}</div> : null}
                        </div>

                        <div>
                            <textarea
                                name="description"
                                rows={3}
                                defaultValue={form?.fields?.description || ''}
                                placeholder="Description (optional)"
                                className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors.description ? <div className="mt-1 text-xs text-rose-600">{errors.description}</div> : null}
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                {form?.button_label || 'Add Category'}
                            </button>
                            {form?.cancel_href ? (
                                <a href={form.cancel_href} data-native="true" className="text-xs font-semibold text-slate-500 hover:text-slate-700">
                                    Cancel edit
                                </a>
                            ) : null}
                        </div>
                    </form>
                </div>

                <div className="card p-6">
                    <div className="section-label">Category list</div>
                    {errors.category ? (
                        <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-700">
                            {errors.category}
                        </div>
                    ) : null}
                    <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="px-3 py-2">Name</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Description</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.length > 0 ? (
                                    categories.map((category) => (
                                        <tr key={category.id} className="border-b border-slate-100">
                                            <td className="px-3 py-2 font-semibold text-slate-900">{category.name}</td>
                                            <td className="px-3 py-2">
                                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(category.status)}`}>
                                                    {category.status_label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-slate-500">{category.description}</td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="flex justify-end gap-3 text-xs font-semibold">
                                                    <a href={category?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action={category?.routes?.destroy}
                                                        data-native="true"
                                                        data-delete-confirm
                                                        data-confirm-name={category.name}
                                                        data-confirm-title={`Delete category ${category.name}?`}
                                                        data-confirm-description="This will permanently delete the expense category."
                                                    >
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={4} className="px-3 py-4 text-center text-slate-500">
                                            No categories yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
