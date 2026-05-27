import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Form({ pageTitle = 'Product', is_edit = false, form = {}, routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const statusOptions = [
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
    ];

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6">
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

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input name="name" defaultValue={fields?.name || ''} className="ui-input" />
                        {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Slug</label>
                        <input name="slug" defaultValue={fields?.slug || ''} className="ui-input" />
                        {errors?.slug ? <p className="mt-1 text-xs text-rose-600">{errors.slug}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                        <SearchableSelect
                            name="status"
                            defaultValue={String(fields?.status || 'active')}
                            options={statusOptions}
                            placeholder="Select status"
                            error={errors?.status}
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                        <textarea
                            name="description"
                            defaultValue={fields?.description || ''}
                            rows={5}
                            className="ui-textarea"
                        />
                        {errors?.description ? <p className="mt-1 text-xs text-rose-600">{errors.description}</p> : null}
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="ui-btn-primary">
                            {is_edit ? 'Update Product' : 'Create Product'}
                        </button>
                        <a href={routes?.index} data-native="true" className="ui-btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </>
    );
}
