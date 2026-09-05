import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    }

    if (status === 'paused') {
        return 'border-amber-200 bg-amber-50 text-amber-700';
    }

    return 'border-slate-200 bg-slate-50 text-slate-600';
};

export default function Index({
    pageTitle = 'Project Maintenance',
    filters = {},
    maintenances = [],
    pagination = {},
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: filters?.search ?? '',
        url: routes?.index,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form
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
                                placeholder="Search maintenance..."
                                className="ui-input"
                            />
                        </div>
                    </form>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className="ui-btn-primary"
                >
                    Add maintenance
                </a>
            </div>

            <div className="card overflow-hidden">
                <DataTable
                    rows={maintenances}
                    emptyMessage="No maintenance plans yet."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (m) => `#${m.id}` },
                        {
                            key: 'project',
                            header: 'Project',
                            render: (m) => (
                                <>
                                    {m.project_route ? (
                                        <a href={m.project_route} data-native="true" className="font-semibold text-slate-900 hover:text-teal-600 block">{m.project_name}</a>
                                    ) : (
                                        <div className="font-semibold text-slate-900">{m.project_name || '--'}</div>
                                    )}
                                    {m.sales_reps?.length > 0 ? (
                                        <div className="mt-0.5 text-xs text-slate-500"><span className="font-medium">Sale rep: </span>{m.sales_reps.join(', ')}</div>
                                    ) : null}
                                </>
                            ),
                        },
                        {
                            key: 'customer',
                            header: 'Customer',
                            render: (m) => (
                                <>
                                    {m.customer_route ? (
                                        <a href={m.customer_route} data-native="true" className="font-medium text-slate-900 hover:text-teal-600">{m.customer_name}</a>
                                    ) : (
                                        <div className="font-medium text-slate-800">{m.customer_name || '--'}</div>
                                    )}
                                    {m.customer_company ? <div className="text-xs text-slate-500">{m.customer_company}</div> : null}
                                </>
                            ),
                        },
                        { key: 'cycle', header: 'Cycle', render: (m) => m.billing_cycle_label },
                        { key: 'next_billing', header: 'Next Billing', cellClassName: 'text-xs text-slate-600', render: (m) => m.next_billing_date },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (m) => <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(m.status)}`}>{m.status_label}</span>,
                        },
                        { key: 'amount', header: 'Amount', headerClassName: 'text-right', cellClassName: 'text-right font-semibold', render: (m) => m.amount_display },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (m) => (
                                <div className="flex items-center justify-end gap-2 text-xs font-semibold">
                                    <a href={m.routes?.show} data-native="true" className="text-slate-700 hover:text-teal-600">View</a>
                                    <a href={m.routes?.edit} data-native="true" className="text-teal-700 hover:text-teal-600">Edit</a>
                                    {m.can_resume ? (
                                        <form method="POST" action={m.routes?.update} data-native="true">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="_method" value="PATCH" />
                                            <input type="hidden" name="quick_status" value="1" />
                                            <input type="hidden" name="status" value="active" />
                                            <button type="submit" className="text-emerald-700 hover:text-emerald-600">Resume</button>
                                        </form>
                                    ) : null}
                                </div>
                            ),
                        },
                    ]}
                    renderMobileCard={(maintenance) => (
                        <MobileCard
                            title={maintenance.project_route ? (
                                <a href={maintenance.project_route} data-native="true" className="hover:text-teal-600">{maintenance.project_name}</a>
                            ) : (maintenance.project_name || '--')}
                            subtitle={maintenance.customer_name}
                            badge={maintenance.status_label}
                            badgeColor={statusBadgeClass(maintenance.status)}
                            metrics={[
                                { label: 'Amount', value: maintenance.amount_display },
                                { label: 'Next Billing', value: maintenance.next_billing_date || '--' },
                            ]}
                            actions={
                                <>
                                    <a
                                        href={maintenance.routes?.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={maintenance.routes?.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    {maintenance.can_resume ? (
                                        <form method="POST" action={maintenance.routes?.update} data-native="true" className="flex-1">
                                            <input type="hidden" name="_token" value={csrf} />
                                            <input type="hidden" name="_method" value="PATCH" />
                                            <input type="hidden" name="quick_status" value="1" />
                                            <input type="hidden" name="status" value="active" />
                                            <button type="submit" className="w-full py-2 px-3 rounded-xl border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition active:scale-95">Resume</button>
                                        </form>
                                    ) : null}
                                </>
                            }
                        >
                            {maintenance.sales_reps?.length > 0 ? (
                                <div className="text-xs text-slate-500"><span className="font-medium">Sale rep: </span>{maintenance.sales_reps.join(', ')}</div>
                            ) : null}
                        </MobileCard>
                    )}
                />
            </div>

            {pagination?.has_pages ? (
                <div className="mt-6 flex items-center justify-end gap-2 text-sm">
                    {pagination?.previous_url ? (
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

                    {pagination?.next_url ? (
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
        </>
    );
}
