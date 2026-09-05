import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

export default function Index({ pageTitle = 'Leave Types', types = [], pagination = {}, editingType = null, routes = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="grid gap-6 lg:grid-cols-5">
                <div className="card p-6 lg:col-span-2">
                    <div className="section-label">Add leave type</div>

                    <form method="POST" action={routes?.store} data-native="true" className="mt-4 grid gap-3 text-sm">
                        <input type="hidden" name="_token" value={csrf} />
                        <input name="name" defaultValue="" placeholder="Name" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                        <input name="code" defaultValue="" placeholder="Code" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                        <div className="flex items-center gap-2">
                            <input type="checkbox" name="is_paid" value="1" className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                            <span className="text-xs text-slate-600">Paid</span>
                        </div>
                        <input type="number" step="0.01" name="default_allocation" defaultValue="" placeholder="Default days" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                        <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add</button>
                    </form>

                    {editingType ? (
                        <div className="mt-8 border-t border-slate-200 pt-6">
                            <div className="section-label">Edit leave type</div>
                            <form method="POST" action={editingType.routes?.update} data-native="true" className="mt-4 grid gap-3 text-sm">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />
                                <input name="name" defaultValue={editingType.name || ''} placeholder="Name" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                                <input name="code" defaultValue={editingType.code || ''} placeholder="Code" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                                <div className="flex items-center gap-2">
                                    <input type="checkbox" name="is_paid" value="1" defaultChecked={Boolean(editingType.is_paid)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                    <span className="text-xs text-slate-600">Paid</span>
                                </div>
                                <input type="number" step="0.01" name="default_allocation" defaultValue={editingType.default_allocation ?? ''} placeholder="Default days" className="rounded-full border border-slate-300 bg-white px-4 py-1.5 h-8 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                                <div className="flex items-center gap-3">
                                    <button className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
                                    <a href={routes?.index} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
                                </div>
                            </form>
                        </div>
                    ) : null}
                </div>

                <div className="card p-6 lg:col-span-3">
                    <DataTable
                        rows={types}
                        emptyMessage="No leave types yet."
                        columns={[
                            { key: 'name', header: 'Name', render: (type) => type.name },
                            { key: 'code', header: 'Code', render: (type) => type.code },
                            { key: 'paid', header: 'Paid', render: (type) => (type.is_paid ? 'Paid' : 'Unpaid') },
                            { key: 'default', header: 'Default', render: (type) => type.default_allocation ?? 'inf' },
                            {
                                key: 'actions',
                                header: 'Actions',
                                headerClassName: 'text-right',
                                render: (type) => (
                                    <div className="flex items-center justify-end gap-3">
                                        <a href={type.routes?.edit} data-native="true" className="text-xs font-semibold text-slate-700 hover:text-slate-900">Edit</a>
                                        <form method="POST" action={type.routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete leave type ${type.name}?`) && e.preventDefault()}>
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                        </form>
                                    </div>
                                ),
                            },
                        ]}
                        renderMobileCard={(type) => (
                            <MobileCard
                                title={type.name}
                                subtitle={type.code}
                                badge={type.is_paid ? 'Paid' : 'Unpaid'}
                                badgeColor={type.is_paid ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}
                                metrics={[{ label: 'Default Days', value: type.default_allocation ?? 'inf' }]}
                                actions={
                                    <>
                                        <a
                                            href={type.routes?.edit}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                        >
                                            Edit
                                        </a>
                                        <form method="POST" action={type.routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete leave type ${type.name}?`) && e.preventDefault()}>
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="_method" value="DELETE" />
                                            <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                        </form>
                                    </>
                                }
                            />
                        )}
                    />

                    {pagination?.has_pages ? (
                        <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                            {pagination.previous_url ? <a href={pagination.previous_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Previous</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>}
                            {pagination.next_url ? <a href={pagination.next_url} data-native="true" className="rounded-full border border-slate-300 px-3 py-1 text-slate-700">Next</a> : <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
