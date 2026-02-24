import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({ pageTitle = 'License', is_edit = false, products = [], subscriptions = [], domains = [], form = {}, routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
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

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Subscription</label>
                            <select name="subscription_id" defaultValue={fields?.subscription_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="">Select subscription</option>
                                {subscriptions.map((subscription) => (
                                    <option key={subscription.id} value={subscription.id}>
                                        {subscription.label}
                                    </option>
                                ))}
                            </select>
                            {errors?.subscription_id ? <p className="mt-1 text-xs text-rose-600">{errors.subscription_id}</p> : null}
                        </div>
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
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">License Key</label>
                            <input name="license_key" defaultValue={fields?.license_key || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.license_key ? <p className="mt-1 text-xs text-rose-600">{errors.license_key}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" defaultValue={fields?.status || 'active'} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="revoked">Revoked</option>
                            </select>
                            {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Starts At</label>
                            <input type="date" name="starts_at" defaultValue={fields?.starts_at || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.starts_at ? <p className="mt-1 text-xs text-rose-600">{errors.starts_at}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Expires At</label>
                            <input type="date" name="expires_at" defaultValue={fields?.expires_at || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.expires_at ? <p className="mt-1 text-xs text-rose-600">{errors.expires_at}</p> : null}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Allowed Domain</label>
                        <input
                            name="allowed_domains"
                            defaultValue={fields?.allowed_domains || ''}
                            placeholder="example.com"
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {errors?.allowed_domains ? <p className="mt-1 text-xs text-rose-600">{errors.allowed_domains}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                        <textarea name="notes" rows={4} defaultValue={fields?.notes || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            {is_edit ? 'Update License' : 'Create License'}
                        </button>
                        <a href={routes?.index} data-native="true" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">
                            Cancel
                        </a>
                    </div>
                </form>

                {is_edit && domains.length > 0 ? (
                    <div className="mt-8">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Domains</h2>
                        <div className="space-y-2">
                            {domains.map((domain) => (
                                <div key={domain.id} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
                                    <div>
                                        <div className="text-sm font-medium text-slate-900">{domain.domain}</div>
                                        <div className="text-xs text-slate-500">{domain.status_label}</div>
                                    </div>
                                    {domain.can_revoke ? (
                                        <form action={domain.routes?.revoke} method="POST" data-native="true">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <button type="submit" className="rounded-lg border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700">
                                                Revoke
                                            </button>
                                        </form>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
