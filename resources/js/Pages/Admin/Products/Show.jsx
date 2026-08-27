import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';

const statusClass = (status) => {
    switch (status) {
        case 'active':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'suspended':
            return 'bg-amber-100 text-amber-800 border-amber-200';
        case 'expired':
            return 'bg-rose-100 text-rose-800 border-rose-200';
        case 'cancelled':
            return 'bg-slate-100 text-slate-700 border-slate-300';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

export default function Show({
    pageTitle = 'Product Details',
    product = {},
    stats = {},
    plans = [],
    client_services = [],
    routes = {},
}) {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [copiedKey, setCopiedKey] = useState(null);

    const handleCopy = (key) => {
        if (!key || key === '--') return;
        navigator.clipboard.writeText(key).then(() => {
            setCopiedKey(key);
            setTimeout(() => setCopiedKey(null), 2000);
        });
    };

    const filteredServices = useMemo(() => {
        return client_services.filter((item) => {
            const matchesStatus = statusFilter === 'all' || item.status === statusFilter;
            if (!matchesStatus) return false;

            if (!search) return true;
            const query = search.toLowerCase();
            return (
                (item.customer_name && item.customer_name.toLowerCase().includes(query)) ||
                (item.customer_company && item.customer_company.toLowerCase().includes(query)) ||
                (item.customer_email && item.customer_email.toLowerCase().includes(query)) ||
                (item.license_key && item.license_key.toLowerCase().includes(query)) ||
                (item.plan_name && item.plan_name.toLowerCase().includes(query)) ||
                (item.domains && item.domains.some((d) => d.toLowerCase().includes(query)))
            );
        });
    }, [client_services, search, statusFilter]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <a
                            href={routes?.index}
                            data-native="true"
                            className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 hover:border-teal-500 hover:text-teal-600 transition"
                            title="Back to Products"
                        >
                            <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </a>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-bold text-slate-900">{product.name}</h1>
                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(product.status)}`}>
                                    {product.status_label}
                                </span>
                            </div>
                            <div className="mt-1 flex items-center gap-3 text-xs text-slate-500">
                                <span>Slug: <code className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">{product.slug}</code></span>
                                <span>•</span>
                                <span>Created: {product.created_at}</span>
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <a
                            href={routes?.edit}
                            data-native="true"
                            className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-500 hover:text-teal-600 transition"
                        >
                            Edit Product
                        </a>
                        <a
                            href={routes?.create_plan}
                            data-native="true"
                            className="rounded-full bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700 transition shadow-sm"
                        >
                            Add Plan
                        </a>
                    </div>
                </div>

                {/* Description if present */}
                {product.description ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
                        <span className="font-semibold text-slate-800">Description: </span>
                        {product.description}
                    </div>
                ) : null}

                {/* Metric Summary Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Clients</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{stats.total_clients || 0}</div>
                        <div className="mt-1 text-xs text-slate-400">Subscribed customers</div>
                    </div>

                    <div className="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5 shadow-sm">
                        <div className="text-xs font-semibold uppercase tracking-wider text-emerald-700">Active Licenses</div>
                        <div className="mt-2 text-2xl font-bold text-emerald-700">{stats.active_licenses || 0}</div>
                        <div className="mt-1 text-xs text-emerald-600/80">Currently running</div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Licenses</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{stats.total_licenses || 0}</div>
                        <div className="mt-1 text-xs text-slate-400">Issued licenses/services</div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-semibold uppercase tracking-wider text-slate-500">Plans</div>
                        <div className="mt-2 text-2xl font-bold text-slate-900">{stats.total_plans || 0}</div>
                        <div className="mt-1 text-xs text-slate-400">Available pricing tiers</div>
                    </div>
                </div>

                {/* Plans Overview Cards */}
                {plans.length > 0 ? (
                    <div className="space-y-3">
                        <h2 className="text-base font-semibold text-slate-900">Pricing &amp; Service Plans</h2>
                        <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                            {plans.map((plan) => (
                                <div key={plan.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-teal-300 transition">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="font-bold text-slate-900">{plan.name}</div>
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${plan.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>
                                            {plan.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </div>
                                    <div className="mt-2 text-lg font-bold text-teal-700">{plan.price_formatted}</div>
                                    <div className="text-xs text-slate-500">Billing: {plan.interval}</div>
                                    <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-xs">
                                        <span className="text-slate-500">{plan.seat_limit ? `${plan.seat_limit} seats` : 'Unlimited seats'}</span>
                                        <a href={plan.routes?.edit} data-native="true" className="font-semibold text-teal-600 hover:text-teal-700">
                                            Edit Plan
                                        </a>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {/* Client Licenses & Subscriptions Table Section */}
                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-5">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">Client License &amp; Service List</h2>
                                <p className="text-xs text-slate-500">
                                    Clients who have acquired licenses and subscriptions for this product.
                                </p>
                            </div>

                            {/* Search & Status Filters */}
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex rounded-full border border-slate-200 bg-slate-50 p-1 text-xs font-semibold">
                                    {['all', 'active', 'suspended', 'expired'].map((st) => (
                                        <button
                                            key={st}
                                            type="button"
                                            onClick={() => setStatusFilter(st)}
                                            className={`rounded-full px-3 py-1 capitalize transition ${
                                                statusFilter === st
                                                    ? 'bg-white text-teal-700 shadow-sm'
                                                    : 'text-slate-600 hover:text-slate-900'
                                            }`}
                                        >
                                            {st}
                                        </button>
                                    ))}
                                </div>

                                <div className="relative">
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Search client, key, domain..."
                                        className="h-9 w-64 rounded-full border border-slate-300 bg-slate-50 px-3.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-teal-500 focus:bg-white focus:outline-none"
                                    />
                                    {search ? (
                                        <button
                                            type="button"
                                            onClick={() => setSearch('')}
                                            className="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600"
                                        >
                                            ✕
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[960px] text-left text-sm text-slate-700">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th className="px-4 py-3">Client / Customer</th>
                                    <th className="px-4 py-3">Plan / Service</th>
                                    <th className="px-4 py-3">License Key</th>
                                    <th className="px-4 py-3">Active Domain(s)</th>
                                    <th className="px-4 py-3">Validity</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Last Check</th>
                                    <th className="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filteredServices.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-8 text-center text-sm text-slate-500">
                                            {search || statusFilter !== 'all'
                                                ? 'No client licenses match your filter criteria.'
                                                : 'No clients have acquired licenses or services for this product yet.'}
                                        </td>
                                    </tr>
                                ) : (
                                    filteredServices.map((service) => (
                                        <tr key={service.id} className="hover:bg-slate-50/70 transition">
                                            {/* Customer */}
                                            <td className="px-4 py-3">
                                                <div>
                                                    {service.customer_route ? (
                                                        <a
                                                            href={service.customer_route}
                                                            data-native="true"
                                                            className="font-semibold text-slate-900 hover:text-teal-600 block"
                                                        >
                                                            {service.customer_name}
                                                        </a>
                                                    ) : (
                                                        <span className="font-semibold text-slate-900">{service.customer_name}</span>
                                                    )}
                                                    {service.customer_company ? (
                                                        <div className="text-xs text-slate-500">{service.customer_company}</div>
                                                    ) : null}
                                                    {service.customer_email ? (
                                                        <div className="text-xs text-slate-400">{service.customer_email}</div>
                                                    ) : null}
                                                </div>
                                            </td>

                                            {/* Plan */}
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-slate-800">{service.plan_name}</div>
                                                {service.subscription_route ? (
                                                    <a
                                                        href={service.subscription_route}
                                                        data-native="true"
                                                        className="text-xs text-teal-600 hover:underline"
                                                    >
                                                        Sub #{service.subscription_id}
                                                    </a>
                                                ) : null}
                                            </td>

                                            {/* License Key */}
                                            <td className="px-4 py-3 font-mono text-xs">
                                                {service.license_key && service.license_key !== '--' ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <span className="rounded bg-slate-100 px-2 py-1 text-slate-800 select-all">
                                                            {service.license_key.length > 16
                                                                ? `${service.license_key.substring(0, 8)}...${service.license_key.substring(service.license_key.length - 8)}`
                                                                : service.license_key}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleCopy(service.license_key)}
                                                            className="text-slate-400 hover:text-teal-600 p-1"
                                                            title="Copy full key"
                                                        >
                                                            {copiedKey === service.license_key ? (
                                                                <span className="text-[11px] font-sans font-bold text-emerald-600">Copied!</span>
                                                            ) : (
                                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                                </svg>
                                                            )}
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <span className="text-slate-400 font-sans">--</span>
                                                )}
                                            </td>

                                            {/* Domains */}
                                            <td className="px-4 py-3">
                                                {service.domains && service.domains.length > 0 ? (
                                                    <div className="flex flex-wrap gap-1">
                                                        {service.domains.map((dom, i) => (
                                                            <span
                                                                key={i}
                                                                className="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 border border-blue-100"
                                                            >
                                                                {dom}
                                                            </span>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-slate-400">No domain bound</span>
                                                )}
                                            </td>

                                            {/* Validity */}
                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                <div>Starts: {service.starts_at}</div>
                                                <div className="text-slate-400">Expires: {service.expires_at}</div>
                                            </td>

                                            {/* Status */}
                                            <td className="px-4 py-3">
                                                <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${statusClass(service.status)}`}>
                                                    {service.status_label}
                                                </span>
                                            </td>

                                            {/* Last Check */}
                                            <td className="px-4 py-3 text-xs text-slate-600">
                                                <div>{service.last_check_at}</div>
                                                {service.last_check_ip && service.last_check_ip !== '--' ? (
                                                    <div className="font-mono text-[11px] text-slate-400">{service.last_check_ip}</div>
                                                ) : null}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2 text-xs font-semibold">
                                                    {service.customer_route ? (
                                                        <a
                                                            href={service.customer_route}
                                                            data-native="true"
                                                            className="text-slate-700 hover:text-teal-600"
                                                        >
                                                            Client
                                                        </a>
                                                    ) : null}
                                                    {service.subscription_route ? (
                                                        <a
                                                            href={service.subscription_route}
                                                            data-native="true"
                                                            className="text-teal-600 hover:text-teal-700"
                                                        >
                                                            Subscription
                                                        </a>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
