import React from 'react';
import { Head } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({
    pageTitle = 'Expenses',
    search = '',
    routes = {},
    expenses = [],
    pagination_links = [],
}) {
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form
                        id="expensesSearchForm"
                        method="GET"
                        action={routes?.index}
                        className="flex items-center gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitSearch();
                        }}
                    >
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search expenses..."
                                className="ui-input"
                            />
                        </div>
                    </form>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <a
                        href={routes?.recurring}
                        data-native="true"
                        className="ui-btn-secondary"
                    >
                        Recurring
                    </a>
                    <a
                        href={routes?.categories}
                        data-native="true"
                        className="ui-btn-secondary"
                    >
                        Categories
                    </a>
                    <a
                        href={routes?.create}
                        data-native="true"
                        className="ui-btn-primary"
                    >
                        Add Expense
                    </a>
                </div>
            </div>

            <div id="expensesTable">
                <div className="overflow-hidden">
                    <div className="mt-4">
                        <DataTable
                            rows={expenses}
                            rowKey={(expense) => expense.key || `${expense.invoice_no}-${expense.expense_date_display}-${expense.title}`}
                            emptyMessage="No expenses found."
                            columns={[
                                { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-700', render: (expense) => expense.id_display },
                                { key: 'date', header: 'Date', render: (expense) => expense.expense_date_display },
                                {
                                    key: 'title',
                                    header: 'Title & Ref',
                                    render: (expense) => (
                                        <>
                                            <div className="font-semibold text-slate-900">{expense.title}</div>
                                            {expense.invoice_number ? <div className="text-xs font-semibold text-teal-600">Invoice #{expense.invoice_number}</div> : null}
                                            {expense.notes ? <div className="text-xs text-slate-500">{expense.notes}</div> : null}
                                        </>
                                    ),
                                },
                                { key: 'category', header: 'Category', render: (expense) => expense.category_name },
                                { key: 'amount', header: 'Amount', cellClassName: 'font-semibold text-slate-900', render: (expense) => expense.amount_display },
                                {
                                    key: 'attachment',
                                    header: 'Attachment',
                                    render: (expense) => (
                                        expense.attachment_url ? (
                                            <a href={expense.attachment_url} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
                                        ) : <span className="text-xs text-slate-400">--</span>
                                    ),
                                },
                            ]}
                            renderMobileCard={(expense) => (
                                <MobileCard
                                    title={expense.title}
                                    subtitle={expense.category_name}
                                    metrics={[
                                        { label: 'Amount', value: expense.amount_display },
                                        { label: 'Date', value: expense.expense_date_display },
                                    ]}
                                    actions={
                                        expense.attachment_url ? (
                                            <a
                                                href={expense.attachment_url}
                                                data-native="true"
                                                className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                            >
                                                View Attachment
                                            </a>
                                        ) : null
                                    }
                                >
                                    {expense.invoice_number ? <div className="text-xs font-semibold text-teal-600">Invoice #{expense.invoice_number}</div> : null}
                                    {expense.notes ? <div className="text-xs text-slate-500">{expense.notes}</div> : null}
                                </MobileCard>
                            )}
                        />

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
