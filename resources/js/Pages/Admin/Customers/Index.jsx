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

            <div className="card overflow-hidden">
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
