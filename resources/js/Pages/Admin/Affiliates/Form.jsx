import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

export default function Form({
    pageTitle = 'Affiliate',
    is_edit = false,
    affiliate = null,
    form = {},
    status_options = [],
    commission_type_options = [],
    customers = [],
    routes = {},
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const customerOptions = [
        { value: '', label: 'Select customer' },
        ...customers.map((customer) => ({
            value: String(customer.id),
            label: `${customer.name} (${customer.email})`,
        })),
    ];
    const statusOptions = status_options.map((option) => ({ value: String(option.value || ''), label: option.label }));
    const commissionTypeOptions = commission_type_options.map((option) => ({ value: String(option.value || ''), label: option.label }));

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Affiliates</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">
                        {is_edit ? affiliate?.customer_name || 'Edit affiliate' : 'Create affiliate account'}
                    </h1>
                </div>
                <a
                    href={is_edit ? routes?.show : routes?.index}
                    data-native="true"
                    className="ui-btn-secondary"
                >
                    {is_edit ? 'Back to affiliate' : 'Back to affiliates'}
                </a>
            </div>

            <div className="card p-8">
                <div className="section-label">{is_edit ? 'Edit Affiliate' : 'New Affiliate'}</div>

                <form method="POST" action={form?.action} data-native="true" className="mt-8 space-y-6">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <label className="text-sm text-slate-600">Customer *</label>
                            <SearchableSelect
                                name="customer_id"
                                required
                                defaultValue={String(fields?.customer_id ?? '')}
                                options={customerOptions}
                                className="mt-2"
                                placeholder="Select customer"
                                error={errors?.customer_id}
                            />
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Status *</label>
                            <SearchableSelect
                                name="status"
                                required
                                defaultValue={String(fields?.status ?? 'active')}
                                options={statusOptions}
                                className="mt-2"
                                placeholder="Select status"
                                error={errors?.status}
                            />
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Commission Type *</label>
                            <SearchableSelect
                                name="commission_type"
                                required
                                defaultValue={String(fields?.commission_type ?? 'percentage')}
                                options={commissionTypeOptions}
                                className="mt-2"
                                placeholder="Select commission type"
                                error={errors?.commission_type}
                            />
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Commission Rate (%) *</label>
                            <input
                                type="number"
                                step="0.01"
                                name="commission_rate"
                                required
                                defaultValue={fields?.commission_rate ?? '10.00'}
                                className="ui-input mt-2"
                            />
                            {errors?.commission_rate ? <p className="mt-1 text-xs text-rose-600">{errors.commission_rate}</p> : null}
                        </div>

                        <div>
                            <label className="text-sm text-slate-600">Fixed Commission Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                name="fixed_commission_amount"
                                defaultValue={fields?.fixed_commission_amount ?? ''}
                                className="ui-input mt-2"
                            />
                            <p className="mt-1 text-xs text-slate-500">Only used if commission type is "Fixed Amount"</p>
                            {errors?.fixed_commission_amount ? (
                                <p className="mt-1 text-xs text-rose-600">{errors.fixed_commission_amount}</p>
                            ) : null}
                        </div>
                    </div>

                    <div>
                        <label className="text-sm text-slate-600">Payment Details</label>
                        <textarea
                            name="payment_details"
                            rows={1}
                            defaultValue={fields?.payment_details ?? ''}
                            className="ui-textarea mt-2"
                        />
                        <p className="mt-1 text-xs text-slate-500">Bank account, PayPal email, or other payment information</p>
                        {errors?.payment_details ? <p className="mt-1 text-xs text-rose-600">{errors.payment_details}</p> : null}
                    </div>

                    <div>
                        <label className="text-sm text-slate-600">Notes</label>
                        <textarea
                            name="notes"
                            rows={1}
                            defaultValue={fields?.notes ?? ''}
                            className="ui-textarea mt-2"
                        />
                        {errors?.notes ? <p className="mt-1 text-xs text-rose-600">{errors.notes}</p> : null}
                    </div>

                    <div className="flex justify-end gap-4 pt-6">
                        <a
                            href={is_edit ? routes?.show : routes?.index}
                            data-native="true"
                            className="ui-btn-secondary"
                        >
                            Cancel
                        </a>
                        <button type="submit" className="ui-btn-primary">
                            {is_edit ? 'Update affiliate' : 'Create affiliate'}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
