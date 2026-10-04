import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import CommissionStatement from '../../../Components/Commission/CommissionStatement';

/* ─── Referral link ────────────────────────────────────────────────── */

function ReferralCard({ referral }) {
    const [copied, setCopied] = useState(false);

    if (!referral?.url) {
        return null;
    }

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(referral.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    return (
        <div className="rounded-2xl border border-slate-200/80 bg-white p-4 sm:p-5 shadow-sm">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <div className="text-xs font-semibold uppercase tracking-wider text-teal-700">Your referral link</div>
                    <p className="mt-0.5 text-xs text-slate-500">
                        Customers who sign up through this link are assigned to you, and you earn commission on what they pay.
                    </p>
                    <div className="mt-2 break-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-xs text-slate-800">{referral.url}</div>
                </div>
                <div className="flex shrink-0 items-center gap-3">
                    <div className="text-right">
                        <div className="text-lg font-bold text-slate-900">{referral.customers_count ?? 0}</div>
                        <div className="text-[11px] text-slate-500">customers referred</div>
                    </div>
                    <button
                        type="button"
                        onClick={copy}
                        className="rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-teal-500 active:scale-95"
                    >
                        {copied ? 'Copied' : 'Copy link'}
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ─── Tiny UI Helpers ──────────────────────────────────────────────── */

function StatusDot({ color = 'slate' }) {
    const map = {
        green:  'bg-emerald-500 ring-4 ring-emerald-50',
        amber:  'bg-amber-500 ring-4 ring-amber-50',
        rose:   'bg-rose-500 ring-4 ring-rose-50',
        slate:  'bg-slate-400 ring-4 ring-slate-50',
        sky:    'bg-sky-500 ring-4 ring-sky-50',
        violet: 'bg-violet-500 ring-4 ring-violet-50',
    };
    return (
        <span className={`inline-block h-2.5 w-2.5 rounded-full shrink-0 ${map[color] ?? map.slate}`} />
    );
}

function Badge({ children, color = 'slate' }) {
    const map = {
        green:  'bg-emerald-50 text-emerald-700 border-emerald-200/80',
        amber:  'bg-amber-50 text-amber-700 border-amber-200/80',
        rose:   'bg-rose-50 text-rose-700 border-rose-200/80',
        slate:  'bg-slate-100 text-slate-600 border-slate-200/80',
        sky:    'bg-sky-50 text-sky-700 border-sky-200/80',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
        violet: 'bg-violet-50 text-violet-700 border-violet-200/80',
    };
    return (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold tracking-wide ${map[color] ?? map.slate}`}>
            {children}
        </span>
    );
}

/* ─── SVG Watermark Icons for Stat Tiles ──────────────────────────── */

const TrendUpIsometricIcon = () => (
    <svg className="w-14 h-14 sm:w-16 sm:h-16 text-slate-300/60 group-hover:text-violet-400/40 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path fillRule="evenodd" d="M15.22 6.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 11-1.06-1.06L17.94 12l-2.72-2.72a.75.75 0 010-1.06z" clipRule="evenodd" />
        <path fillRule="evenodd" d="M2.25 12a.75.75 0 01.75-.75h15a.75.75 0 010 1.5H3a.75.75 0 01-.75-.75z" clipRule="evenodd" />
        <path d="M12 2.25a.75.75 0 01.75.75v18a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75z" opacity="0.3" />
        <path d="M3.75 19.5l6-6 4 4 6.75-6.75" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
);

const CalendarCheckIsometricIcon = () => (
    <svg className="w-14 h-14 sm:w-16 sm:h-16 text-slate-300/60 group-hover:text-teal-400/40 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12.75 12.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM7.5 15.75a.75.75 0 100-1.5.75.75 0 000 1.5zM8.25 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM9.75 15.75a.75.75 0 100-1.5.75.75 0 000 1.5zM10.5 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12 15.75a.75.75 0 100-1.5.75.75 0 000 1.5zM12.75 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM14.25 15.75a.75.75 0 100-1.5.75.75 0 000 1.5zM15 17.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM16.5 15.75a.75.75 0 100-1.5.75.75 0 000 1.5zM15 12.75a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM16.5 13.5a.75.75 0 100-1.5.75.75 0 000 1.5z" />
        <path fillRule="evenodd" d="M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75a3 3 0 013 3v11.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V7.5a3 3 0 013-3H6V3a.75.75 0 01.75-.75zm13.5 9a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5v7.5a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5v-7.5z" clipRule="evenodd" />
    </svg>
);

/* ─── Stat Tile Component (WHMCS Reference Style, Consistent Single-Line Metrics) ── */

function StatTile({ href, label, currency = 'USD', amount = '0.00', accentColor, hoverBorder, icon, valueColor = 'text-[#0e5077]' }) {
    return (
        <a
            href={href}
            data-native="true"
            className={`group relative flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200/90 bg-white p-3 sm:p-3.5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md ${hoverBorder} ${accentColor}`}
        >
            {/* Background Watermark Icon (positioned absolutely so text has 100% card width) */}
            <div className="pointer-events-none select-none absolute -right-2 -bottom-1 sm:right-0 sm:bottom-0 opacity-15 group-hover:opacity-25 transition-all duration-300">
                {icon}
            </div>

            {/* Foreground Content */}
            <div className="relative z-10 w-full min-w-0">
                <div className="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-slate-500 leading-tight">
                    {label}
                </div>
                <div className="mt-2 flex items-baseline gap-1 sm:gap-1.5 whitespace-nowrap leading-none">
                    <span className="text-[11px] sm:text-xs font-bold text-slate-400 shrink-0">
                        {currency}
                    </span>
                    <span className={`text-base sm:text-lg xl:text-lg 2xl:text-xl font-black tabular-nums tracking-tight ${valueColor}`}>
                        {amount}
                    </span>
                </div>
            </div>
        </a>
    );
}

/* ─── Payable Status Alert Banner ──────────────────────────────────── */

export default function Index({
    rep = {},
    referral = null,
    statement = null,
    earned_this_month = 0,
    paid_this_month = 0,
    currency = 'USD',
    routes = {},
}) {
    return (
        <>
            <Head title="Sales Rep Dashboard" />

            <div className="w-full space-y-4 sm:space-y-6 pb-6">

                {/* ── Greeting & Actions Banner ── */}
                <div className="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-r from-slate-900 via-slate-800 to-teal-950 p-5 sm:p-6 text-white shadow-sm">
                    {/* Decorative subtle background blur lights */}
                    <div className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-teal-500/10 blur-2xl" />
                    <div className="pointer-events-none absolute right-20 -bottom-10 h-40 w-40 rounded-full bg-cyan-500/10 blur-2xl" />

                    <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-teal-300/90">
                                <span>Sales Representative</span>
                                <span>•</span>
                                <span className="text-slate-300">Dashboard Overview</span>
                            </div>
                            <h1 className="mt-1 text-xl sm:text-2xl font-bold tracking-tight text-white">
                                Welcome, {rep?.name || 'Representative'}
                            </h1>
                            <p className="mt-0.5 text-xs sm:text-sm text-slate-300/80">
                                Track your commission earnings, payouts, payable balance, and financial summaries.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2 shrink-0">
                            <a
                                href={routes?.earnings || '/sales/earnings'}
                                data-native="true"
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-teal-500 active:scale-95"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Commissions</span>
                            </a>
                            <a
                                href={routes?.payouts || '/sales/payouts'}
                                data-native="true"
                                className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-semibold text-white backdrop-blur transition hover:bg-white/20 active:scale-95"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <span>Payouts</span>
                            </a>
                            <a
                                href={routes?.projects || '/sales/projects'}
                                data-native="true"
                                className="hidden sm:inline-flex items-center justify-center gap-1.5 rounded-xl border border-white/20 bg-white/5 px-3.5 py-2.5 text-xs font-semibold text-slate-200 hover:bg-white/15 transition"
                            >
                                <span>Projects</span>
                            </a>
                        </div>
                    </div>
                </div>

                <ReferralCard referral={referral} />

                <CommissionStatement statement={statement} audience="rep" showLines={false} />

                <a
                    href={routes?.earnings || '/sales/earnings'}
                    data-native="true"
                    className="-mt-1 inline-flex text-xs font-semibold text-teal-700 hover:text-teal-600"
                >
                    See the breakdown by project and service →
                </a>

                <div className="grid grid-cols-2 gap-2.5 sm:gap-4">
                    {/* 4. THIS MONTH EARNED */}
                    <StatTile
                        href={routes?.earnings || '/sales/earnings'}
                        label="This Month Earned"
                        currency={currency}
                        amount={Number(earned_this_month || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        accentColor="border-b-[3.5px] border-b-[#8b5cf6]"
                        hoverBorder="hover:border-violet-300"
                        icon={<TrendUpIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 5. THIS MONTH PAID */}
                    <StatTile
                        href={routes?.payouts || '/sales/payouts'}
                        label="This Month Paid"
                        currency={currency}
                        amount={Number(paid_this_month || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        accentColor="border-b-[3.5px] border-b-[#0d9488]"
                        hoverBorder="hover:border-teal-300"
                        icon={<CalendarCheckIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />
                </div>

            </div>
        </>
    );
}

Index.title = 'Sales Rep Dashboard';
