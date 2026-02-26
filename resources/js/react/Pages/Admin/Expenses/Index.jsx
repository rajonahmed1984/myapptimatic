import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Expenses',
    search = '',
    routes = {},
    expenses = [],
    pagination_links = [],
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form id="expensesSearchForm" method="GET" action={routes?.index} className="flex items-center gap-3" data-native="true">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search expenses..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                onInput={(event) => {
                                    const input = event.currentTarget;
                                    clearTimeout(input.__searchTimer);
                                    input.__searchTimer = setTimeout(() => input.form?.requestSubmit(), 300);
                                }}
                            />
                        </div>
                    </form>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <a
                        href={routes?.recurring}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Recurring
                    </a>
                    <a
                        href={routes?.categories}
                        data-native="true"
                        className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Categories
                    </a>
                    <a
                        href={routes?.create}
                        data-native="true"
                        className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    >
                        Add Expense
                    </a>
                </div>
            </div>

            <div id="expensesTable">
                <div className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80 px-3 py-3">
                            <table className="min-w-full whitespace-nowrap text-left text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="px-3 py-2 whitespace-nowrap">ID</th>
                                        <th className="px-3 py-2 whitespace-nowrap">Date</th>
                                        <th className="px-3 py-2">Title & Ref</th>
                                        <th className="px-3 py-2">Category</th>
                                        <th className="px-3 py-2">Person</th>
                                        <th className="px-3 py-2">Amount</th>
                                        <th className="px-3 py-2">Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {expenses.length > 0 ? (
                                        expenses.map((expense) => (
                                            <tr key={expense.key || `${expense.invoice_no}-${expense.expense_date_display}-${expense.title}`} className="border-t border-slate-100">
                                                <td className="px-3 py-2 whitespace-nowrap font-semibold text-slate-700">
                                                    {expense.id_display}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">{expense.expense_date_display}</td>
                                                <td className="px-3 py-2">
                                                    <div className="font-semibold text-slate-900">{expense.title}</div>
                                                    {expense.invoice_number ? (
                                                        <div className="text-xs font-semibold text-teal-600">
                                                            Invoice #{expense.invoice_number}
                                                        </div>
                                                    ) : null}
                                                    {expense.notes ? (
                                                        <div className="text-xs text-slate-500">{expense.notes}</div>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-2">{expense.category_name}</td>
                                                <td className="px-3 py-2">
                                                    <div className="text-sm text-slate-700">{expense.person_name}</div>
                                                </td>
                                                <td className="px-3 py-2 font-semibold text-slate-900">{expense.amount_display}</td>
                                                <td className="px-3 py-2">
                                                    {expense.attachment_url ? (
                                                        <a href={expense.attachment_url} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
                                                            View
                                                        </a>
                                                    ) : (
                                                        <span className="text-xs text-slate-400">--</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-4 text-center text-slate-500">
                                                No expenses found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            {pagination_links.map((link, index) =>
                                link.url ? (
                                    <a
                                        key={`${index}-${link.label}`}
                                        href={link.url}
                                        data-native="true"
                                        className={`rounded-full border px-3 py-1 ${
                                            link.active
                                                ? 'border-slate-900 bg-slate-900 text-white'
                                                : 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600'
                                        }`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span
                                        key={`${index}-${link.label}`}
                                        className="rounded-full border border-slate-200 px-3 py-1 text-slate-300"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
