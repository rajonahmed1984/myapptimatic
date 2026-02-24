import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function PublicProductsShow({
    product,
    plans = [],
    can_order_as_client = false,
    has_customer_profile = false,
    routes = {},
}) {
    const authUser = usePage().props?.auth?.user ?? null;

    return (
        <>
            <Head title={product?.name ?? 'Product'} />
            <div className="card p-6">
                <div className="section-label">Product</div>
                <div className="mt-2 text-2xl font-semibold text-slate-900">{product?.name}</div>
                {product?.description ? <p className="mt-2 text-sm text-slate-500">{product.description}</p> : null}

                {plans.length === 0 ? (
                    <div className="mt-6 rounded-2xl border border-slate-200 bg-white/70 p-4 text-sm text-slate-600">
                        No active plans are available right now. Please check back later.
                    </div>
                ) : (
                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        {plans.map((plan) => (
                            <div
                                className={`card-muted p-4 ${plan.is_selected ? 'ring-2 ring-teal-300' : ''}`}
                                key={plan.id}
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <div className="text-sm text-slate-500">{plan.interval} plan</div>
                                        <div className="mt-1 text-lg font-semibold text-slate-900">{plan.name}</div>
                                        <div className="mt-2 text-sm text-slate-600">{plan.price_display}</div>
                                    </div>

                                    {can_order_as_client ? (
                                        <a
                                            href={plan.routes?.review}
                                            className="rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white"
                                        >
                                            Review & checkout
                                        </a>
                                    ) : authUser ? (
                                        <div className="text-xs text-slate-500">Login as client to order</div>
                                    ) : (
                                        <div className="flex flex-col gap-2 text-xs">
                                            <a
                                                href={plan.routes?.login}
                                                className="rounded-full border border-slate-200 px-3 py-2 text-center text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                            >
                                                Sign in to order
                                            </a>
                                            <a
                                                href={plan.routes?.register}
                                                className="rounded-full bg-teal-500 px-3 py-2 text-center font-semibold text-white"
                                            >
                                                Create account
                                            </a>
                                            <a
                                                href={plan.routes?.plan}
                                                className="text-center text-xs font-semibold text-teal-600 hover:text-teal-500"
                                            >
                                                View plan
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {can_order_as_client && !has_customer_profile ? (
                    <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                        Your account is not linked to a customer profile yet. Please contact support.
                    </div>
                ) : null}

                <div className="mt-6">
                    <a href={routes?.index} className="text-sm font-semibold text-teal-600 hover:text-teal-500">
                        Back to products
                    </a>
                </div>
            </div>
        </>
    );
}
