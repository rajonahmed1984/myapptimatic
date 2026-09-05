import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

const statusClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 text-emerald-700 bg-emerald-50';
    }

    return 'border-slate-300 text-slate-600 bg-slate-50';
};

export default function Index({
    pageTitle = 'Income Categories',
    heading = 'Income categories',
    routes = {},
    form = {},
    categories = [],
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};
    const method = String(form?.method || 'POST').toUpperCase();
    const isEditing = Boolean(form?.editing);
    const statusOptions = [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
    ];

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
                    Back to Income
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
                                className="ui-input"
                            />
                            {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                        </div>

                        <div>
                            <SearchableSelect
                                name="status"
                                defaultValue={String(form?.fields?.status || 'active')}
                                options={statusOptions}
                                placeholder="Select status"
                                error={errors.status}
                            />
                        </div>

                        <div>
                            <textarea
                                name="description"
                                rows={1}
                                defaultValue={form?.fields?.description || ''}
                                placeholder="Description (optional)"
                                className="ui-input"
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
                    <DataTable
                        rows={categories}
                        emptyMessage="No categories yet."
                        columns={[
                            { key: 'name', header: 'Name', cellClassName: 'font-semibold text-slate-900', render: (category) => category.name },
                            {
                                key: 'status',
                                header: 'Status',
                                render: (category) => <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(category.status)}`}>{category.status_label}</span>,
                            },
                            { key: 'description', header: 'Description', cellClassName: 'text-slate-500', render: (category) => category.description },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                render: (category) => (
                                    <div className="flex justify-end gap-3 text-xs font-semibold">
                                        <a href={category?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">Edit</a>
                                        <form
                                            method="POST"
                                            action={category?.routes?.destroy}
                                            data-native="true"
                                            data-delete-confirm
                                            data-confirm-name={category.name}
                                            data-confirm-title={`Delete category ${category.name}?`}
                                            data-confirm-description="This will permanently delete the income category."
                                        >
                                            <input type="hidden" name="_token" value={csrfToken} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                        </form>
                                    </div>
                                ),
                            },
                        ]}
                        renderMobileCard={(category) => (
                            <MobileCard
                                title={category.name}
                                subtitle={category.description}
                                badge={category.status_label}
                                badgeColor={statusClass(category.status)}
                                actions={
                                    <>
                                        <a
                                            href={category?.routes?.edit}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                        >
                                            Edit
                                        </a>
                                        <form
                                            method="POST"
                                            action={category?.routes?.destroy}
                                            data-native="true"
                                            data-delete-confirm
                                            data-confirm-name={category.name}
                                            data-confirm-title={`Delete category ${category.name}?`}
                                            data-confirm-description="This will permanently delete the income category."
                                        >
                                            <input type="hidden" name="_token" value={csrfToken} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                        </form>
                                    </>
                                }
                            />
                        )}
                    />
                </div>
            </div>
        </>
    );
}
