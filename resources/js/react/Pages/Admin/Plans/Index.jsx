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

export default function Index({ pageTitle = 'Plans', routes = {}, plans = [] }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="text-2xl font-semibold text-slate-900">Plans</h1>
                <a href={routes?.create} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    New Plan
                </a>
            </div>

            <div id="plansTableWrap" className="card overflow-x-auto">
                <table className="w-full min-w-[900px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">SL</th>
                            <th className="px-4 py-3">Plan</th>
                            <th className="px-4 py-3">Slug</th>
                            <th className="px-4 py-3">Product</th>
                            <th className="px-4 py-3">Price</th>
                            <th className="px-4 py-3">Interval</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {plans.length > 0 ? (
                            plans.map((plan) => (
                                <tr key={plan.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-500">{plan.serial}</td>
                                    <td className="px-4 py-3 font-medium text-slate-900">{plan.name}</td>
                                    <td className="px-4 py-3 text-slate-500">{plan.slug_path}</td>
                                    <td className="px-4 py-3 text-slate-500">{plan.product_name}</td>
                                    <td className="px-4 py-3 text-slate-700">{plan.price_display}</td>
                                    <td className="px-4 py-3 text-slate-700">{plan.interval_label}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(plan.status)}`}>
                                            {plan.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3">
                                            <a href={plan?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                Edit
                                            </a>
                                            <form
                                                method="POST"
                                                action={plan?.routes?.destroy}
                                                data-native="true"
                                                onSubmit={(event) => {
                                                    if (!window.confirm('Delete this plan?')) {
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
                                <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                                    No plans yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}