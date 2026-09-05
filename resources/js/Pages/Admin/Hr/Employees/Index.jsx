import React from 'react';
import { Head } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../../hooks/useInertiaLiveSearch';
import DataTable from '../../../../Components/Table/DataTable';
import MobileCard from '../../../../Components/Mobile/MobileCard';

export default function Index({
    pageTitle = 'Employees',
    search = '',
    employees = [],
    pagination = {},
    routes = {},
}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    return (
        <>
            <Head title={pageTitle} />

            <div className="card p-6 overflow-hidden">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-4">
                    <form
                        method="GET"
                        action={routes?.index}
                        className="flex-1 max-w-sm"
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
                            placeholder="Search employees..."
                            className="w-full h-8 rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600"
                        />
                    </form>
                    <a href={routes?.create} data-native="true" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Add employee</a>
                </div>
                <DataTable
                    rows={employees}
                    emptyMessage="No employees found."
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'font-semibold text-slate-900', render: (employee) => employee.id },
                        {
                            key: 'photo',
                            header: 'Photo',
                            render: (employee) => (
                                employee.photo_url ? (
                                    <img src={employee.photo_url} alt={`${employee.name} photo`} className="h-10 w-10 rounded-full border border-slate-200 object-cover" loading="lazy" />
                                ) : (
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-xs font-semibold text-slate-500">
                                        {(employee.name || '?').slice(0, 1).toUpperCase()}
                                    </div>
                                )
                            ),
                        },
                        {
                            key: 'name',
                            header: 'Name / Designation',
                            render: (employee) => (
                                <>
                                    <div className="font-semibold text-slate-900"><a href={employee.routes.show} data-native="true" className="hover:text-teal-600">{employee.name}</a></div>
                                    <div className="text-xs text-slate-500">{employee.email}</div>
                                    <div className="mt-1 text-xs text-slate-500">{employee.designation || '--'}</div>
                                </>
                            ),
                        },
                        {
                            key: 'employment',
                            header: 'Employment / Salary Type',
                            render: (employee) => (
                                <>
                                    <div>{employee.employment_type || '--'}</div>
                                    <div className="mt-1 text-xs text-slate-500">{employee.salary_type_label || employee.salary_type || '--'}</div>
                                </>
                            ),
                        },
                        { key: 'join_date', header: 'Join Date', render: (employee) => employee.join_date },
                        { key: 'manager', header: 'Manager', render: (employee) => employee.manager_name },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (employee) => (
                                <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${employee.status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : 'border-slate-300 text-slate-600 bg-slate-50'}`}>
                                    {employee.status_label}
                                </span>
                            ),
                        },
                        {
                            key: 'login',
                            header: 'Login',
                            render: (employee) => (
                                <>
                                    {employee.show_login_badge ? (
                                        <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${employee.login_classes}`}>{employee.login_label}</span>
                                    ) : null}
                                    <div className="mt-1 text-[11px] text-slate-400">Last login: {employee.last_login_at}</div>
                                </>
                            ),
                        },
                        {
                            key: 'actions',
                            header: 'Actions',
                            headerClassName: 'text-right',
                            cellClassName: 'text-right space-x-2',
                            render: (employee) => (
                                <>
                                    <a href={employee.routes.edit} data-native="true" className="text-xs text-emerald-700 hover:underline">Edit</a>
                                    <form method="POST" action={employee.routes.destroy} data-native="true" className="inline">
                                        <input type="hidden" name="_token" value={token} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button className="text-xs text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </>
                            ),
                        },
                    ]}
                    renderMobileCard={(employee) => (
                        <MobileCard
                            avatar={employee.photo_url ? (
                                <img src={employee.photo_url} alt={`${employee.name} photo`} className="h-11 w-11 rounded-full border border-slate-200 object-cover" loading="lazy" />
                            ) : (
                                <div className="flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-sm font-semibold text-slate-500">
                                    {(employee.name || '?').slice(0, 1).toUpperCase()}
                                </div>
                            )}
                            title={<a href={employee.routes.show} data-native="true" className="hover:text-teal-600">{employee.name}</a>}
                            subtitle={employee.designation || employee.email}
                            badge={employee.status_label}
                            badgeColor={employee.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}
                            metrics={[
                                { label: 'Employment', value: employee.employment_type || '--' },
                                { label: 'Join Date', value: employee.join_date || '--' },
                            ]}
                            actions={
                                <>
                                    <a
                                        href={employee.routes.show}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition active:scale-95"
                                    >
                                        View
                                    </a>
                                    <a
                                        href={employee.routes.edit}
                                        data-native="true"
                                        className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                    >
                                        Edit
                                    </a>
                                    <form method="POST" action={employee.routes.destroy} data-native="true">
                                        <input type="hidden" name="_token" value={token} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-rose-600 hover:bg-rose-50 transition active:scale-95">Delete</button>
                                    </form>
                                </>
                            }
                        >
                            <div className="text-xs text-slate-500">
                                Manager: {employee.manager_name || '--'} · Last login: {employee.last_login_at || '--'}
                            </div>
                        </MobileCard>
                    )}
                />

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                        <a href={pagination?.previous_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.previous_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Previous</a>
                        <a href={pagination?.next_url || '#'} data-native="true" className={`rounded border px-3 py-1 ${pagination?.next_url ? 'border-slate-300 text-slate-700' : 'pointer-events-none border-slate-200 text-slate-300'}`}>Next</a>
                    </div>
                ) : null}
            </div>
        </>
    );
}
