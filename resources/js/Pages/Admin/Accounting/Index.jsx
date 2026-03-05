import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Ledger',
    scope = 'ledger',
    search = '',
    searchAction = '',
    routes = {},
    summary = {},
    entries = [],
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const currencySummary = summary?.currencies || [];
    const typeSummary = summary?.types || [];
    const isTransactions = scope === 'transactions';

    const confirmDelete = (label) => window.confirm(`Delete entry ${label}?`);

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-5">
                <div className="card p-4 md:p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 p-1 text-sm">
                            <a
                                href={routes?.ledger}
                                data-native="true"
                                className={`rounded-full px-4 py-1.5 font-semibold ${scope === 'ledger' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-teal-600'}`}
                            >
                                Ledger
                            </a>
                            <a
                                href={routes?.transactions}
                                data-native="true"
                                className={`rounded-full px-4 py-1.5 font-semibold ${scope === 'transactions' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-teal-600'}`}
                            >
                                Transactions
                            </a>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <a
                                href={routes?.create?.payment}
                                data-native="true"
                                className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
                            >
                                New Payment
                            </a>
                            <a
                                href={routes?.create?.refund}
                                data-native="true"
                                className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                            >
                                New Refund
                            </a>
                            {!isTransactions ? (
                                <>
                                    <a
                                        href={routes?.create?.credit}
                                        data-native="true"
                                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                    >
                                        New Credit
                                    </a>
                                    <a
                                        href={routes?.create?.expense}
                                        data-native="true"
                                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                                    >
                                        New Expense
                                    </a>
                                </>
                            ) : null}
                        </div>
                    </div>

                    <p className="mt-3 text-xs text-slate-500">
                        {isTransactions
                            ? 'Transactions only include payment and refund flow.'
                            : 'Ledger includes all accounting entries: payment, refund, credit, and expense.'}
                    </p>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-3">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">Total entries</p>
                            <p className="mt-1 text-2xl font-semibold text-slate-900">{summary?.total_entries || 0}</p>
                        </div>
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-emerald-700">Inflows</p>
                            <p className="mt-1 text-2xl font-semibold text-emerald-700">{summary?.inflow_entries || 0}</p>
                        </div>
                        <div className="rounded-xl border border-rose-200 bg-rose-50/50 p-3">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-rose-700">Outflows</p>
                            <p className="mt-1 text-2xl font-semibold text-rose-700">{summary?.outflow_entries || 0}</p>
                        </div>
                        <div className="rounded-xl border border-slate-200 bg-white p-3">
                            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">Latest entry</p>
                            <p className="mt-1 whitespace-nowrap text-lg font-semibold tabular-nums text-slate-900">
                                {summary?.latest_entry_date_display || '--'}
                            </p>
                        </div>
                    </div>

                    {currencySummary.length > 0 ? (
                        <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {currencySummary.map((currency) => (
                                <div key={currency.currency} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">{currency.currency} summary</p>
                                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                        <span className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-700">
                                            In {currency.inflow_display}
                                        </span>
                                        <span className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 font-semibold text-rose-700">
                                            Out {currency.outflow_display}
                                        </span>
                                        <span
                                            className={`rounded-full px-2 py-0.5 font-semibold ${
                                                currency.net_is_negative
                                                    ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                                    : 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                            }`}
                                        >
                                            Net {currency.net_is_negative ? '-' : '+'}
                                            {currency.net_display}
                                        </span>
                                        <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-slate-600">
                                            {currency.entries_count} entries
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : null}

                    <div className="mt-4">
                        <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">
                            {isTransactions ? 'Transaction breakdown' : 'Type breakdown'}
                        </p>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            {typeSummary.map((type) => (
                                <div key={type.type} className="rounded-xl border border-slate-200 bg-white p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="font-semibold text-slate-900">{type.label}</p>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${type.is_outflow ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                            {type.count}
                                        </span>
                                    </div>
                                    {type.totals?.length ? (
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {type.totals.map((value) => (
                                                <span key={`${type.type}-${value}`} className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600">
                                                    {value}
                                                </span>
                                            ))}
                                        </div>
                                    ) : (
                                        <p className="mt-2 text-xs text-slate-400">No entries</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="card p-4 md:p-5">
                    <form method="GET" action={searchAction} className="flex flex-wrap items-center gap-3" data-native="true">
                        <div className="relative w-full max-w-md">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search by reference, invoice, gateway, customer..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                onInput={(event) => {
                                    const input = event.currentTarget;
                                    clearTimeout(input.__searchTimer);
                                    input.__searchTimer = setTimeout(() => input.form?.requestSubmit(), 300);
                                }}
                            />
                        </div>
                    </form>

                    <div id="accountingTableWrap" className="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table className="w-full min-w-[1220px] text-left text-sm">
                            <thead className="border-b border-slate-300 bg-slate-50 text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="sticky left-0 z-20 whitespace-nowrap border-r border-slate-200 bg-slate-50 px-4 py-3">Entry ID</th>
                                    <th className="whitespace-nowrap px-4 py-3">Date</th>
                                    <th className="px-4 py-3">Entry</th>
                                    <th className="px-4 py-3">Customer / Invoice</th>
                                    <th className="px-4 py-3">Gateway / Ref</th>
                                    <th className="px-4 py-3 text-right">Amount</th>
                                    <th className="px-4 py-3 text-right">Running balance</th>
                                    <th className="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length > 0 ? (
                                entries.map((entry) => (
                                        <tr key={entry.id} className="border-b border-slate-100 align-top">
                                            <td className="sticky left-0 z-10 whitespace-nowrap border-r border-slate-100 bg-white px-4 py-3 font-semibold tabular-nums text-slate-700">
                                                Entry #{entry.id}
                                            </td>
                                            <td className="whitespace-nowrap px-4 py-3 tabular-nums text-slate-600">{entry.entry_date_display}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${entry.is_outflow ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}`}>
                                                        {entry.type_label}
                                                    </span>
                                                </div>
                                                <div className="mt-1 max-w-[280px] truncate text-xs text-slate-500" title={entry.description}>
                                                    {entry.description}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <div className="max-w-[240px] truncate" title={entry.customer_name}>
                                                    {entry?.routes?.customer_show ? (
                                                        <a
                                                            href={entry.routes.customer_show}
                                                            data-native="true"
                                                            className="font-medium text-teal-600 hover:text-teal-500"
                                                        >
                                                            {entry.customer_name}
                                                        </a>
                                                    ) : (
                                                        <span>{entry.customer_name}</span>
                                                    )}
                                                </div>
                                                <div className="mt-1 text-xs">
                                                    {entry?.routes?.invoice_show ? (
                                                        <a href={entry.routes.invoice_show} data-native="true" className="text-slate-500 hover:text-teal-600">
                                                            Invoice {entry.invoice_label}
                                                        </a>
                                                    ) : (
                                                        <span className="text-slate-400">Invoice -</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <div className="max-w-[220px] truncate" title={entry.gateway_name}>
                                                    {entry.gateway_name}
                                                </div>
                                                <div className="mt-1 max-w-[220px] truncate text-xs text-slate-500" title={entry.reference}>
                                                    Ref: {entry.reference}
                                                </div>
                                            </td>
                                            <td className={`whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums ${entry.is_outflow ? 'text-rose-600' : 'text-emerald-600'}`}>
                                                {entry.amount_display}
                                            </td>
                                            <td className={`whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums ${entry.running_balance_is_negative ? 'text-rose-600' : 'text-slate-700'}`}>
                                                {entry.running_balance_is_negative ? '-' : ''}
                                                {entry.running_balance_display}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-3">
                                                    <a
                                                        href={entry?.routes?.edit}
                                                        data-native="true"
                                                        className="text-teal-600 hover:text-teal-500"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form
                                                        method="POST"
                                                        action={entry?.routes?.destroy}
                                                        data-native="true"
                                                        onSubmit={(event) => {
                                                            if (!confirmDelete(entry.reference || entry.id)) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <input type="hidden" name="scope" value={scope} />
                                                        <input type="hidden" name="search" value={search} />
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
                                        <td colSpan={8} className="px-4 py-8 text-center text-slate-500">
                                            No accounting entries found.
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
