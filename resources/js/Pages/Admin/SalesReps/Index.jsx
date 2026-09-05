import React from 'react';
import { Head } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

const statusBadgeClass = (status) =>
    status === 'active'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
        : 'border-slate-300 bg-slate-50 text-slate-600';

export default function Index({
    pageTitle = 'Sales Representatives',
    filters = {},
    reps = [],
    routes = {},
}) {
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
                        <div className="relative">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search sales reps..."
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
                    Add sales rep
                </a>
            </div>

            <div className="card overflow-hidden">
                <DataTable
                    rows={reps}
                    emptyMessage="No sales representatives yet."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (rep) => rep.id },
                        {
                            key: 'name',
                            header: 'Name',
                            render: (rep) => (
                                <>
                                    <div className="font-semibold text-slate-900"><a href={rep.routes?.show} data-native="true" className="hover:text-teal-600">{rep.name}</a></div>
                                    <div className="text-xs text-slate-500">{rep.email || '--'}</div>
                                    {rep.employee_name ? <div className="text-xs text-emerald-600">Employee: {rep.employee_name}</div> : null}
                                </>
                            ),
                        },
                        { key: 'services', header: 'Services', cellClassName: 'text-sm text-slate-700', render: (rep) => `${rep.active_subscriptions_count} (${rep.subscriptions_count})` },
                        {
                            key: 'projects',
                            header: 'Projects & Maintenance',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right',
                            render: (rep) => (
                                <>
                                    <div className="text-sm text-slate-700">Projects: {rep.projects_count}</div>
                                    <div className="text-xs text-slate-500">Maintenance: {rep.maintenances_count}</div>
                                </>
                            ),
                        },
                        { key: 'login', header: 'Login', cellClassName: 'text-[11px] text-slate-400', render: (rep) => `Last login: ${rep.last_login_label}` },
                        { key: 'total_earned', header: 'Total earned', headerClassName: 'text-right', cellClassName: 'text-right font-semibold', render: (rep) => rep.total_earned },
                        { key: 'payable', header: 'Payable (Net)', headerClassName: 'text-right', cellClassName: 'text-right', render: (rep) => rep.total_payable },
                        { key: 'paid', header: 'Paid (Incl. Advance)', headerClassName: 'text-right', cellClassName: 'text-right', render: (rep) => rep.total_paid },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (rep) => <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusBadgeClass(rep.status)}`}>{rep.status_label}</span>,
                        },
                    ]}
                    renderMobileCard={(rep) => (
                        <MobileCard
                            title={<a href={rep.routes?.show} data-native="true" className="hover:text-teal-600">{rep.name}</a>}
                            subtitle={rep.email || rep.employee_name}
                            badge={rep.status_label}
                            badgeColor={statusBadgeClass(rep.status)}
                            metrics={[
                                { label: 'Total Earned', value: rep.total_earned },
                                { label: 'Payable', value: rep.total_payable },
                            ]}
                        >
                            <div className="text-xs text-slate-500">
                                Projects: {rep.projects_count} · Maintenance: {rep.maintenances_count} · Services: {rep.active_subscriptions_count}/{rep.subscriptions_count}
                            </div>
                        </MobileCard>
                    )}
                />
            </div>
        </>
    );
}
