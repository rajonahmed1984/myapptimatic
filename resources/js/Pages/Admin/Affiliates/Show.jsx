import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({
    pageTitle = 'Affiliate Details',
    affiliate = {},
    stats = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Affiliates</div>
                    <h1 className="text-2xl font-semibold text-slate-900">{affiliate.customer_name}</h1>
                </div>
                <div className="flex gap-3">
                    <a
                        href={routes?.edit}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-6 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Edit
                    </a>
                    <form
                        method="POST"
                        action={routes?.destroy}
                        data-native="true"
                        onSubmit={(event) => {
                            if (!window.confirm(`Delete affiliate ${affiliate.customer_name}?`)) {
                                event.preventDefault();
                            }
                        }}
                    >
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <button
                            type="submit"
                            className="rounded-full border border-rose-200 px-6 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300"
                        >
                            Delete
                        </button>
                    </form>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Total Earned</div>
                    <div className="mt-2 text-3xl font-bold text-slate-900">{affiliate.total_earned_display}</div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Balance</div>
                    <div className="mt-2 text-3xl font-bold text-teal-600">{affiliate.balance_display}</div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Conversion Rate</div>
                    <div className="mt-2 text-3xl font-bold text-slate-900">{stats.conversion_rate}%</div>
                </div>
            </div>

            <div className="card mt-6 p-6">
                <h2 className="text-lg font-semibold text-slate-900">Affiliate Information</h2>
                <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-2">
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                        <div className="mt-1 font-semibold text-slate-900">{affiliate.customer_name}</div>
                        <div className="text-xs text-slate-500">{affiliate.customer_email}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Code</div>
                        <div className="mt-1 font-mono text-sm text-slate-800">{affiliate.affiliate_code}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</div>
                        <div className="mt-1 text-sm text-slate-700">{affiliate.status_label}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Commission</div>
                        <div className="mt-1 text-sm text-slate-700">{affiliate.commission_display}</div>
                    </div>
                </div>
            </div>

            <div className="card mt-6 p-6">
                <h2 className="text-lg font-semibold text-slate-900">Performance</h2>
                <div className="mt-4 grid gap-3 text-sm text-slate-700 md:grid-cols-3">
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total Clicks</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{stats.total_clicks}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Referrals</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{affiliate.total_referrals}</div>
                    </div>
                    <div>
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Conversions</div>
                        <div className="mt-1 text-xl font-semibold text-slate-900">{stats.total_conversions}</div>
                    </div>
                </div>
            </div>

            <div className="mt-6">
                <a
                    href={routes?.index}
                    data-native="true"
                    className="text-sm font-semibold text-slate-500 hover:text-teal-600"
                >
                    Back to affiliates
                </a>
            </div>
        </>
    );
}
