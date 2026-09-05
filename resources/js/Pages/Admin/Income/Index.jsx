import React from 'react';
import { Head } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({
    pageTitle = 'Income list',
    search = '',
    routes = {},
    incomes = [],
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
                        id="incomeSearchForm"
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
                                placeholder="Search income..."
                                className="ui-input"
                            />
                        </div>
                    </form>
                </div>
                <div className="flex flex-wrap items-center gap-3">
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
                        Add Income
                    </a>
                </div>
            </div>

            <div id="incomeTable">
                <div className="overflow-hidden">
                    <div className="mt-4">
                        <DataTable
                            rows={incomes}
                            rowKey={(income) => income.key || `${income.title}-${income.income_date_display}-${income.amount_display}`}
                            emptyMessage="No income found."
                            columns={[
                                { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-700', render: (income) => income.id_display },
                                { key: 'date', header: 'Date', render: (income) => income.income_date_display },
                                {
                                    key: 'title',
                                    header: 'Title & Ref',
                                    render: (income) => (
                                        <>
                                            <div className="font-semibold text-slate-900">{income.title}</div>
                                            {income.invoice_number && income.source_label === 'System' ? (
                                                <div className="text-xs font-semibold text-teal-600">Invoice #{income.invoice_number}</div>
                                            ) : null}
                                        </>
                                    ),
                                },
                                { key: 'category', header: 'Category', render: (income) => income.category_name },
                                {
                                    key: 'customer',
                                    header: 'Customer / Project',
                                    render: (income) => (
                                        <>
                                            <div className="font-medium text-slate-800">{income.customer_name}</div>
                                            <div className="text-xs text-slate-500">{income.project_name}</div>
                                        </>
                                    ),
                                },
                                { key: 'amount', header: 'Amount', cellClassName: 'font-semibold text-slate-900', render: (income) => income.amount_display },
                                {
                                    key: 'attachment',
                                    header: 'Attachment',
                                    render: (income) => (
                                        income.attachment_url ? (
                                            <a href={income.attachment_url} data-native="true" className="text-xs font-semibold text-teal-600 hover:text-teal-500">View</a>
                                        ) : <span className="text-xs text-slate-400">--</span>
                                    ),
                                },
                            ]}
                            renderMobileCard={(income) => (
                                <MobileCard
                                    title={income.title}
                                    subtitle={`${income.category_name}${income.customer_name ? ` · ${income.customer_name}` : ''}`}
                                    metrics={[
                                        { label: 'Amount', value: income.amount_display },
                                        { label: 'Date', value: income.income_date_display },
                                    ]}
                                    actions={
                                        income.attachment_url ? (
                                            <a
                                                href={income.attachment_url}
                                                data-native="true"
                                                className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                            >
                                                View Attachment
                                            </a>
                                        ) : null
                                    }
                                >
                                    {income.invoice_number && income.source_label === 'System' ? (
                                        <div className="text-xs font-semibold text-teal-600">Invoice #{income.invoice_number}</div>
                                    ) : null}
                                    {income.project_name ? <div className="text-xs text-slate-500">Project: {income.project_name}</div> : null}
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
