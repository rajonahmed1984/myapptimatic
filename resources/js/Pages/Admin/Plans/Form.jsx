import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Form({ pageTitle = 'Plan', is_edit = false, products = [], mybuilding_slug = 'mybuilding', form = {}, routes = {}, default_currency = '' }) {
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

    const [productId, setProductId] = React.useState(String(fields?.product_id || ''));
    const selectedProduct = products.find((p) => String(p.id) === String(productId));
    const isMyBuilding = selectedProduct?.slug === mybuilding_slug;

    const [pricingModel, setPricingModel] = React.useState(() => {
        if (fields?.pricing_model) return fields.pricing_model;
        if (isMyBuilding) return 'per_flat';
        return 'fixed';
    });

    const productOptions = [
        { value: '', label: 'Select product' },
        ...products.map((product) => ({ value: String(product.id), label: product.name })),
    ];
    const intervalOptions = [
        { value: 'monthly', label: 'Monthly' },
        { value: 'yearly', label: 'Yearly' },
    ];

    const handleProductChange = (val) => {
        setProductId(val);
        const prod = products.find((p) => String(p.id) === String(val));
        if (prod?.slug === mybuilding_slug) {
            setPricingModel('per_flat');
        }
    };

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
                        <SearchableSelect
                            name="product_id"
                            value={productId}
                            onChange={handleProductChange}
                            options={productOptions}
                            placeholder="Select product"
                            error={errors?.product_id}
                        />
                    </div>

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
                        <label className="mb-1 block text-sm font-medium text-slate-700">Pricing Model</label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className={`flex cursor-pointer items-center justify-between rounded-xl border p-3 transition ${pricingModel === 'fixed' ? 'border-teal-500 bg-teal-50/40' : 'border-slate-200 bg-white hover:bg-slate-50'}`}>
                                <div>
                                    <div className="text-xs font-semibold text-slate-900">Fixed Subscription</div>
                                    <div className="text-[11px] text-slate-500">Standard rate per interval (e.g. 2,000 BDT/mo)</div>
                                </div>
                                <input
                                    type="radio"
                                    name="pricing_model"
                                    value="fixed"
                                    checked={pricingModel === 'fixed'}
                                    onChange={() => setPricingModel('fixed')}
                                    className="text-teal-600 focus:ring-teal-500"
                                />
                            </label>
                            <label className={`flex cursor-pointer items-center justify-between rounded-xl border p-3 transition ${pricingModel === 'per_flat' ? 'border-teal-500 bg-teal-50/40' : 'border-slate-200 bg-white hover:bg-slate-50'}`}>
                                <div>
                                    <div className="text-xs font-semibold text-slate-900">Flat-wise (Per Flat)</div>
                                    <div className="text-[11px] text-slate-500">Rate per flat/floor (e.g. 50 BDT / flat / mo)</div>
                                </div>
                                <input
                                    type="radio"
                                    name="pricing_model"
                                    value="per_flat"
                                    checked={pricingModel === 'per_flat'}
                                    onChange={() => setPricingModel('per_flat')}
                                    className="text-teal-600 focus:ring-teal-500"
                                />
                            </label>
                        </div>
                    </div>

                    <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <label className="block text-sm font-medium text-slate-700">
                                {pricingModel === 'per_flat' ? 'Interval & Flat Rate' : 'Interval & Price'}
                            </label>
                            <button
                                type="button"
                                onClick={addPricingRow}
                                className="ui-btn-secondary"
                            >
                                Add Interval & Price
                            </button>
                        </div>

                        {pricingRows.map((row, index) => (
                            <div key={`${row.id || 'new'}-${index}`} className="grid gap-3 rounded-lg border border-slate-200 bg-white p-3 md:grid-cols-[1fr_1fr_auto]">
                                <input type="hidden" name={`pricing_rows[${index}][id]`} value={row.id} />

                                <div>
                                    <label className="mb-1 block text-xs font-medium uppercase tracking-[0.12em] text-slate-500">Interval</label>
                                    <SearchableSelect
                                        name={`pricing_rows[${index}][interval]`}
                                        value={row.interval}
                                        onChange={(nextValue) => updatePricingRow(index, 'interval', String(nextValue || 'monthly'))}
                                        options={intervalOptions}
                                        placeholder="Select interval"
                                    />
                                    {errors?.[`pricing_rows.${index}.interval`] ? (
                                        <p className="mt-1 text-xs text-rose-600">{errors[`pricing_rows.${index}.interval`]}</p>
                                    ) : null}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium uppercase tracking-[0.12em] text-slate-500">
                                        {pricingModel === 'per_flat' ? `Rate per Flat (${default_currency})` : `Price (${default_currency})`}
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder={pricingModel === 'per_flat' ? 'e.g. 50' : 'e.g. 2000'}
                                        name={`pricing_rows[${index}][price]`}
                                        value={row.price}
                                        onChange={(event) => updatePricingRow(index, 'price', event.target.value)}
                                        className="ui-input"
                                    />
                                    {errors?.[`pricing_rows.${index}.price`] ? (
                                        <p className="mt-1 text-xs text-rose-600">{errors[`pricing_rows.${index}.price`]}</p>
                                    ) : null}
                                </div>

                                <div className="flex items-end">
                                    <button
                                        type="button"
                                        onClick={() => removePricingRow(index)}
                                        className="ui-btn-danger disabled:cursor-not-allowed disabled:opacity-40"
                                        disabled={pricingRows.length <= 1}
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                        ))}

                        {pricingModel === 'per_flat' && (
                            <div className="rounded-lg bg-teal-50 p-2.5 text-xs text-teal-800 border border-teal-100">
                                💡 <strong>Flat-wise Pricing:</strong> Customer will be billed dynamically based on the total number of flats across all floors (e.g. 10 flats × {default_currency} {pricingRows[0]?.price || '50'} = {default_currency} {Number(pricingRows[0]?.price || 50) * 10} / mo).
                            </div>
                        )}

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
                        <button type="submit" className="ui-btn-primary">
                            {is_edit ? 'Update Plan' : 'Create Plan'}
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
