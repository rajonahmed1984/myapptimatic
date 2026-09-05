import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({
    pageTitle = 'Ledger',
    scope = 'ledger',
    search = '',
    searchAction = '',
    routes = {},
    summary = {},
    entries = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const currencySummary = summary?.currencies || [];
    const typeSummary = summary?.types || [];
    const isTransactions = scope === 'transactions';
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: searchAction,
    });

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
                        <div className="mt-3 grid gap-3 md:grid-cols-1 xl:grid-cols-1">
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
                    <form
                        method="GET"
                        action={searchAction}
                        className="flex flex-wrap items-center gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <div className="relative w-full max-w-md">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search by reference, invoice, gateway, customer..."
                                className="ui-input"
                            />
                        </div>
                    </form>

                    <div id="accountingTableWrap" className="mt-4">
                        <DataTable
                            rows={entries}
                            emptyMessage="No accounting entries found."
                            columns={[
                                { key: 'date', header: 'Date', cellClassName: 'tabular-nums text-slate-600', render: (entry) => entry.entry_date_display },
                                {
                                    key: 'customer',
                                    header: 'Customer / Invoice',
                                    cellClassName: 'text-slate-600',
                                    render: (entry) => (
                                        <>
                                            <div className="max-w-[240px] truncate" title={entry.customer_name}>
                                                {entry?.routes?.customer_show ? (
                                                    <a href={entry.routes.customer_show} data-native="true" className="font-medium text-teal-600 hover:text-teal-500">{entry.customer_name}</a>
                                                ) : <span>{entry.customer_name}</span>}
                                            </div>
                                            <div className="mt-1 text-xs">
                                                {entry?.routes?.invoice_show ? (
                                                    <a href={entry.routes.invoice_show} data-native="true" className="text-slate-500 hover:text-teal-600">Invoice {entry.invoice_label}</a>
                                                ) : <span className="text-slate-400">Invoice -</span>}
                                            </div>
                                        </>
                                    ),
                                },
                                {
                                    key: 'gateway',
                                    header: 'Gateway / Ref',
                                    cellClassName: 'text-slate-600',
                                    render: (entry) => (
                                        <>
                                            <div className="max-w-[220px] truncate" title={entry.gateway_name}>{entry.gateway_name}</div>
                                            <div className="mt-1 max-w-[220px] truncate text-xs text-slate-500" title={entry.reference}>Ref: {entry.reference}</div>
                                        </>
                                    ),
                                },
                                {
                                    key: 'amount',
                                    header: 'Amount / Balance',
                                    headerClassName: 'text-right',
                                    cellClassName: 'text-right',
                                    render: (entry) => (
                                        <>
                                            <div className={`font-semibold tabular-nums ${entry.is_outflow ? 'text-rose-600' : 'text-emerald-600'}`}>{entry.type_label}: {entry.amount_display}</div>
                                            <div className={`mt-1 text-xs font-semibold tabular-nums ${entry.running_balance_is_negative ? 'text-rose-600' : 'text-slate-700'}`}>
                                                Balance {entry.running_balance_is_negative ? '-' : ''}{entry.running_balance_display}
                                            </div>
                                        </>
                                    ),
                                },
                                {
                                    key: 'actions',
                                    header: 'Actions',
                                    headerClassName: 'text-right',
                                    cellClassName: 'text-right',
                                    render: (entry) => (
                                        <div className="flex items-center justify-end gap-3">
                                            <a href={entry?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">Edit</a>
                                            <form
                                                method="POST"
                                                action={entry?.routes?.destroy}
                                                data-native="true"
                                                onSubmit={(event) => { if (!confirmDelete(entry.reference || entry.id)) event.preventDefault(); }}
                                            >
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <input type="hidden" name="scope" value={scope} />
                                                <input type="hidden" name="search" value={search} />
                                                <button type="submit" className="text-rose-600 hover:text-rose-500">Delete</button>
                                            </form>
                                        </div>
                                    ),
                                },
                            ]}
                            renderMobileCard={(entry) => (
                                <MobileCard
                                    title={entry?.routes?.customer_show ? (
                                        <a href={entry.routes.customer_show} data-native="true" className="hover:text-teal-600">{entry.customer_name}</a>
                                    ) : entry.customer_name}
                                    subtitle={`${entry.gateway_name} · ${entry.entry_date_display}`}
                                    badge={entry.type_label}
                                    badgeColor={entry.is_outflow ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}
                                    metrics={[
                                        { label: 'Amount', value: entry.amount_display, tone: entry.is_outflow ? 'text-rose-600' : 'text-emerald-600' },
                                        { label: 'Balance', value: `${entry.running_balance_is_negative ? '-' : ''}${entry.running_balance_display}`, tone: entry.running_balance_is_negative ? 'text-rose-600' : 'text-slate-700' },
                                    ]}
                                    actions={
                                        <>
                                            <a
                                                href={entry?.routes?.edit}
                                                data-native="true"
                                                className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                            >
                                                Edit
                                            </a>
                                            <form
                                                method="POST"
                                                action={entry?.routes?.destroy}
                                                data-native="true"
                                                onSubmit={(event) => { if (!confirmDelete(entry.reference || entry.id)) event.preventDefault(); }}
                                            >
                                                <input type="hidden" name="_token" value={csrfToken} />
                                                <input type="hidden" name="_method" value="DELETE" />
                                                <input type="hidden" name="scope" value={scope} />
                                                <input type="hidden" name="search" value={search} />
                                                <button type="submit" className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                            </form>
                                        </>
                                    }
                                >
                                    {entry?.routes?.invoice_show ? (
                                        <a href={entry.routes.invoice_show} data-native="true" className="text-xs text-slate-500 hover:text-teal-600">Invoice {entry.invoice_label}</a>
                                    ) : null}
                                    <div className="text-xs text-slate-500">Ref: {entry.reference}</div>
                                </MobileCard>
                            )}
                        />
                    </div>

                    {pagination?.total > 0 ? (
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span className="text-slate-500">
                                Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total || 0}
                            </span>

                            {pagination?.has_pages ? (
                                <div className="flex items-center gap-2">
                                    {pagination.previous_url ? (
                                        <a
                                            href={pagination.previous_url}
                                            data-native="true"
                                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                        >
                                            Previous
                                        </a>
                                    ) : (
                                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Previous</span>
                                    )}

                                    <span className="whitespace-nowrap text-slate-500">
                                        Page {pagination.current_page || 1} of {pagination.last_page || 1}
                                    </span>

                                    {pagination.next_url ? (
                                        <a
                                            href={pagination.next_url}
                                            data-native="true"
                                            className="rounded-full border border-slate-300 px-3 py-1 text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                        >
                                            Next
                                        </a>
                                    ) : (
                                        <span className="rounded-full border border-slate-200 px-3 py-1 text-slate-300">Next</span>
                                    )}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
