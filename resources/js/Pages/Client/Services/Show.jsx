import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

function CancellationCard({ cancellation = {}, routes = {}, csrfToken = '', errors = {} }) {
    const [open, setOpen] = useState(false);
    const [type, setType] = useState('end_of_period');

    return (
        <div className="card p-6 lg:col-span-2">
            <div className="section-label">Cancellation</div>

            {errors?.cancellation ? (
                <p className="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                    {errors.cancellation}
                </p>
            ) : null}

            {cancellation.already_cancelled ? (
                <p className="mt-3 text-sm text-slate-500">This service is already cancelled.</p>
            ) : cancellation.pending ? (
                <div className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                    <p className="text-sm font-semibold text-amber-800">Cancellation request under review</p>
                    <p className="mt-1 text-sm text-amber-700">
                        {cancellation.pending.type_label} · submitted {cancellation.pending.requested_at_display}
                    </p>
                    <p className="mt-2 whitespace-pre-line text-sm text-amber-700">{cancellation.pending.reason}</p>
                </div>
            ) : cancellation.scheduled ? (
                <p className="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    This service is scheduled to end on <strong>{cancellation.period_end_display}</strong>. No further
                    invoices will be raised.
                </p>
            ) : !open ? (
                <>
                    <p className="mt-3 text-sm text-slate-500">
                        Need to stop this service? Send us a cancellation request and our team will review it. Any
                        invoices already raised stay payable.
                    </p>
                    <button
                        type="button"
                        onClick={() => setOpen(true)}
                        className="mt-4 rounded-full border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:bg-rose-50"
                    >
                        Request cancellation
                    </button>
                </>
            ) : (
                <form method="POST" action={routes.request_cancellation} data-native="true" className="mt-4 space-y-4">
                    <input type="hidden" name="_token" value={csrfToken} />

                    <div className="space-y-2">
                        <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-3 hover:border-teal-300">
                            <input
                                type="radio"
                                name="type"
                                value="end_of_period"
                                checked={type === 'end_of_period'}
                                onChange={() => setType('end_of_period')}
                                className="mt-1"
                            />
                            <span className="text-sm">
                                <span className="font-semibold text-slate-900">At the end of the billing period</span>
                                <span className="mt-0.5 block text-slate-500">
                                    Keep using the service until {cancellation.period_end_display}, then it stops. Nothing
                                    new is invoiced.
                                </span>
                            </span>
                        </label>

                        <label className="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 p-3 hover:border-rose-300">
                            <input
                                type="radio"
                                name="type"
                                value="immediate"
                                checked={type === 'immediate'}
                                onChange={() => setType('immediate')}
                                className="mt-1"
                            />
                            <span className="text-sm">
                                <span className="font-semibold text-slate-900">Immediately</span>
                                <span className="mt-0.5 block text-slate-500">
                                    The service stops as soon as the request is approved. The rest of the paid period is
                                    not refunded.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Why are you cancelling?</label>
                        <textarea
                            name="reason"
                            rows={3}
                            required
                            minLength={5}
                            placeholder="This helps us improve our service."
                            className="ui-input w-full"
                        />
                        {errors?.reason ? <p className="mt-1 text-xs text-rose-600">{errors.reason}</p> : null}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="submit"
                            className="rounded-full bg-rose-600 px-5 py-2 text-sm font-semibold text-white hover:bg-rose-500"
                        >
                            Submit request
                        </button>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="rounded-full border border-slate-300 px-5 py-2 text-sm font-semibold text-slate-600 hover:border-slate-400"
                        >
                            Never mind
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Show({ service = {}, licenses = [], cancellation = {}, routes = {} }) {
    const { csrf_token: csrfToken = '', errors = {}, flash = {} } = usePage().props || {};

    return (
        <>
            <Head title="Service Details" />

            {flash?.status ? (
                <div className="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {flash.status}
                </div>
            ) : null}

            <div className="mb-6 flex flex-wrap items-center justify-end gap-4">
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

                <CancellationCard
                    cancellation={cancellation}
                    routes={routes}
                    csrfToken={csrfToken}
                    errors={errors}
                />
            </div>
        </>
    );
}
