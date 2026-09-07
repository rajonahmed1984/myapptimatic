import React from 'react';
import { Head } from '@inertiajs/react';

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

const CashIsometricIcon = () => (
    <svg className="w-14 h-14 sm:w-16 sm:h-16 text-slate-300/60 group-hover:text-emerald-400/40 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 7.5a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
        <path fillRule="evenodd" d="M1.5 4.875C1.5 3.839 2.34 3 3.375 3h17.25c1.035 0 1.875.84 1.875 1.875v9.75c0 1.036-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 011.5 14.625v-9.75zM8.25 9.75a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0zM18.75 9a.75.75 0 00-.75.75v.008c0 .414.336.75.75.75h.008a.75.75 0 00.75-.75V9.75a.75.75 0 00-.75-.75h-.008zM4.5 9.75A.75.75 0 015.25 9h.008a.75.75 0 01.75.75v.008a.75.75 0 01-.75.75H5.25a.75.75 0 01-.75-.75V9.75z" clipRule="evenodd" />
        <path d="M2.25 18a.75.75 0 000 1.5c5.4 0 10.63.722 15.6 2.075 1.19.324 2.4-.558 2.4-1.82V18.75a.75.75 0 00-.75-.75H2.25z" />
    </svg>
);

const CreditCardIsometricIcon = () => (
    <svg className="w-14 h-14 sm:w-16 sm:h-16 text-slate-300/60 group-hover:text-indigo-400/40 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path fillRule="evenodd" d="M2.25 6A2.25 2.25 0 014.5 3.75h15A2.25 2.25 0 0121.75 6v12A2.25 2.25 0 0119.5 20.25h-15A2.25 2.25 0 012.25 18V6zm2.25-.75A.75.75 0 003.75 6v1.5h16.5V6a.75.75 0 00-.75-.75h-15zm16.5 4.5H3.75V18c0 .414.336.75.75.75h15a.75.75 0 00.75-.75V9.75zm-14.25 4.5a.75.75 0 01.75-.75h2.25a.75.75 0 010 1.5H7.5a.75.75 0 01-.75-.75zm4.5 0a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75z" clipRule="evenodd" />
    </svg>
);

const ScaleIsometricIcon = () => (
    <svg className="w-14 h-14 sm:w-16 sm:h-16 text-slate-300/60 group-hover:text-sky-400/40 transition-colors" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2.25a.75.75 0 01.75.75v1.008l4.985 1.662a.75.75 0 01.515.71v.75l2.485.828a.75.75 0 01.515.71v5.082a4.5 4.5 0 01-2.25 3.897v3.603a.75.75 0 01-.75.75H5.75a.75.75 0 01-.75-.75v-3.603a4.5 4.5 0 01-2.25-3.897V7.908a.75.75 0 01.515-.71L5.75 6.37V5.62a.75.75 0 01.515-.71L11.25 3.25V3a.75.75 0 01.75-.75zm6.75 7.184l-1.5-.5V12.75a3 3 0 001.5-2.616v-.7zM5.25 9.434l-1.5.5v.7A3 3 0 005.25 13.25V9.434zM11.25 6.008l-3.5 1.167v6.075a.75.75 0 01-1.5 0V6.675l-2-0.667v.342a4.48 4.48 0 011.5 3.4v3.1a4.502 4.502 0 012.25 3.897V20.25h8v-3.403a4.502 4.502 0 012.25-3.897v-3.1a4.48 4.48 0 011.5-3.4v-.342l-2 .667v7.075a.75.75 0 01-1.5 0V7.175l-3.5-1.167V19.5h-1.5V6.008z" />
    </svg>
);

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

function PayableStatusBanner({ payableBalance, currency = 'USD', routes = {} }) {
    if (payableBalance === 0) return null;

    const isDue = payableBalance > 0;

    return (
        <div className={`rounded-2xl border p-4 sm:p-5 text-sm shadow-sm ${
            isDue ? 'border-teal-200 bg-teal-50/90 text-teal-950' : 'border-rose-200 bg-rose-50/90 text-rose-950'
        }`}>
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex items-start sm:items-center gap-3">
                    <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${
                        isDue ? 'bg-teal-100 text-teal-700' : 'bg-rose-100 text-rose-700'
                    }`}>
                        {isDue ? (
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        ) : (
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        )}
                    </div>
                    <div>
                        <div className="font-bold text-base">
                            {isDue ? 'Payable Balance Due to Representative' : 'Overpaid / Advance Balance to be Settled'}
                        </div>
                        <div className="text-xs mt-0.5 opacity-80">
                            {isDue ? (
                                <>Outstanding commission ready for payout: <span className="font-extrabold">{currency} {payableBalance.toFixed(2)}</span></>
                            ) : (
                                <>Current advance / overpayment amount: <span className="font-extrabold">{currency} {Math.abs(payableBalance).toFixed(2)}</span></>
                            )}
                            <span className="block sm:inline sm:ml-2 text-[11px] opacity-75 font-medium">
                                ({isDue ? 'Positive balance indicates earnings ready to be disbursed' : 'Advance or overpayment to be settled against future commissions'})
                            </span>
                        </div>
                    </div>
                </div>
                <div className="flex items-center gap-2 w-full sm:w-auto shrink-0">
                    <a
                        href={isDue ? routes?.payouts || '/sales/payouts' : routes?.earnings || '/sales/earnings'}
                        data-native="true"
                        className={`w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl px-5 py-2.5 text-xs font-bold text-white shadow-sm transition active:scale-95 ${
                            isDue ? 'bg-teal-600 hover:bg-teal-700' : 'bg-rose-600 hover:bg-rose-700'
                        }`}
                    >
                        <span>{isDue ? 'View Payouts' : 'View Commissions'}</span>
                        <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    );
}

/* ─── Financial Performance & Breakdown Card ──────────────────────── */

function FinancialSummaryCard({ balance = {}, earnedThisMonth = 0, paidThisMonth = 0, currency = 'USD', routes = {} }) {
    const totalEarned = Number(balance?.total_earned || 0);
    const totalPaid = Number(balance?.total_paid || 0);
    const payableBalance = Number(balance?.payable_balance ?? (totalEarned - totalPaid));
    const advancePaid = Number(balance?.advance_paid || 0);

    const paidPercentage = totalEarned > 0 ? Math.min(100, Math.round((totalPaid / totalEarned) * 100)) : 0;

    return (
        <div className="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                <div className="flex items-center gap-2.5">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </div>
                    <div>
                        <div className="text-xs font-bold uppercase tracking-wider text-slate-700">Financial Summary</div>
                        <div className="text-xs text-slate-500 mt-0.5">Commission & Payout Disbursal Progress</div>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <a
                        href={routes?.earnings || '/sales/earnings'}
                        data-native="true"
                        className="text-xs font-semibold text-teal-600 hover:text-teal-700 inline-flex items-center gap-1"
                    >
                        <span>Commissions</span>
                        <span>→</span>
                    </a>
                </div>
            </div>

            {/* Payout Progress Bar */}
            <div className="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-100">
                <div className="flex items-center justify-between text-xs font-medium text-slate-600 mb-2">
                    <span>Payout Disbursal Ratio ({paidPercentage}%)</span>
                    <span className="tabular-nums font-semibold text-slate-800">
                        {currency} {totalPaid.toFixed(2)} / {currency} {totalEarned.toFixed(2)}
                    </span>
                </div>
                <div className="h-2.5 w-full rounded-full bg-slate-200 overflow-hidden">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500"
                        style={{ width: `${paidPercentage}%` }}
                    />
                </div>
            </div>

            {/* Metric Grid */}
            <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div className="p-3 rounded-xl border border-slate-100 bg-white">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Earned</div>
                    <div className="mt-1 text-base sm:text-lg font-bold text-emerald-700 tabular-nums">
                        {currency} {totalEarned.toFixed(2)}
                    </div>
                </div>
                <div className="p-3 rounded-xl border border-slate-100 bg-white">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Paid</div>
                    <div className="mt-1 text-base sm:text-lg font-bold text-indigo-700 tabular-nums">
                        {currency} {totalPaid.toFixed(2)}
                    </div>
                </div>
                <div className="p-3 rounded-xl border border-slate-100 bg-white">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Net Payable</div>
                    <div className="mt-1 text-base sm:text-lg font-bold text-sky-700 tabular-nums">
                        {currency} {payableBalance.toFixed(2)}
                    </div>
                </div>
                <div className="p-3 rounded-xl border border-slate-100 bg-white">
                    <div className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Advance Paid</div>
                    <div className="mt-1 text-base sm:text-lg font-bold text-slate-700 tabular-nums">
                        {currency} {advancePaid.toFixed(2)}
                    </div>
                </div>
            </div>
        </div>
    );
}

/* ─── Main Sales Rep Dashboard ─────────────────────────────────────── */

export default function Index({
    rep = {},
    balance = {},
    earned_this_month = 0,
    paid_this_month = 0,
    currency = 'USD',
    routes = {},
}) {
    const totalEarned = Number(balance?.total_earned || 0);
    const totalPaid = Number(balance?.total_paid || 0);
    const payableBalance = Number(balance?.payable_balance ?? (totalEarned - totalPaid));

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

                {/* ── Payable Status Alert Banner ── */}
                <PayableStatusBanner
                    payableBalance={payableBalance}
                    currency={currency}
                    routes={routes}
                />

                {/* ── 5 Stat Tiles (Never-truncating, True Background Watermarks) ── */}
                <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-2.5 sm:gap-4">
                    {/* 1. TOTAL EARNED */}
                    <StatTile
                        href={routes?.earnings || '/sales/earnings'}
                        label="Total Earned"
                        currency={currency}
                        amount={totalEarned.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        accentColor="border-b-[3.5px] border-b-[#16a34a]"
                        hoverBorder="hover:border-emerald-300"
                        icon={<CashIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 2. TOTAL PAYOUTS */}
                    <StatTile
                        href={routes?.payouts || '/sales/payouts'}
                        label="Total Payouts"
                        currency={currency}
                        amount={totalPaid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        accentColor="border-b-[3.5px] border-b-[#4f46e5]"
                        hoverBorder="hover:border-indigo-300"
                        icon={<CreditCardIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />

                    {/* 3. PAYABLE BALANCE */}
                    <StatTile
                        href={routes?.earnings || '/sales/earnings'}
                        label="Payable Balance"
                        currency={currency}
                        amount={payableBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        accentColor="border-b-[3.5px] border-b-[#0284c7]"
                        hoverBorder="hover:border-sky-300"
                        icon={<ScaleIsometricIcon />}
                        valueColor="text-[#0e5077]"
                    />

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

                {/* ── Financial Performance & Payout Progress Card ── */}
                <FinancialSummaryCard
                    balance={balance}
                    earnedThisMonth={earned_this_month}
                    paidThisMonth={paid_this_month}
                    currency={currency}
                    routes={routes}
                />

            </div>
        </>
    );
}

Index.title = 'Sales Rep Dashboard';
