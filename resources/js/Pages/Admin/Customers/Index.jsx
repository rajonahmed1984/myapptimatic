import React, { useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';

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
                    className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white"
                >
                    New Customer
                </a>
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Photo</th>
                                <th className="px-4 py-3">Name & Company</th>
                                <th className="px-4 py-3">Email & mobile</th>
                                <th className="px-4 py-3">Services</th>
                                <th className="px-4 py-3">Projects & Maintenance</th>
                                <th className="px-4 py-3">Login</th>
                                <th className="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-6 text-center text-slate-500">
                                        No customers yet.
                                    </td>
                                </tr>
                            ) : (
                                customers.map((customer) => (
                                    <tr key={customer.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 text-slate-500">
                                            <a href={customer.routes?.show} data-native="true" className="hover:text-teal-600">
                                                {customer.id}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3">
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
                                        <td className="px-4 py-3">
                                            <div>
                                                <a
                                                    href={customer.routes?.show}
                                                    data-native="true"
                                                    className="font-medium text-slate-900 hover:text-teal-600"
                                                >
                                                    {customer.name}
                                                </a>
                                                <div className="text-xs text-slate-500">{customer.company_name || '--'}</div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">
                                            <div>{customer.email || '--'}</div>
                                            <div className="text-xs text-slate-400">{customer.mobile || customer.phone || '--'}</div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">
                                            {Number(customer.active_subscriptions_count || 0)} ({Number(customer.subscriptions_count || 0)})
                                        </td>
                                        <td className="px-4 py-3 text-slate-500">
                                            <div className="text-sm text-slate-700">Projects: {Number(customer.projects_count || 0)}</div>
                                            <div className="text-xs text-slate-500">Maintenance: {Number(customer.project_maintenances_count || 0)}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="text-[11px] text-slate-400">
                                                Last login: {customer.login?.last_login_at || '--'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${customer.status?.classes || 'bg-slate-200 text-slate-700'}`}>
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
                        className={`rounded-full border px-3 py-1 ${pagination.previous_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Previous
                    </a>
                    <a
                        href={pagination.next_url || '#'}
                        data-native="true"
                        className={`rounded-full border px-3 py-1 ${pagination.next_url ? 'border-slate-300 text-slate-700 hover:border-teal-300 hover:text-teal-600' : 'cursor-not-allowed border-slate-200 text-slate-400'}`}
                    >
                        Next
                    </a>
                </div>
            ) : null}
        </>
    );
}
