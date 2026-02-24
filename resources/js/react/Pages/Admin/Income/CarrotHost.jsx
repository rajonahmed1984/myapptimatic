import React, { useEffect, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function CarrotHost({
    pageTitle = 'CarrotHost Income',
    sectionLabel = 'Income Sync',
    title = 'CarrotHost',
    month_label = '',
    start_date = '',
    end_date = '',
    month = '',
    prev_month = null,
    prev_month_label = null,
    next_month = null,
    next_month_label = null,
    last_refreshed_display = '',
    amount_in_subtotal_display = '0.00',
    fees_subtotal_display = '0.00',
    whmcs_errors = [],
    transactions = [],
    routes = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [isSyncing, setIsSyncing] = useState(false);
    const timerRef = useRef(null);

    const syncNow = async () => {
        if (isSyncing || !routes?.sync || !csrfToken) {
            return;
        }

        setIsSyncing(true);
        const formData = new FormData();
        formData.set('_token', csrfToken);
        formData.set('month', month);

        try {
            const response = await fetch(routes.sync, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            if (response.ok) {
                window.location.reload();
                return;
            }
        } catch (_) {
            // Silent fail to preserve prior behavior.
        }

        setIsSyncing(false);
    };

    useEffect(() => {
        if (!window.fetch) {
            return undefined;
        }

        timerRef.current = setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            syncNow();
        }, 5 * 60 * 1000);

        return () => {
            if (timerRef.current) {
                clearInterval(timerRef.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [month, routes?.sync, csrfToken, isSyncing]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">{sectionLabel}</div>
                    <div className="text-2xl font-semibold text-slate-900">{title}</div>
                    <div className="mt-1 text-sm text-slate-500">
                        Transactions for {month_label} ({start_date} to {end_date}).
                    </div>
                </div>
                <div className="text-right text-xs text-slate-500">
                    <div>Last refreshed: {last_refreshed_display}</div>
                    <div className="mt-2 flex flex-wrap items-center justify-end gap-2 text-xs">
                        <form method="POST" action={routes?.sync} data-native="true" className="inline">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <input type="hidden" name="month" value={month} />
                            <button
                                type="button"
                                onClick={syncNow}
                                disabled={isSyncing}
                                className={`rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-100 ${
                                    isSyncing ? 'cursor-wait opacity-70' : ''
                                }`}
                            >
                                {isSyncing ? 'Syncing...' : 'Sync Data'}
                            </button>
                        </form>
                        {prev_month ? (
                            <a
                                href={`${routes?.index}?month=${prev_month}`}
                                data-native="true"
                                className="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-emerald-200 hover:text-emerald-700"
                            >
                                {`<- ${prev_month_label}`}
                            </a>
                        ) : (
                            <span className="rounded-full border border-slate-100 px-3 py-1 text-slate-300">{`<- ${month_label}`}</span>
                        )}
                        {next_month ? (
                            <a
                                href={`${routes?.index}?month=${next_month}`}
                                data-native="true"
                                className="rounded-full border border-slate-200 px-3 py-1 text-slate-600 hover:border-emerald-200 hover:text-emerald-700"
                            >
                                {`${next_month_label} ->`}
                            </a>
                        ) : (
                            <span className="rounded-full border border-slate-100 px-3 py-1 text-slate-300">{`${month_label} ->`}</span>
                        )}
                    </div>
                </div>
            </div>

            {whmcs_errors.length > 0 ? (
                <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <div className="font-semibold text-amber-900">WHMCS warnings</div>
                    <ul className="mt-2 list-disc pl-5">
                        {whmcs_errors.map((error, index) => (
                            <li key={`${error}-${index}`}>{error}</li>
                        ))}
                    </ul>
                </div>
            ) : null}

            <div className="space-y-6">
                <div className="card p-6">
                    <div className="text-sm font-semibold text-slate-700">Transactions</div>
                    <div className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                        <div className="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-emerald-700">
                            <div className="text-xs uppercase tracking-[0.2em] text-emerald-600">Amount In subtotal</div>
                            <div className="mt-1 text-lg font-semibold text-emerald-800">{amount_in_subtotal_display}</div>
                        </div>
                        <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-rose-700">
                            <div className="text-xs uppercase tracking-[0.2em] text-rose-600">Fees subtotal</div>
                            <div className="mt-1 text-lg font-semibold text-rose-800">{fees_subtotal_display}</div>
                        </div>
                    </div>
                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full text-left text-sm text-slate-700">
                            <thead className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">ID</th>
                                    <th className="px-3 py-2">Client</th>
                                    <th className="px-3 py-2 whitespace-nowrap">Date & Time</th>
                                    <th className="px-3 py-2">Invoice</th>
                                    <th className="px-3 py-2">Transaction ID</th>
                                    <th className="px-3 py-2">Amount In</th>
                                    <th className="px-3 py-2">Fees</th>
                                    <th className="px-3 py-2">Gateway</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.length > 0 ? (
                                    transactions.map((row, index) => (
                                        <tr key={`${row.transaction_id}-${index}`} className="border-t border-slate-100">
                                            <td className="px-3 py-2">{row.user_id}</td>
                                            <td className="px-3 py-2">{row.client_name}</td>
                                            <td className="px-3 py-2 whitespace-nowrap">{row.date}</td>
                                            <td className="px-3 py-2">{row.invoice_id}</td>
                                            <td className="px-3 py-2">{row.transaction_id}</td>
                                            <td className="px-3 py-2">{row.amount_in}</td>
                                            <td className="px-3 py-2">{row.fees}</td>
                                            <td className="px-3 py-2">{row.gateway}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="px-3 py-4 text-center text-slate-500">
                                            No transactions found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
