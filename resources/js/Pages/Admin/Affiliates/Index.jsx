import React from 'react';
import { Head, router } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';
import SearchableSelect from '../../../Components/SearchableSelect';
import Pagination from '../../../Components/Table/Pagination';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';
import FilterSheet from '../../../Components/Mobile/FilterSheet';

const statusBadgeClass = (status) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'suspended') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-slate-100 text-slate-700';
};

export default function Index({
    pageTitle = 'Affiliates',
    filters = {},
    status_options = [],
    routes = {},
    affiliates = [],
    pagination = {},
}) {
    const [statusFilter, setStatusFilter] = React.useState(filters?.status ?? '');
    const searchExtras = React.useMemo(() => (statusFilter ? { status: statusFilter } : null), [statusFilter]);
    const { searchTerm, setSearchTerm } = useInertiaLiveSearch({
        initialValue: filters?.search ?? '',
        url: routes?.index,
        extraData: searchExtras,
    });
    const hasFilters = Boolean(searchTerm.trim() || statusFilter);
    const statusOptions = status_options.map((option) => ({ value: String(option.value || ''), label: option.label }));

    React.useEffect(() => {
        setStatusFilter(filters?.status ?? '');
    }, [filters?.status]);

    const handleFilterSubmit = (event) => {
        event.preventDefault();

        router.get(
            routes?.index || '/admin/affiliates',
            {
                ...(searchTerm.trim() ? { search: searchTerm.trim() } : {}),
                ...(statusFilter ? { status: statusFilter } : {}),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <div className="flex flex-1 flex-wrap items-center gap-3">
                        <form method="GET" action={routes?.index} className="min-w-0 flex-1 max-w-sm" onSubmit={handleFilterSubmit}>
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search by name, email, or code..."
                                className="ui-input w-full"
                            />
                        </form>
                        <span className="text-xs font-semibold text-slate-500">
                            Showing {pagination?.from ?? 1} – {pagination?.to ?? affiliates.length} of {pagination?.total ?? affiliates.length} affiliates
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="w-40">
                            <SearchableSelect
                                name="status"
                                value={statusFilter}
                                onChange={(nextValue) => setStatusFilter(String(nextValue || ''))}
                                options={statusOptions}
                                placeholder="All statuses"
                            />
                        </div>
                        <a
                            href={routes?.create}
                            data-native="true"
                            className="ui-btn-primary"
                        >
                            Add affiliate
                        </a>
                    </div>
                </div>

                <DataTable
                    framed={false}
                    rows={affiliates}
                    emptyMessage="No affiliates found."
                            columns={[
                                {
                                    key: 'affiliate',
                                    header: 'Affiliate',
                                    render: (affiliate) => (
                                        <>
                                            <div className="font-semibold text-slate-900">{affiliate.customer_name}</div>
                                            <div className="text-xs text-slate-500">{affiliate.customer_email}</div>
                                        </>
                                    ),
                                },
                                {
                                    key: 'code',
                                    header: 'Code',
                                    render: (affiliate) => <code className="rounded bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700">{affiliate.affiliate_code}</code>,
                                },
                                {
                                    key: 'status',
                                    header: 'Status',
                                    render: (affiliate) => <span className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${statusBadgeClass(affiliate.status)}`}>{affiliate.status_label}</span>,
                                },
                                { key: 'commission', header: 'Commission', render: (affiliate) => affiliate.commission_display },
                                { key: 'balance', header: 'Balance', cellClassName: 'font-semibold', render: (affiliate) => affiliate.balance_display },
                                { key: 'referrals', header: 'Referrals', render: (affiliate) => affiliate.referrals_display },
                                {
                                    key: 'actions',
                                    header: 'Actions',
                                    render: (affiliate) => <a href={affiliate?.routes?.show} data-native="true" className="font-semibold text-teal-600 hover:text-teal-500">View</a>,
                                },
                            ]}
                            renderMobileCard={(affiliate) => (
                                <MobileCard
                                    title={affiliate.customer_name}
                                    subtitle={affiliate.customer_email}
                                    badge={affiliate.status_label}
                                    badgeColor={statusBadgeClass(affiliate.status)}
                                    metrics={[
                                        { label: 'Balance', value: affiliate.balance_display },
                                        { label: 'Referrals', value: affiliate.referrals_display },
                                    ]}
                                    actions={
                                        <a
                                            href={affiliate?.routes?.show}
                                            data-native="true"
                                            className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                        >
                                            View
                                        </a>
                                    }
                                >
                                    <div className="text-xs text-slate-500">Code: {affiliate.affiliate_code} · Commission: {affiliate.commission_display}</div>
                                </MobileCard>
                            )}
                        />

                <Pagination
                    pagination={pagination}
                    label="affiliates"
                    className="border-t border-slate-200 px-4 py-3"
                />
            </div>
        </>
    );
}
