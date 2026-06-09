import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

const driverFieldConfig = {
    manual: {
        textFields: ['instructions', 'payment_url', 'account_name', 'account_number', 'bank_name', 'branch', 'routing_number', 'button_label'],
        toggleFields: [],
        showProcessingCurrency: false,
    },
    bkash: {
        textFields: ['merchant_number', 'instructions', 'payment_url', 'account_name', 'account_number', 'button_label'],
        toggleFields: [],
        showProcessingCurrency: false,
    },
    bkash_api: {
        textFields: ['username', 'password', 'app_key', 'app_secret', 'instructions', 'button_label'],
        toggleFields: ['sandbox'],
        showProcessingCurrency: false,
    },
    sslcommerz: {
        textFields: ['store_id', 'store_password', 'instructions', 'payment_url', 'button_label'],
        toggleFields: ['easy_checkout', 'sandbox'],
        showProcessingCurrency: true,
    },
    paypal: {
        textFields: ['instructions', 'payment_url', 'paypal_email', 'api_username', 'api_password', 'api_signature', 'client_id', 'client_secret', 'button_label'],
        toggleFields: ['sandbox', 'force_one_time', 'force_subscriptions', 'require_shipping', 'client_address_matching'],
        showProcessingCurrency: false,
    },
};

const secureFieldsConfig = {
    4: ['api_password', 'api_signature', 'client_secret'],
    5: ['password', 'app_key', 'app_secret'],
};

function formatLabel(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function Edit({ pageTitle = 'Edit Payment Gateway', gateway = {}, currency_options = [], routes = {}, default_currency = '' }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = gateway?.fields || {};
    const driver = String(gateway?.driver || '').toLowerCase();
    const config = driverFieldConfig[driver] || {
        textFields: ['instructions', 'payment_url'],
        toggleFields: [],
        showProcessingCurrency: false,
    };
    
    const gatewayId = gateway?.id ? Number(gateway.id) : null;
    let textFields = config.textFields || [];

    if (gatewayId === 2) {
        const toRemove = ['merchant_number', 'instructions', 'payment_url', 'account_name'];
        textFields = textFields.filter((field) => !toRemove.includes(field));
    } else if (gatewayId === 9) {
        const toRemove = ['instructions', 'payment_url', 'account_name', 'branch', 'routing_number'];
        textFields = textFields.filter((field) => !toRemove.includes(field));
    } else if (gatewayId === 6) {
        const toRemove = ['instructions', 'payment_url', 'account_name', 'account_number', 'bank_name', 'branch', 'routing_number'];
        textFields = textFields.filter((field) => !toRemove.includes(field));
    }

    const [visibleSecureFields, setVisibleSecureFields] = React.useState({});
    const toggleFields = config.toggleFields || [];
    const currencyOptions = [
        { value: '', label: 'Select currency' },
        ...currency_options.map((currency) => ({ value: String(currency), label: currency })),
    ];

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                        <p className="text-sm text-slate-500">
                            Driver: <span className="font-medium text-slate-700">{gateway?.driver || '--'}</span>
                        </p>
                    </div>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to gateways
                    </a>
                </div>

                <form action={routes?.update} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Name</label>
                            <input name="name" defaultValue={gateway?.name || ''} className="ui-input" />
                            {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Sort Order</label>
                            <input
                                type="number"
                                min="0"
                                name="sort_order"
                                defaultValue={gateway?.sort_order ?? 0}
                                className="ui-input"
                            />
                        </div>
                        <div className="flex items-center gap-4 pt-7">
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="hidden" name="is_active" value="0" />
                                <input type="checkbox" name="is_active" value="1" defaultChecked={Boolean(gateway?.is_active)} />
                                Active
                            </label>
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="hidden" name="deactivate" value="0" />
                                <input type="checkbox" name="deactivate" value="1" defaultChecked={Boolean(gateway?.deactivate)} />
                                Deactivate
                            </label>
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {textFields.map((field) => {
                            const isSecureField = secureFieldsConfig[gatewayId]?.includes(field);
                            const isFieldVisible = visibleSecureFields[field];

                            return (
                                <div key={field}>
                                    <label className="mb-1 block text-sm font-medium text-slate-700">{formatLabel(field)}</label>
                                    <div className="relative">
                                        <input
                                            type={isSecureField && !isFieldVisible ? 'password' : 'text'}
                                            name={field}
                                            defaultValue={fields?.[field] || ''}
                                            className={`ui-input ${isSecureField ? 'pr-10' : ''}`}
                                        />
                                        {isSecureField ? (
                                            <button
                                                type="button"
                                                onClick={() => setVisibleSecureFields((prev) => ({ ...prev, [field]: !prev[field] }))}
                                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-700 focus:outline-none"
                                            >
                                                {isFieldVisible ? (
                                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                ) : (
                                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                                    </svg>
                                                )}
                                            </button>
                                        ) : null}
                                    </div>
                                    {errors?.[field] ? <p className="mt-1 text-xs text-rose-600">{errors[field]}</p> : null}
                                </div>
                            );
                        })}
                    </div>

                    {config.showProcessingCurrency ? (
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Processing Currency</label>
                                <SearchableSelect
                                    name="processing_currency"
                                    defaultValue={String(fields?.processing_currency || default_currency || '')}
                                    options={currencyOptions}
                                    placeholder="Select currency"
                                />
                            </div>
                        </div>
                    ) : null}

                    {toggleFields.length > 0 ? (
                        <div className="flex flex-wrap gap-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            {toggleFields.map((field) => (
                                <label key={field} className="inline-flex items-center gap-2 text-sm text-slate-700">
                                    <input type="hidden" name={field} value="0" />
                                    <input type="checkbox" name={field} value="1" defaultChecked={Boolean(fields?.[field])} />
                                    {formatLabel(field)}
                                </label>
                            ))}
                        </div>
                    ) : null}

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="ui-btn-primary">
                            Save Changes
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
