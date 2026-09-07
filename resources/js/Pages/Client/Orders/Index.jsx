import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ has_customer = false, products = [], currency = 'USD', routes = {} }) {
    return (
        <>
            <Head title="Order Services" />

            {!has_customer ? (
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-6 text-sm text-slate-600">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            ) : products.length === 0 ? (
                <div className="rounded-2xl border border-slate-200 bg-white/80 p-6 text-sm text-slate-600">No active products are available right now. Please check back later.</div>
            ) : (
                <div className="space-y-8">
                    {products.map((product) => (
                        <div key={product.id} className="space-y-4">
                            <div>
                                <div className="section-label">Product</div>
                                <div className="mt-1 text-xl font-semibold text-slate-900">{product.name}</div>
                                {product.description ? <p className="mt-1 text-sm text-slate-500">{product.description}</p> : null}
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                {product.plans.map((plan) => (
                                    <div key={plan.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs hover:border-teal-200 transition-colors">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm text-slate-500">{plan.interval_label} plan</span>
                                                    {plan.is_per_flat || plan.pricing_model === 'per_flat' ? (
                                                        <span className="inline-block rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-800">
                                                            Per Flat
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <div className="mt-1 text-lg font-semibold text-slate-900">{plan.name}</div>
                                                <div className="mt-2 text-sm font-semibold text-slate-700">
                                                    {currency} {Number(plan.price).toFixed(2)}
                                                    {plan.is_per_flat || plan.pricing_model === 'per_flat' ? (
                                                        <span className="text-xs font-normal text-slate-500"> / flat / {plan.interval_label.toLowerCase()}</span>
                                                    ) : null}
                                                </div>
                                                {plan.is_per_flat || plan.pricing_model === 'per_flat' ? (
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        Floor &amp; flat breakdown configured at next step.
                                                    </p>
                                                ) : null}
                                            </div>
                                            <form method="GET" action={routes.review} data-native="true">
                                                <input type="hidden" name="plan_id" value={plan.id} />
                                                <button type="submit" className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-teal-500 transition">
                                                    {plan.is_per_flat || plan.pricing_model === 'per_flat' ? 'Configure & Order' : 'Review & checkout'}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
