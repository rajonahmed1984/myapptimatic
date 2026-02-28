import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    rep = {},
    balance = {},
    earned_this_month = 0,
    paid_this_month = 0,
    recent_earnings = [],
    recent_payouts = [],
    tasks_widget = {},
    routes = {},
}) {
    return (
        <>
            <Head title="Sales Rep Dashboard" />

            <div className="card space-y-6 p-6">
                <div>
                    <div className="section-label">Commissions</div>
                    <h1 className="text-2xl font-semibold text-slate-900">Welcome, {rep?.name}</h1>
                    <div className="text-sm text-slate-500">View earnings, payouts, and balances.</div>
                </div>

                <div className="grid gap-4 text-sm text-slate-700 md:grid-cols-4">
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Payable balance</div><div className="mt-2 text-2xl font-semibold text-slate-900">{Number(balance?.payable_balance || 0).toFixed(2)}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Total earned</div><div className="mt-2 text-2xl font-semibold text-slate-900">{Number(balance?.total_earned || 0).toFixed(2)}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Earned this month</div><div className="mt-2 text-2xl font-semibold text-slate-900">{Number(earned_this_month || 0).toFixed(2)}</div></div>
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4"><div className="text-xs uppercase tracking-[0.2em] text-slate-400">Paid this month</div><div className="mt-2 text-2xl font-semibold text-slate-900">{Number(paid_this_month || 0).toFixed(2)}</div></div>
                </div>

                {tasks_widget?.show ? (
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm">
                        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Tasks</div>
                        <div className="mt-2 text-slate-600">Open: {tasks_widget?.summary?.open ?? 0} | In progress: {tasks_widget?.summary?.in_progress ?? 0}</div>
                    </div>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="flex items-center justify-between"><div className="text-sm font-semibold text-slate-800">Recent earnings</div><a href={routes?.earnings} data-native="true" className="text-xs font-semibold text-emerald-700">View all</a></div>
                        <div className="mt-3 space-y-2">
                            {recent_earnings.length === 0 ? <div className="text-sm text-slate-500">No earnings yet.</div> : recent_earnings.map((earning) => (
                                <div key={earning.id} className="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                                    <div className="flex items-center justify-between"><div className="font-semibold text-slate-900">#{earning.id} - {earning.source_type}</div><div className="text-xs text-slate-500">{earning.earned_at_display}</div></div>
                                    <div className="text-xs text-slate-600">Commission: {Number(earning.commission_amount || 0).toFixed(2)} {earning.currency}</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                        <div className="flex items-center justify-between"><div className="text-sm font-semibold text-slate-800">Recent payouts</div><a href={routes?.payouts} data-native="true" className="text-xs font-semibold text-emerald-700">View all</a></div>
                        <div className="mt-3 space-y-2">
                            {recent_payouts.length === 0 ? <div className="text-sm text-slate-500">No payouts yet.</div> : recent_payouts.map((payout) => (
                                <div key={payout.id} className="rounded-xl border border-slate-200 bg-white/70 px-3 py-2">
                                    <div className="flex items-center justify-between"><div className="font-semibold text-slate-900">Payout #{payout.id}</div><div className="text-xs text-slate-500">{payout.paid_at_display}</div></div>
                                    <div className="text-xs text-slate-600">Amount: {Number(payout.total_amount || 0).toFixed(2)} {payout.currency}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Index.title = 'Sales Dashboard';
