import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (status) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }

    if (status === 'inactive') {
        return 'bg-slate-200 text-slate-700 border-slate-300';
    }

    return 'bg-slate-100 text-slate-600 border-slate-200';
};

export default function Index({ pageTitle = 'Products', routes = {}, products = [] }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="text-2xl font-semibold text-slate-900">Products</h1>
                <a href={routes?.create} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    New Product
                </a>
            </div>

            <div id="productsTableWrap" className="card overflow-x-auto">
                <table className="w-full min-w-[800px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">SL</th>
                            <th className="px-4 py-3">Name</th>
                            <th className="px-4 py-3">Slug</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {products.length > 0 ? (
                            products.map((product) => (
                                <tr key={product.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-500">{product.serial}</td>
                                    <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                                    <td className="px-4 py-3 text-slate-500">{product.slug}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(product.status)}`}>
                                            {product.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3">
                                            <a href={product?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                Edit
                                            </a>
                                            <form
                                                method="POST"
                                                action={product?.routes?.destroy}
                                                data-native="true"
                                                onSubmit={(event) => {
                                                    if (!window.confirm('Delete this product?')) {
                                                        event.preventDefault();
                                                    }
                                                }}
                                            >
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan={5} className="px-4 py-6 text-center text-slate-500">
                                    No products yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}