import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({ pageTitle = 'Plan', is_edit = false, products = [], form = {}, routes = {}, default_currency = '' }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

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
                        <label className="mb-1 block text-sm font-medium text-slate-700">Product</label>
                        <select name="product_id" defaultValue={fields?.product_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Select product</option>
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.name}
                                </option>
                            ))}
                        </select>
                        {errors?.product_id ? <p className="mt-1 text-xs text-rose-600">{errors.product_id}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input name="name" defaultValue={fields?.name || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Slug</label>
                        <input name="slug" defaultValue={fields?.slug || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.slug ? <p className="mt-1 text-xs text-rose-600">{errors.slug}</p> : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Interval</label>
                            <select name="interval" defaultValue={fields?.interval || 'monthly'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            {errors?.interval ? <p className="mt-1 text-xs text-rose-600">{errors.interval}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Price ({default_currency})</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="price"
                                defaultValue={fields?.price || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.price ? <p className="mt-1 text-xs text-rose-600">{errors.price}</p> : null}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0" />
                        <input id="is_active" type="checkbox" name="is_active" value="1" defaultChecked={Boolean(fields?.is_active)} />
                        <label htmlFor="is_active" className="text-sm text-slate-700">
                            Active
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {is_edit ? 'Update Plan' : 'Create Plan'}
                        </button>
                        <a href={routes?.index} data-native="true" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </>
    );
}
