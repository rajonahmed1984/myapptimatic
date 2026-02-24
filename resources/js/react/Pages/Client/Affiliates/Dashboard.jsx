import React from 'react';
import { Head } from '@inertiajs/react';

export default function Dashboard({ affiliate = {}, stats = {}, routes = {} }) {
    const copyLink = async () => {
        const text = affiliate.referral_link || '';
        if (!text) return;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                if (typeof window.notify === 'function') window.notify('Link copied to clipboard!', 'success');
                return;
            }
        } catch (_error) {
            // fallback below
        }

        const input = document.getElementById('referral-link');
        if (!input) return;
        input.select();
        const copied = document.execCommand('copy');
        if (typeof window.notify === 'function') window.notify(copied ? 'Link copied to clipboard!' : 'Unable to copy link.', copied ? 'success' : 'error');
    };

    return (
        <>
            <Head title="Affiliate Dashboard" />

            <div className="mb-6">
                <div className="section-label">Affiliate Program</div>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">Your affiliate dashboard</h1>
            </div>

            {affiliate.status !== 'active' ? (
                <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                    Your affiliate account is {affiliate.status}. {affiliate.status === 'inactive' ? 'It is pending approval.' : ''}
                </div>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-4">
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Balance</div>
                    <div className="mt-2 text-3xl font-bold text-teal-600">${Number(affiliate.balance || 0).toFixed(2)}</div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Total Earned</div>
                    <div className="mt-2 text-3xl font-bold text-slate-900">${Number(affiliate.total_earned || 0).toFixed(2)}</div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Clicks</div>
                    <div className="mt-2 text-3xl font-bold text-slate-900">{stats.total_clicks || 0}</div>
                </div>
                <div className="card p-6">
                    <div className="text-xs uppercase tracking-wider text-slate-400">Conversions</div>
                    <div className="mt-2 text-3xl font-bold text-slate-900">{stats.total_conversions || 0}</div>
                </div>
            </div>

            <div className="card mt-6 p-6">
                <h2 className="text-lg font-semibold text-slate-900">Your Referral Link</h2>
                <div className="mt-4 flex gap-3">
                    <input id="referral-link" type="text" readOnly value={affiliate.referral_link || ''} className="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm" />
                    <button onClick={copyLink} type="button" className="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white hover:bg-teal-400">
                        Copy Link
                    </button>
                </div>
                <p className="mt-2 text-xs text-slate-500">Share this link to earn commissions on referrals.</p>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Commission Summary</h2>
                    <div className="mt-4 space-y-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-600">Pending</span>
                            <span className="font-semibold">${Number(stats.pending_commissions || 0).toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-600">Approved</span>
                            <span className="font-semibold">${Number(stats.approved_commissions || 0).toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-slate-600">Paid</span>
                            <span className="font-semibold">${Number(stats.paid_commissions || 0).toFixed(2)}</span>
                        </div>
                    </div>
                    <a href={routes.commissions} data-native="true" className="mt-4 inline-block text-sm text-teal-600 hover:text-teal-500">
                        View all commissions
                    </a>
                </div>

                <div className="card p-6">
                    <h2 className="text-lg font-semibold text-slate-900">Recent Referrals</h2>
                    {(stats.recent_referrals || []).length === 0 ? (
                        <p className="mt-4 text-sm text-slate-600">No referrals yet.</p>
                    ) : (
                        <div className="mt-4 space-y-3">
                            {stats.recent_referrals.slice(0, 5).map((referral) => (
                                <div key={referral.id} className="flex items-center justify-between text-sm">
                                    <div>
                                        <div className="font-semibold">{referral.customer_name}</div>
                                        <div className="text-xs text-slate-500">{referral.created_at_display}</div>
                                    </div>
                                    <span className={`text-xs ${referral.status === 'converted' ? 'text-emerald-600' : 'text-amber-600'}`}>{referral.status_label}</span>
                                </div>
                            ))}
                        </div>
                    )}
                    <a href={routes.referrals} data-native="true" className="mt-4 inline-block text-sm text-teal-600 hover:text-teal-500">
                        View all referrals
                    </a>
                </div>
            </div>
        </>
    );
}
