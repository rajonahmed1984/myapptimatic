import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Settings({ affiliate = {}, routes = {} }) {
    const { csrf_token: csrfToken } = usePage().props;

    return (
        <>
            <Head title="Affiliate Settings" />

            <div className="card p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="section-label">Affiliate</div>
                        <h1 className="mt-2 text-2xl font-semibold text-slate-900">Settings</h1>
                    </div>
                    <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                        Dashboard
                    </a>
                </div>

                <form method="POST" action={routes.update} data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <input type="hidden" name="_method" value="PUT" />
                    <div>
                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Affiliate code</label>
                        <input disabled value={affiliate.code || ''} className="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-100 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="text-xs uppercase tracking-[0.2em] text-slate-500">Payment details</label>
                        <textarea
                            name="payment_details"
                            defaultValue={affiliate.payment_details || ''}
                            rows={6}
                            className="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm"
                        />
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" className="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-400">
                            Save settings
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
}
