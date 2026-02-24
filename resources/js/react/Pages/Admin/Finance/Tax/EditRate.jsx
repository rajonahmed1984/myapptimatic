import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function EditRate({ pageTitle = 'Edit Tax Rate', rate = {}, routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to tax settings
                    </a>
                </div>

                <form action={routes?.update} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input name="name" defaultValue={rate?.name || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        {errors?.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Rate Percent</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="rate_percent"
                            defaultValue={rate?.rate_percent || ''}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        {errors?.rate_percent ? <p className="mt-1 text-xs text-rose-600">{errors.rate_percent}</p> : null}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Effective From</label>
                            <input
                                type="date"
                                name="effective_from"
                                defaultValue={rate?.effective_from || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.effective_from ? <p className="mt-1 text-xs text-rose-600">{errors.effective_from}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Effective To</label>
                            <input
                                type="date"
                                name="effective_to"
                                defaultValue={rate?.effective_to || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.effective_to ? <p className="mt-1 text-xs text-rose-600">{errors.effective_to}</p> : null}
                        </div>
                    </div>

                    <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" name="is_active" value="1" defaultChecked={Boolean(rate?.is_active)} />
                        Active
                    </label>

                    <div>
                        <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Update Rate
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
