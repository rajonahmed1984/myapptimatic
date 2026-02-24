import React from 'react';
import { Head } from '@inertiajs/react';

export default function Show({ service = {}, licenses = [], routes = {} }) {
    return (
        <>
            <Head title="Service Details" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Service Details</h1>
                    <p className="mt-1 text-sm text-slate-500">Review the service configuration and license coverage.</p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to services
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <div className="section-label">Service</div>
                    <h2 className="mt-2 text-xl font-semibold text-slate-900">{service.name}</h2>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Plan</span>
                            <span className="font-semibold text-slate-900">{service.plan_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Status</span>
                            <span className="font-semibold text-slate-900">{service.status_label}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Billing cycle</span>
                            <span className="font-semibold text-slate-900">{service.cycle_label}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Start date</span>
                            <span className="font-semibold text-slate-900">{service.start_date_display}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Current period</span>
                            <span className="font-semibold text-slate-900">
                                {service.period_start_display} - {service.period_end_display}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Auto renew</span>
                            <span className="font-semibold text-slate-900">{service.auto_renew_label}</span>
                        </div>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">License</div>
                    <h3 className="mt-2 text-lg font-semibold text-slate-900">Licensed domains</h3>
                    {licenses.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">No licenses associated with this service yet.</p>
                    ) : (
                        <div className="mt-4 space-y-4 text-sm text-slate-600">
                            {licenses.map((license) => (
                                <div key={license.id} className="rounded-2xl border border-slate-200 bg-white/70 p-4">
                                    <div className="text-xs uppercase tracking-[0.25em] text-slate-400">License key</div>
                                    <div className="mt-1 font-mono text-sm text-slate-700">{license.masked_key}</div>
                                    <div className="mt-3 text-xs uppercase tracking-[0.25em] text-slate-400">Domains</div>
                                    {license.domains.length === 0 ? (
                                        <div className="mt-1 text-sm text-slate-500">No domains registered.</div>
                                    ) : (
                                        <div className="mt-1 space-y-1 text-sm text-slate-600">
                                            {license.domains.map((domain, index) => (
                                                <div key={`${license.id}-${index}`}>{domain}</div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
