import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import Pagination from '../../../Components/Table/Pagination';
import MobileCard from '../../../Components/Mobile/MobileCard';
import IncomeCreateModal from './IncomeCreateModal';

export default function Index({
    pageTitle = 'Income list',
    search = '',
    routes = {},
    incomes = [],
    pagination = {},
    categories = [],
    defaultIncomeDate = '',
}) {
    const { flash } = usePage().props || {};
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    const [isCreateOpen, setIsCreateOpen] = useState(() => {
        if (typeof window !== 'undefined') {
            return new URLSearchParams(window.location.search).get('create') === '1';
        }
        return false;
    });

    const handleCloseModal = () => {
        setIsCreateOpen(false);
        if (typeof window !== 'undefined' && window.location.search.includes('create=')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('create');
            window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
        }
    };

    return (
        <>
            <Head title={pageTitle} />

            <div id="incomeTable" className="card overflow-hidden">
                {flash?.status && (
                    <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-semibold text-emerald-800 flex items-center justify-between">
                        <span>{flash.status}</span>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                        <form
                            id="incomeSearchForm"
                            method="GET"
                            action={routes?.index}
                            className="w-full max-w-sm"
                            onSubmit={(event) => {
                                event.preventDefault();
                                submitSearch();
                            }}
                        >
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search income..."
                                className="ui-input w-full"
                            />
                        </form>
                        <span className="hidden whitespace-nowrap text-xs text-slate-500 sm:inline">
                            Showing {pagination?.from ?? (incomes.length > 0 ? 1 : 0)} – {pagination?.to ?? incomes.length} of {pagination?.total ?? incomes.length} income entries
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <a
                            href={routes?.categories}
                            data-native="true"
                            className="ui-btn-secondary"
                        >
                            Categories
                        </a>
                        <button
                            type="button"
                            onClick={() => setIsCreateOpen(true)}
                            className="ui-btn-primary flex items-center gap-1.5"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Income
                        </button>
                    </div>
                </div>

                <div>
                    <div>
                        <DataTable
                            framed={false}
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

                        <Pagination
                            pagination={pagination}
                            label="income entries"
                            className="border-t border-slate-200 px-4 py-3"
                        />
                    </div>
                </div>
            </div>

            <IncomeCreateModal
                open={isCreateOpen}
                onClose={handleCloseModal}
                categories={categories}
                storeUrl={routes?.store || '/admin/income'}
                defaultDate={defaultIncomeDate}
            />
        </>
    );
}
