import React from 'react';
import { Head } from '@inertiajs/react';

export default function PublicProductsIndex({ products = [], currency = 'USD' }) {
    return (
        <>
            <Head title="Products" />
            <div className="mb-6">
                <div className="section-label">Products</div>
                <h1 className="mt-2 text-3xl font-semibold text-slate-900">Choose a service plan</h1>
                <p className="mt-2 text-sm text-slate-600">Browse available products and start your order.</p>
            </div>

            {products.length === 0 ? (
                <div className="card p-6 text-sm text-slate-600">No active products are available right now.</div>
            ) : (
                <div className="grid gap-6 md:grid-cols-2">
                    {products.map((product) => (
                        <div className="card p-6" key={product.id}>
                            <div className="text-xl font-semibold text-slate-900">{product.name}</div>
                            {product.description ? (
                                <p className="mt-2 text-sm text-slate-500">{product.description}</p>
                            ) : null}
                            <div className="mt-3 flex items-center justify-between gap-4">
                                <div className="text-sm text-slate-600">
                                    {product.plan_count} plan(s)
                                    {product.min_price !== null ? (
                                        <>
                                            <span className="mx-2 text-slate-300">|</span>
                                            Starts at {currency} {Number(product.min_price).toFixed(2)}
                                        </>
                                    ) : null}
                                </div>
                                <a
                                    href={product.routes?.show}
                                    className="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                >
                                    View plans
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </>
    );
}
