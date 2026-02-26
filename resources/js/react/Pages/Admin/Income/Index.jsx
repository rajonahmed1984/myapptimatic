import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Income list',
    search = '',
    routes = {},
    incomes = [],
    pagination_links = [],
}) {
    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form id="incomeSearchForm" method="GET" action={routes?.index} className="flex items-center gap-3" data-native="true">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search income..."
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
                        Add Income
                    </a>
                </div>
            </div>

            <div id="incomeTable">
                <div className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80 px-3 py-3">
                            <table className="min-w-full text-left text-sm text-slate-700">
                                <thead>
                                    <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                        <th className="px-3 py-2 whitespace-nowrap">ID</th>
                                        <th className="px-3 py-2 whitespace-nowrap">Date</th>
                                        <th className="px-3 py-2">Title & Ref</th>
                                        <th className="px-3 py-2">Category</th>
                                        <th className="px-3 py-2">Source</th>
                                        <th className="px-3 py-2">Customer</th>
                                        <th className="px-3 py-2">Project</th>
                                        <th className="px-3 py-2">Amount</th>
                                        <th className="px-3 py-2">Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {incomes.length > 0 ? (
                                        incomes.map((income) => (
                                            <tr key={income.key || `${income.title}-${income.income_date_display}-${income.amount_display}`} className="border-t border-slate-100">
                                                <td className="px-3 py-2 whitespace-nowrap font-semibold text-slate-700">
                                                    {income.id_display}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">{income.income_date_display}</td>
                                                <td className="px-3 py-2">
                                                    <div className="font-semibold text-slate-900">{income.title}</div>
                                                    {income.invoice_number && income.source_label === 'System' ? (
                                                        <div className="text-xs font-semibold text-teal-600">
                                                            Invoice #{income.invoice_number}
                                                        </div>
                                                    ) : null}
                                                    {income.notes ? (
                                                        <div className="text-xs text-slate-500">{income.notes}</div>
                                                    ) : null}
                                                </td>
                                                <td className="px-3 py-2">{income.category_name}</td>
                                                <td className="px-3 py-2">
                                                    <span className="rounded-full border border-slate-300 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                                        {income.source_label}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">{income.customer_name}</td>
                                                <td className="px-3 py-2">{income.project_name}</td>
                                                <td className="px-3 py-2 font-semibold text-slate-900">{income.amount_display}</td>
                                                <td className="px-3 py-2">
                                                    {income.attachment_url ? (
                                                        <a href={income.attachment_url} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">
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
                                            <td colSpan={9} className="px-3 py-4 text-center text-slate-500">
                                                No income found.
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
