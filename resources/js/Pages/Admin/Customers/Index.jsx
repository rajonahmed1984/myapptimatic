import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';

const BTN = {
    primary: 'bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500',
    secondary: 'border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600',
};

const initials = (value) => {
    const text = String(value || '').trim();
    if (!text) {
        return '--';
    }

    const parts = text.split(/\s+/).filter(Boolean);
    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('');
};

export default function Index({ pageTitle = 'Customers', search = '', routes = {}, customers = [], pagination = {} }) {
    const [searchTerm, setSearchTerm] = useState(String(search || ''));
    const isFirstRender = useRef(true);

    useEffect(() => {
        setSearchTerm(String(search || ''));
    }, [search]);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const current = String(searchTerm || '').trim();
        const server = String(search || '').trim();
        if (current === server) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(
                routes?.index || '/admin/customers',
                current === '' ? {} : { search: current },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            );
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [searchTerm, search, routes?.index]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <div className="flex items-center gap-3">
                        <div className="relative">
                            <input
                                type="text"
                                name="search"
                                value={searchTerm}
                                onChange={(event) => setSearchTerm(event.target.value)}
                                placeholder="Search customers..."
                                className="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>
                <a
                    href={routes?.create}
                    data-native="true"
                    className={BTN.primary}
                >
                    New Customer
                </a>
            </div>

            {/* Mobile Cards List (<md) */}
            <div className="md:hidden space-y-3">
                {customers.length === 0 ? (
                    <div className="card p-6 text-center text-sm text-slate-500">
                        No customers found.
                    </div>
                ) : (
                    customers.map((customer) => (
                        <div key={customer.id} className="card p-4 flex flex-col gap-3 shadow-sm border border-slate-200/80">
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex items-center gap-3 min-w-0">
                                    {customer.avatar_url ? (
                                        <img
                                            src={customer.avatar_url}
                                            alt={customer.name}
                                            className="h-11 w-11 rounded-xl object-cover shrink-0 border border-slate-200"
                                        />
                                    ) : (
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-700 text-sm font-bold border border-teal-200">
                                            {initials(customer.name)}
                                        </div>
                                    )}
                                    <div className="min-w-0">
                                        <a
                                            href={customer.routes?.show}
                                            data-native="true"
                                            className="font-bold text-sm text-slate-900 hover:text-teal-600 block truncate"
                                        >
                                            {customer.name}
                                        </a>
                                        {customer.company_name && (
                                            <div className="text-xs text-slate-500 truncate mt-0.5">
                                                {customer.company_name}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${customer.status?.classes || 'bg-slate-100 text-slate-700'}`}>
                                    {customer.status?.label || 'Active'}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs">
                                <div className="space-y-0.5">
                                    <div className="text-[10px] uppercase font-semibold text-slate-400">Services</div>
                                    <div className="font-bold text-slate-800">
                                        {Number(customer.active_subscriptions_count || 0)} active <span className="text-slate-400 font-normal">({Number(customer.subscriptions_count || 0)})</span>
                                    </div>
                                </div>
                                <div className="space-y-0.5 text-right">
                                    <div className="text-[10px] uppercase font-semibold text-slate-400">Projects</div>
                                    <div className="font-bold text-slate-800">
                                        {Number(customer.projects_count || 0)} projects
                                    </div>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 pt-2 border-t border-slate-100">
                                <a
                                    href={customer.routes?.show}
                                    data-native="true"
                                    className="flex-1 text-center py-2 px-3 rounded-xl bg-teal-600 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition active:scale-95"
                                >
                                    View Profile
                                </a>
                                {(customer.mobile || customer.phone) && (
                                    <a
                                        href={`tel:${customer.mobile || customer.phone}`}
                                        className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition active:scale-95 flex items-center justify-center"
                                        aria-label="Call customer"
                                    >
                                        <svg className="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </a>
                                )}
                                {customer.email && (
                                    <a
                                        href={`mailto:${customer.email}`}
                                        className="py-2 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 transition active:scale-95 flex items-center justify-center"
                                        aria-label="Email customer"
                                    >
                                        <svg className="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </a>
                                )}
                            </div>
                        </div>
                    ))
                )}
            </div>

            {/* Desktop Table (>=md) */}
            <div className="hidden md:block card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3 whitespace-nowrap">ID</th>
                                <th className="px-4 py-3 whitespace-nowrap">Photo</th>
                                <th className="px-4 py-3 whitespace-nowrap">Customer Info</th>
                                <th className="px-4 py-3 whitespace-nowrap">Services</th>
                                <th className="px-4 py-3 whitespace-nowrap">Projects & Maintenance</th>
                                <th className="px-4 py-3 whitespace-nowrap">Last Login</th>
                                <th className="px-4 py-3 whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-6 text-center text-slate-500 whitespace-nowrap">
                                        No customers yet.
                                    </td>
                                </tr>
                            ) : (
                                customers.map((customer) => (
                                    <tr key={customer.id} className="border-b border-slate-100 transition hover:bg-slate-50/60">
                                        <td className="px-4 py-3 text-slate-500 whitespace-nowrap">
                                            <a href={customer.routes?.show} data-native="true" className="font-semibold text-slate-700 hover:text-teal-600">
                                                {customer.id}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {customer.avatar_url ? (
                                                <img
                                                    src={customer.avatar_url}
                                                    alt={customer.name}
                                                    className="h-10 w-10 rounded object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded bg-slate-200 text-xs font-semibold text-slate-700">
                                                    {initials(customer.name)}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <div className="whitespace-nowrap space-y-0.5">
                                                <div className="whitespace-nowrap">
                                                    <a
                                                        href={customer.routes?.show}
                                                        data-native="true"
                                                        className="font-semibold text-slate-900 hover:text-teal-600 inline-block"
                                                    >
                                                        {customer.name}
                                                    </a>
                                                </div>
                                                <div className="text-xs text-slate-500 whitespace-nowrap">
                                                    {customer.company_name || '--'}
                                                </div>
                                                <div className="text-xs text-slate-600 whitespace-nowrap">
                                                    {customer.email || '--'}
                                                </div>
                                                <div className="text-xs text-slate-400 whitespace-nowrap">
                                                    {customer.mobile || customer.phone || '--'}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 whitespace-nowrap">
                                            {Number(customer.active_subscriptions_count || 0)} ({Number(customer.subscriptions_count || 0)})
                                        </td>
                                        <td className="px-4 py-3 text-slate-500 whitespace-nowrap">
                                            <div className="text-sm text-slate-700 whitespace-nowrap">Projects: {Number(customer.projects_count || 0)}</div>
                                            <div className="text-xs text-slate-500 whitespace-nowrap">Maintenance: {Number(customer.project_maintenances_count || 0)}</div>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <div className="text-xs text-slate-600 whitespace-nowrap">
                                                {customer.login?.last_login_at || '--'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <span className={`inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${customer.status?.classes || 'bg-slate-200 text-slate-700'}`}>
                                                {customer.status?.label || '--'}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {pagination?.has_pages ? (
                <div className="mt-4 flex items-center justify-between gap-2 text-sm">
                    <a
                        href={pagination.previous_url || '#'}
                        data-native="true"
                        className={pagination.previous_url ? BTN.secondary : 'cursor-not-allowed rounded-full border border-slate-200 px-3 py-1 text-slate-400'}
                    >
                        Previous
                    </a>
                    <a
                        href={pagination.next_url || '#'}
                        data-native="true"
                        className={pagination.next_url ? BTN.secondary : 'cursor-not-allowed rounded-full border border-slate-200 px-3 py-1 text-slate-400'}
                    >
                        Next
                    </a>
                </div>
            ) : null}
        </>
    );
}
