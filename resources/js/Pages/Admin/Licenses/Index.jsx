import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const licenseStatusClass = (status) => {
    if (status === 'active') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'suspended') {
        return 'bg-amber-100 text-amber-700';
    }

    if (status === 'revoked') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-slate-100 text-slate-600';
};

export default function Index({
    pageTitle = 'Licenses',
    search = '',
    routes = {},
    licenses = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};

    const copyLicense = async (licenseKey) => {
        const key = String(licenseKey || '').trim();
        if (!key) {
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(key);
                return;
            } catch (_error) {
                // Fall through to execCommand fallback.
            }
        }

        const textarea = document.createElement('textarea');
        textarea.value = key;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form id="licensesSearchForm" method="GET" action={routes?.index} className="flex items-center gap-3" data-native="true">
                        <div className="relative w-full max-w-sm">
                            <input
                                type="text"
                                name="search"
                                defaultValue={search}
                                placeholder="Search licenses..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                                onInput={(event) => {
                                    const input = event.currentTarget;
                                    clearTimeout(input.__searchTimer);
                                    input.__searchTimer = setTimeout(() => input.form?.requestSubmit(), 300);
                                }}
                            />
                        </div>
                    </form>
                </div>
                <a href={routes?.create} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    New License
                </a>
            </div>

            <div id="licensesTable">
                <div className="card overflow-x-auto">
                    <table className="w-full min-w-[1150px] text-left text-sm">
                        <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Customer &amp; Order</th>
                                <th className="px-4 py-3">License &amp; URL</th>
                                <th className="px-4 py-3">True verification</th>
                                <th className="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {licenses.length > 0 ? (
                                licenses.map((license) => (
                                    <tr key={license.id} className="border-b border-slate-100">
                                        <td className="px-4 py-3 text-slate-500">{license.id}</td>
                                        <td className="px-4 py-3">
                                            {license.customer_url ? (
                                                <a href={license.customer_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    {license.customer_name}
                                                </a>
                                            ) : (
                                                <span className="text-slate-500">--</span>
                                            )}
                                            {license.is_blocked ? <div className="mt-1 text-xs text-rose-600">Access blocked</div> : null}
                                            <div className="mt-1 text-xs text-slate-500">
                                                Order: {license.order_number} {'>'} {license.product_name} - {license.plan_name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-teal-700">
                                            <div className="flex items-center gap-2">
                                                <span>{license.license_key}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        copyLicense(license.license_key);
                                                    }}
                                                    className="text-slate-400 transition-colors hover:text-teal-600"
                                                    title="Copy license key"
                                                >
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path
                                                            strokeLinecap="round"
                                                            strokeLinejoin="round"
                                                            strokeWidth="2"
                                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                                        />
                                                    </svg>
                                                </button>
                                            </div>
                                            <div className="mt-2 text-slate-900">{license.domain}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${license.verification_class}`}>
                                                {license.verification_label}
                                                <span className={`ml-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${licenseStatusClass(license.license_status)}`}>
                                                    {license.license_status}
                                                </span>
                                                <span className={`ml-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${license.sync_class}`}>
                                                    {license.sync_label}
                                                </span>
                                            </div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                {license.verification_hint}
                                                <span className="ml-2">{license.sync_time_display}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="inline-flex items-center gap-3">
                                                {license.can_sync ? (
                                                    <form method="POST" action={license?.routes?.sync} data-native="true" className="inline">
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <button
                                                            type="submit"
                                                            className="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600"
                                                        >
                                                            Sync
                                                        </button>
                                                    </form>
                                                ) : null}
                                                <a href={license?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    Manage
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={7} className="px-4 py-6 text-center text-slate-500">
                                        No licenses yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {pagination?.has_pages ? (
                    <div className="mt-4 flex items-center justify-end gap-2 text-sm">
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
            </div>
        </>
    );
}