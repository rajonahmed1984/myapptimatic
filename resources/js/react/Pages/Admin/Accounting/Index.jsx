import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Ledger',
    scope = 'ledger',
    search = '',
    searchAction = '',
    routes = {},
    entries = [],
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    const confirmDelete = (label) => window.confirm(`Delete entry ${label}?`);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form method="GET" action={searchAction} className="flex items-center gap-3" data-native="true">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search entries..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </form>
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
                </div>
            </div>

            <div id="accountingTableWrap">
                <div className="card overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="whitespace-nowrap px-4 py-3">Date</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Customer</th>
                                <th className="px-4 py-3">Invoice</th>
                                <th className="px-4 py-3">Gateway</th>
                                <th className="px-4 py-3">Amount</th>
                                <th className="px-4 py-3">Reference</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length > 0 ? (
                                entries.map((entry) => (
                                    <tr key={entry.id} className="border-b border-slate-100">
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-600">{entry.entry_date_display}</td>
                                        <td className="px-4 py-3 text-slate-700">{entry.type_label}</td>
                                        <td className="px-4 py-3 text-slate-500">
                                            {entry?.routes?.customer_show ? (
                                                <a
                                                    href={entry.routes.customer_show}
                                                    data-native="true"
                                                    className="text-teal-600 hover:text-teal-500"
                                                >
                                                    {entry.customer_name}
                                                </a>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">
                                            {entry?.routes?.invoice_show ? (
                                                <a
                                                    href={entry.routes.invoice_show}
                                                    data-native="true"
                                                    className="text-teal-600 hover:text-teal-500"
                                                >
                                                    {entry.invoice_label}
                                                </a>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{entry.gateway_name}</td>
                                        <td className={`px-4 py-3 font-semibold ${entry.is_outflow ? 'text-rose-600' : 'text-emerald-600'}`}>
                                            {entry.amount_display}
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">{entry.reference}</td>
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
                                    <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                                        No entries yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
