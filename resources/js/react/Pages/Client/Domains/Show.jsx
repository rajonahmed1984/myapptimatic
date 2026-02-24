import React from 'react';
import { Head } from '@inertiajs/react';

export default function Show({ domain = {}, routes = {} }) {
    return (
        <>
            <Head title="Domain Details" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Domain Details</h1>
                    <p className="mt-1 text-sm text-slate-500">Review domain status and license details.</p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to domains
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Domain</div>
                    <h2 className="mt-2 text-xl font-semibold text-slate-900">{domain.name}</h2>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Product</span>
                            <span className="font-semibold text-slate-900">{domain.product_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Plan</span>
                            <span className="font-semibold text-slate-900">{domain.plan_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Status</span>
                            <span className="font-semibold text-slate-900">{domain.status_label}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Verified</span>
                            <span className="font-semibold text-slate-900">{domain.verified_display}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Last seen</span>
                            <span className="font-semibold text-slate-900">{domain.last_seen_display}</span>
                        </div>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">License</div>
                    <h3 className="mt-2 text-lg font-semibold text-slate-900">License key</h3>
                    <div className="mt-3 rounded-2xl border border-slate-200 bg-white/70 p-4 font-mono text-sm text-slate-700">
                        {domain.masked_key}
                    </div>
                </div>
            </div>
        </>
    );
}
