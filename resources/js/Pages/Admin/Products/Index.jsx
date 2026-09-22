import React, { useState, useMemo } from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3.5 py-1.5 font-semibold text-white hover:bg-teal-500 shadow-sm transition',
};

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
    const [searchTerm, setSearchTerm] = useState('');

    const filteredProducts = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();
        if (!query) return products;
        return products.filter(
            (p) =>
                String(p.name || '').toLowerCase().includes(query) ||
                String(p.slug || '').toLowerCase().includes(query)
        );
    }, [products, searchTerm]);

    const total = filteredProducts.length;

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <input
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Search products..."
                            className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {total > 0 ? 1 : 0} – {total} of {total} products
                        </span>
                    </div>

                    <a
                        href={routes?.create}
                        data-native="true"
                        className={BTN.primary}
                    >
                        New Product
                    </a>
                </div>

                <DataTable
                    framed={false}
                    rows={filteredProducts}
                    emptyMessage="No products found."
                    columns={[
                        { key: 'sl', header: 'SL', cellClassName: 'text-slate-500', render: (product) => product.serial },
                        {
                            key: 'name',
                            header: 'Name',
                            cellClassName: 'font-semibold text-slate-900',
                            render: (product) => <a href={product?.routes?.show} data-native="true" className="hover:text-teal-600 transition">{product.name}</a>,
                        },
                        { key: 'slug', header: 'Slug', cellClassName: 'text-slate-500', render: (product) => product.slug },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (product) => <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(product.status)}`}>{product.status_label}</span>,
                        },
                        { key: 'usage', header: 'Usage', cellClassName: 'text-slate-600 font-medium', render: (product) => Number(product.usage_count || 0) },
                        {
                            key: 'actions',
                            header: 'Action',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (product) => (
                                <div className="inline-flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href={product?.routes?.show} data-native="true" className="text-xs font-semibold text-slate-700 hover:text-teal-600 transition">View</a>
                                    <span className="text-slate-300">·</span>
                                    <a href={product?.routes?.edit} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-700 transition">Edit</a>
                                    <span className="text-slate-300">·</span>
                                    <form
                                        method="POST"
                                        action={product?.routes?.destroy}
                                        data-native="true"
                                        className="inline"
                                        onSubmit={(event) => { if (!window.confirm('Delete this product?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-700 transition">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(product) => (
                        <MobileCard
                            title={<a href={product?.routes?.show} data-native="true" className="hover:text-teal-600">{product.name}</a>}
                            subtitle={product.slug}
                            badge={product.status_label}
                            badgeColor={statusClass(product.status)}
                            metrics={[{ label: 'Usage', value: Number(product.usage_count || 0) }]}
                            actions={
                                <>
                                    <a
                                        href={product?.routes?.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={product?.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action={product?.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this product?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        />
                    )}
                />

                <div className="border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
                    Showing <span className="font-semibold text-slate-700">{total > 0 ? 1 : 0}</span> – <span className="font-semibold text-slate-700">{total}</span> of <span className="font-semibold text-slate-700">{total}</span> products
                </div>
            </div>
        </>
    );
}
