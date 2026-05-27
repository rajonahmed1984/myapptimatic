import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Create({
    pageTitle = 'Create Invoice',
    customers = [],
    form = {},
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const customerOptions = [
        { value: '', label: 'Select customer' },
        ...customers.map((customer) => ({ value: String(customer.id), label: customer.label })),
    ];
    const initialItems = Array.isArray(fields?.items) && fields.items.length > 0 ? fields.items : [{ description: '', quantity: 1, unit_price: '0' }];
    const [items, setItems] = React.useState(initialItems);

    const addItem = () => {
        setItems((current) => [...current, { description: '', quantity: 1, unit_price: '0' }]);
    };

    const removeItem = (index) => {
        setItems((current) => {
            if (current.length === 1) {
                return current;
            }

            return current.filter((_, itemIndex) => itemIndex !== index);
        });
    };

    const updateItem = (index, key, value) => {
        setItems((current) =>
            current.map((item, itemIndex) => {
                if (itemIndex !== index) {
                    return item;
                }

                return { ...item, [key]: value };
            }),
        );
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Invoices</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Create Invoice</h1>
                    <p className="mt-2 text-sm text-slate-600">Create a manual invoice for a customer.</p>
                </div>
                <a
                    href={routes?.index}
                    data-native="true"
                    className="ui-btn-secondary"
                >
                    Back to invoices
                </a>
            </div>

            <div className="card p-6">
                <form method="POST" action={form?.action} data-native="true" className="space-y-6">
                    <input type="hidden" name="_token" value={csrf} />

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="md:col-span-2">
                            <label className="text-sm text-slate-600">Customer</label>
                            <SearchableSelect
                                name="customer_id"
                                defaultValue={String(fields?.customer_id || '')}
                                options={customerOptions}
                                className="mt-2"
                                placeholder="Select customer"
                                required
                                error={errors?.customer_id}
                            />
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Issue Date</label>
                            <input
                                name="issue_date"
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                defaultValue={fields?.issue_date || ''}
                                required
                                className="ui-input mt-2"
                            />
                            {errors?.issue_date ? <p className="mt-1 text-xs text-rose-600">{errors.issue_date}</p> : null}
                        </div>
                        <div>
                            <label className="text-sm text-slate-600">Due Date</label>
                            <input
                                name="due_date"
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                defaultValue={fields?.due_date || ''}
                                required
                                className="ui-input mt-2"
                            />
                            {errors?.due_date ? <p className="mt-1 text-xs text-rose-600">{errors.due_date}</p> : null}
                        </div>
                        <div className="md:col-span-3">
                            <label className="text-sm text-slate-600">Notes</label>
                            <textarea
                                name="notes"
                                rows={1}
                                defaultValue={fields?.notes || ''}
                                className="ui-textarea mt-2"
                            />
                        </div>
                    </div>

                    <div>
                        <div className="flex items-center justify-between">
                            <div className="text-sm font-semibold text-slate-700">Invoice Items</div>
                            <button
                                type="button"
                                onClick={addItem}
                                className="ui-btn-secondary"
                            >
                                Add item
                            </button>
                        </div>
                        {errors?.items ? <p className="mt-2 text-xs text-rose-600">{errors.items}</p> : null}

                        <div className="mt-4 space-y-3">
                            {items.map((item, index) => (
                                <div key={index} className="grid items-start gap-3 md:grid-cols-12">
                                    <div className="md:col-span-7">
                                        <label className="text-xs text-slate-500">Description</label>
                                        <input
                                            name={`items[${index}][description]`}
                                            value={item.description}
                                            onChange={(event) => updateItem(index, 'description', event.target.value)}
                                            required
                                            className="ui-input mt-1"
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="text-xs text-slate-500">Qty</label>
                                        <input
                                            name={`items[${index}][quantity]`}
                                            type="number"
                                            min="1"
                                            value={item.quantity}
                                            onChange={(event) => updateItem(index, 'quantity', event.target.value)}
                                            required
                                            className="ui-input mt-1"
                                        />
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="text-xs text-slate-500">Unit Price</label>
                                        <input
                                            name={`items[${index}][unit_price]`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.unit_price}
                                            onChange={(event) => updateItem(index, 'unit_price', event.target.value)}
                                            required
                                            className="ui-input mt-1"
                                        />
                                    </div>
                                    <div className="md:col-span-1 flex items-end">
                                        <button
                                            type="button"
                                            onClick={() => removeItem(index)}
                                            className="ui-btn-danger mt-7"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button type="submit" className="ui-btn-primary">
                            Create invoice
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
