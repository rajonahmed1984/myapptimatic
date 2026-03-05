import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({ pageTitle = 'Plan', is_edit = false, products = [], form = {}, routes = {}, default_currency = '' }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const initialPricingRows = Array.isArray(fields?.pricing_rows) && fields.pricing_rows.length > 0
        ? fields.pricing_rows
        : [{ id: '', interval: fields?.interval || 'monthly', price: fields?.price || '' }];
    const [pricingRows, setPricingRows] = React.useState(
        initialPricingRows.map((row) => ({
            id: row?.id || '',
            interval: row?.interval || 'monthly',
            price: row?.price ?? '',
        }))
    );

    const addPricingRow = () => {
        setPricingRows((prev) => [...prev, { id: '', interval: 'monthly', price: '' }]);
    };

    const removePricingRow = (index) => {
        setPricingRows((prev) => (prev.length <= 1 ? prev : prev.filter((_, idx) => idx !== index)));
    };

    const updatePricingRow = (index, key, value) => {
        setPricingRows((prev) =>
            prev.map((row, idx) => (idx === index ? { ...row, [key]: value } : row))
        );
    };

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

                    <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="block text-sm font-medium text-slate-700">Interval & Price</label>
                            <button
                                type="button"
                                onClick={addPricingRow}
                                className="rounded-lg border border-teal-300 bg-white px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-50"
                            >
                                Add Interval & Price
                            </button>
                        </div>

                        {pricingRows.map((row, index) => (
                            <div key={`${row.id || 'new'}-${index}`} className="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 md:grid-cols-[1fr_1fr_auto]">
                                <input type="hidden" name={`pricing_rows[${index}][id]`} value={row.id} />

                                <div>
                                    <label className="mb-1 block text-xs font-medium uppercase tracking-[0.12em] text-slate-500">Interval</label>
                                    <select
                                        name={`pricing_rows[${index}][interval]`}
                                        value={row.interval}
                                        onChange={(event) => updatePricingRow(index, 'interval', event.target.value)}
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    >
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                    </select>
                                    {errors?.[`pricing_rows.${index}.interval`] ? (
                                        <p className="mt-1 text-xs text-rose-600">{errors[`pricing_rows.${index}.interval`]}</p>
                                    ) : null}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium uppercase tracking-[0.12em] text-slate-500">Price ({default_currency})</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name={`pricing_rows[${index}][price]`}
                                        value={row.price}
                                        onChange={(event) => updatePricingRow(index, 'price', event.target.value)}
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2"
                                    />
                                    {errors?.[`pricing_rows.${index}.price`] ? (
                                        <p className="mt-1 text-xs text-rose-600">{errors[`pricing_rows.${index}.price`]}</p>
                                    ) : null}
                                </div>

                                <div className="flex items-end">
                                    <button
                                        type="button"
                                        onClick={() => removePricingRow(index)}
                                        className="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                                        disabled={pricingRows.length <= 1}
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        ))}

                        {errors?.pricing_rows ? <p className="text-xs text-rose-600">{errors.pricing_rows}</p> : null}
                        {errors?.interval ? <p className="text-xs text-rose-600">{errors.interval}</p> : null}
                        {errors?.price ? <p className="text-xs text-rose-600">{errors.price}</p> : null}
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
