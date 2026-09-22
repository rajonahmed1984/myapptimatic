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

export default function Index({ pageTitle = 'Plans', routes = {}, plans = [] }) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [searchTerm, setSearchTerm] = useState('');

    const filteredPlans = useMemo(() => {
        const query = searchTerm.trim().toLowerCase();
        if (!query) return plans;
        return plans.filter(
            (p) =>
                String(p.name || '').toLowerCase().includes(query) ||
                String(p.slug_path || '').toLowerCase().includes(query) ||
                String(p.price_display || '').toLowerCase().includes(query)
        );
    }, [plans, searchTerm]);

    const total = filteredPlans.length;

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
                            placeholder="Search plans..."
                            className="w-full max-w-sm rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {total > 0 ? 1 : 0} – {total} of {total} plans
                        </span>
                    </div>

                    <a
                        href={routes?.create}
                        data-native="true"
                        className={BTN.primary}
                    >
                        New Plan
                    </a>
                </div>

                <DataTable
                    framed={false}
                    rows={filteredPlans}
                    emptyMessage="No plans found."
                    columns={[
                        { key: 'sl', header: 'SL', cellClassName: 'text-slate-500', render: (plan) => plan.serial },
                        {
                            key: 'plan',
                            header: 'Plan',
                            cellClassName: 'font-medium text-slate-900',
                            render: (plan) => (
                                <>
                                    {plan.name}
                                    {plan.is_per_flat ? (
                                        <span className="ml-2 inline-block rounded-md bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700 border border-teal-200">Per Flat</span>
                                    ) : null}
                                </>
                            ),
                        },
                        { key: 'slug', header: 'Slug', cellClassName: 'text-slate-500', render: (plan) => plan.slug_path },
                        { key: 'price', header: 'Price', cellClassName: 'text-slate-700 font-semibold', render: (plan) => plan.price_display },
                        { key: 'interval', header: 'Interval', cellClassName: 'text-slate-700', render: (plan) => plan.interval_label },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (plan) => <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(plan.status)}`}>{plan.status_label}</span>,
                        },
                        { key: 'usage', header: 'Usage', cellClassName: 'text-slate-600', render: (plan) => Number(plan.usage_count || 0) },
                        {
                            key: 'actions',
                            header: 'Action',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (plan) => (
                                <div className="inline-flex items-center justify-end gap-2 whitespace-nowrap">
                                    <a href={plan?.routes?.edit} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-700 transition">Edit</a>
                                    <span className="text-slate-300">·</span>
                                    <form
                                        method="POST"
                                        action={plan?.routes?.destroy}
                                        data-native="true"
                                        className="inline"
                                        onSubmit={(event) => { if (!window.confirm('Delete this plan?')) event.preventDefault(); }}
                                    >
                                        <input type="hidden" name="_token" value={csrfToken} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button type="submit" className="text-xs font-semibold text-rose-600 hover:text-rose-700 transition">Delete</button>
                                    </form>
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(plan) => (
                        <MobileCard
                            title={
                                <>
                                    {plan.name}
                                    {plan.is_per_flat ? (
                                        <span className="ml-2 inline-block rounded-md bg-teal-50 px-1.5 py-0.5 text-[10px] font-semibold text-teal-700 border border-teal-200">Per Flat</span>
                                    ) : null}
                                </>
                            }
                            subtitle={plan.slug_path}
                            badge={plan.status_label}
                            badgeColor={statusClass(plan.status)}
                            metrics={[
                                { label: 'Price', value: `${plan.price_display} / ${plan.interval_label}` },
                                { label: 'Usage', value: Number(plan.usage_count || 0) },
                            ]}
                            actions={
                                <>
                                    <a
                                        href={plan?.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form
                                        method="POST"
                                        action={plan?.routes?.destroy}
                                        data-native="true"
                                        onSubmit={(event) => { if (!window.confirm('Delete this plan?')) event.preventDefault(); }}
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
                    Showing <span className="font-semibold text-slate-700">{total > 0 ? 1 : 0}</span> – <span className="font-semibold text-slate-700">{total}</span> of <span className="font-semibold text-slate-700">{total}</span> plans
                </div>
            </div>
        </>
    );
}
