import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({ pageTitle = 'Leave Types', types = [], pagination = {}, editingType = null, routes = {} }) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Leave types</div>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
                <div className="card p-6 lg:col-span-2">
                    <div className="section-label">Add leave type</div>

                    <form method="POST" action={routes?.store} data-native="true" className="mt-4 grid gap-3 text-sm">
                        <input type="hidden" name="_token" value={csrf} />
                        <input name="name" defaultValue="" placeholder="Name" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <input name="code" defaultValue="" placeholder="Code" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <div className="flex items-center gap-2">
                            <input type="checkbox" name="is_paid" value="1" className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                            <span className="text-xs text-slate-600">Paid</span>
                        </div>
                        <input type="number" step="0.01" name="default_allocation" defaultValue="" placeholder="Default days" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                        <button className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add</button>
                    </form>

                    {editingType ? (
                        <div className="mt-8 border-t border-slate-200 pt-6">
                            <div className="section-label">Edit leave type</div>
                            <form method="POST" action={editingType.routes?.update} data-native="true" className="mt-4 grid gap-3 text-sm">
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="_method" value="PUT" />
                                <input name="name" defaultValue={editingType.name || ''} placeholder="Name" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                <input name="code" defaultValue={editingType.code || ''} placeholder="Code" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                <div className="flex items-center gap-2">
                                    <input type="checkbox" name="is_paid" value="1" defaultChecked={Boolean(editingType.is_paid)} className="h-4 w-4 rounded border-slate-300 text-emerald-600" />
                                    <span className="text-xs text-slate-600">Paid</span>
                                </div>
                                <input type="number" step="0.01" name="default_allocation" defaultValue={editingType.default_allocation ?? ''} placeholder="Default days" className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
                                <div className="flex items-center gap-3">
                                    <button className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Update</button>
                                    <a href={routes?.index} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
                                </div>
                            </form>
                        </div>
                    ) : null}
                </div>

                <div className="card p-6 lg:col-span-3">
                    <div className="overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead>
                                <tr className="text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                    <th className="px-3 py-2">Name</th>
                                    <th className="px-3 py-2">Code</th>
                                    <th className="px-3 py-2">Paid</th>
                                    <th className="px-3 py-2">Default</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {types.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="px-3 py-4 text-center text-slate-500">No leave types yet.</td>
                                    </tr>
                                ) : (
                                    types.map((type) => (
                                        <tr key={type.id} className="border-b border-slate-100">
                                            <td className="px-3 py-2">{type.name}</td>
                                            <td className="px-3 py-2">{type.code}</td>
                                            <td className="px-3 py-2">{type.is_paid ? 'Paid' : 'Unpaid'}</td>
                                            <td className="px-3 py-2">{type.default_allocation ?? 'inf'}</td>
                                            <td className="px-3 py-2">
                                                <div className="flex items-center justify-end gap-3">
                                                    <a href={type.routes?.edit} data-native="true" className="text-xs font-semibold text-slate-700 hover:text-slate-900">Edit</a>
                                                    <form method="POST" action={type.routes?.destroy} data-native="true" onSubmit={(e) => !window.confirm(`Delete leave type ${type.name}?`) && e.preventDefault()}>
                                                        <input type="hidden" name="_token" value={csrf} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-500">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

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
