import React from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../../Components/SearchableSelect';
import Pagination from '../../../../Components/Table/Pagination';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

const statusBadgeClass = (status) => {
    if (status === 'completed') {
        return 'bg-emerald-100 text-emerald-700';
    }

    return 'bg-amber-100 text-amber-700';
};

export default function Index({
    pageTitle = 'Affiliate Payouts',
    filters = {},
    status_options = [],
    routes = {},
    payouts = [],
    pagination = {},
}) {
    const statusOptions = status_options.map((option) => ({ value: String(option.value || ''), label: option.label }));

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <span className="text-xs font-semibold text-slate-500">
                        Showing {pagination?.from ?? 1} – {pagination?.to ?? payouts.length} of {pagination?.total ?? payouts.length} payouts
                    </span>

                    <div className="flex flex-wrap items-center gap-3">
                        <form method="GET" action={routes?.index} data-native="true" className="flex items-center gap-2">
                            <div className="w-44">
                                <SearchableSelect
                                    name="status"
                                    defaultValue={String(filters?.status ?? '')}
                                    options={statusOptions}
                                    placeholder="All statuses"
                                />
                            </div>
                            <button
                                type="submit"
                                className="ui-btn-secondary"
                            >
                                Filter
                            </button>
                            {filters?.status ? (
                                <a
                                    href={routes?.index}
                                    data-native="true"
                                    className="ui-btn-secondary"
                                >
                                    Clear
                                </a>
                            ) : null}
                        </form>
                        <a
                            href={routes?.create}
                            data-native="true"
                            className="ui-btn-primary"
                        >
                            Create payout
                        </a>
                    </div>
                </div>

                <DataTable
                    framed={false}
                    rows={payouts}
                    emptyMessage="No payouts found."
                            columns={[
                                { key: 'number', header: 'Payout #', cellClassName: 'font-semibold text-slate-900', render: (payout) => payout.payout_number },
                                { key: 'affiliate', header: 'Affiliate', render: (payout) => payout.affiliate_name },
                                { key: 'amount', header: 'Amount', render: (payout) => payout.amount_display },
                                {
                                    key: 'status',
                                    header: 'Status',
                                    render: (payout) => <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(payout.status)}`}>{payout.status_label}</span>,
                                },
                                { key: 'created', header: 'Created', cellClassName: 'text-slate-600', render: (payout) => payout.created_at_display },
                                { key: 'completed', header: 'Completed', cellClassName: 'text-slate-600', render: (payout) => payout.completed_at_display },
                                {
                                    key: 'action',
                                    header: 'Action',
                                    render: (payout) => <a href={payout?.routes?.show} data-native="true" className="font-semibold text-teal-600 hover:text-teal-500">View</a>,
                                },
                            ]}
                            renderMobileCard={(payout) => (
                                <MobileCard
                                    title={payout.payout_number}
                                    subtitle={payout.affiliate_name}
                                    badge={payout.status_label}
                                    badgeColor={statusBadgeClass(payout.status)}
                                    metrics={[
                                        { label: 'Amount', value: payout.amount_display },
                                        { label: 'Created', value: payout.created_at_display },
                                    ]}
                                    actions={
                                        <a
                                            href={payout?.routes?.show}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                        >
                                            View
                                        </a>
                                    }
                                >
                                    {payout.completed_at_display ? <div className="text-xs text-slate-500">Completed: {payout.completed_at_display}</div> : null}
                                </MobileCard>
                            )}
                        />

                <Pagination
                    pagination={pagination}
                    label="payouts"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
