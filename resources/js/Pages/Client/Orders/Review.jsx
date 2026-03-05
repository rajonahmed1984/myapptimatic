import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Review({
    plan = {},
    currency = 'USD',
    start_date_display = '--',
    period_end_display = '--',
    subtotal = 0,
    periodDays = null,
    cycleDays = null,
    showProration = false,
    dueDays = 0,
    routes = {},
}) {
    const { csrf_token: csrfToken } = usePage().props;

    return (
        <>
            <Head title="Review & Checkout" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Review & Checkout</h1>
                    <p className="mt-1 text-sm text-slate-500">Confirm your plan details before placing the order.</p>
                </div>
                <a href={routes.index} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to products
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="card p-6 lg:col-span-2">
                    <div className="section-label">Plan details</div>
                    <div className="mt-4 space-y-3 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Product</span>
                            <span className="font-semibold text-slate-900">{plan.product_name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Plan</span>
                            <span className="font-semibold text-slate-900">{plan.name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Interval</span>
                            <span className="font-semibold text-slate-900">{plan.interval_label}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Billing period</span>
                            <span className="font-semibold text-slate-900">
                                {start_date_display} -&gt; {period_end_display}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Invoice due</span>
                            <span className="font-semibold text-slate-900">{dueDays} day(s) after issue</span>
                        </div>
                    </div>
                </div>

                <div className="card p-6">
                    <div className="section-label">Summary</div>
                    <div className="mt-4 text-sm text-slate-600">
                        <div className="flex items-center justify-between">
                            <span>Subtotal</span>
                            <span className="font-semibold text-slate-900">
                                {currency} {Number(subtotal).toFixed(2)}
                            </span>
                        </div>
                        {showProration && cycleDays ? (
                            <div className="mt-1 text-xs text-slate-500">
                                Prorated for {periodDays}/{cycleDays} days
                            </div>
                        ) : null}
                        <div className="mt-2 flex items-center justify-between">
                            <span>Total</span>
                            <span className="text-lg font-semibold text-slate-900">
                                {currency} {Number(subtotal).toFixed(2)}
                            </span>
                        </div>
                    </div>

                    <form method="POST" action={routes.store} data-native="true" className="mt-6">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="plan_id" value={plan.id} />
                        <button type="submit" className="w-full rounded-full bg-teal-500 px-4 py-3 text-sm font-semibold text-white">
                            Place order
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
