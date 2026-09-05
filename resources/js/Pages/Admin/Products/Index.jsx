import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

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

            <DataTable
                rows={products}
                emptyMessage="No products yet."
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
                            <div className="flex items-center justify-end gap-3 font-semibold text-xs">
                                <a href={product?.routes?.show} data-native="true" className="text-slate-700 hover:text-teal-600">View</a>
                                <a href={product?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">Edit</a>
                                <form
                                    method="POST"
                                    action={product?.routes?.destroy}
                                    data-native="true"
                                    onSubmit={(event) => { if (!window.confirm('Delete this product?')) event.preventDefault(); }}
                                >
                                    <input type="hidden" name="_token" value={csrfToken} />
                                    <input type="hidden" name="_method" value="DELETE" />
                                    <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
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
        </>
    );
}
