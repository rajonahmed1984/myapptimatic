import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import useInertiaLiveSearch from '../../../hooks/useInertiaLiveSearch';

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

const licenseKeyIndicator = (license) => {
    const verificationLabel = String(license?.verification_label || '').toLowerCase();
    const verificationHint = String(license?.verification_hint || '').toLowerCase();

    if (verificationLabel === 'verified' && verificationHint.includes('match')) {
        return {
            type: 'verified',
            title: 'License key verified',
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (verificationHint === 'domain_not_bound') {
        return {
            type: 'verified',
            title: 'License key verified',
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    return {
        type: 'failed',
        title: 'License key failed',
        className: 'bg-rose-100 text-rose-700',
    };
};

const urlIndicator = (license) => {
    const verificationLabel = String(license?.verification_label || '').toLowerCase();
    const verificationHint = String(license?.verification_hint || '').toLowerCase();
    const syncLabel = String(license?.sync_label || '').toLowerCase();
    const domain = String(license?.domain || '').trim();

    if (domain === '' || domain === '--' || verificationHint === 'domain_not_bound') {
        return {
            type: 'failed',
            title: 'URL not verified',
            className: 'bg-rose-100 text-rose-700',
        };
    }

    if (syncLabel === 'stale' || syncLabel === 'never') {
        return {
            type: 'stale',
            title: 'URL sync stale or never synced',
            className: 'bg-amber-100 text-amber-700',
        };
    }

    if (verificationLabel === 'verified' && verificationHint.includes('match')) {
        return {
            type: 'verified',
            title: 'URL verified and matched',
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    return {
        type: 'failed',
        title: 'URL failed',
        className: 'bg-rose-100 text-rose-700',
    };
};

const renderStatusIcon = (type) => {
    if (type === 'verified') {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 10-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
            </svg>
        );
    }

    if (type === 'stale') {
        return (
            <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.517 11.59c.75 1.334-.213 2.99-1.742 2.99H3.482c-1.53 0-2.492-1.656-1.742-2.99l6.517-11.59zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1z" clipRule="evenodd" />
            </svg>
        );
    }

    return (
        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-10.293a1 1 0 00-1.414-1.414L10 8.586 7.707 6.293a1 1 0 10-1.414 1.414L8.586 10l-2.293 2.293a1 1 0 103.414 1.414L10 11.414l2.293 2.293a1 1 0 001.414-1.414L11.414 10l2.293-2.293z" clipRule="evenodd" />
        </svg>
    );
};

export default function Index({
    pageTitle = 'Licenses',
    search = '',
    routes = {},
    licenses = [],
    pagination = {},
}) {
    const { csrf_token: csrfToken = '' } = usePage().props || {};
    const [toast, setToast] = React.useState(null);
    const [syncingIds, setSyncingIds] = React.useState({});
    const toastTimerRef = React.useRef(null);
    const { searchTerm, setSearchTerm, submitSearch } = useInertiaLiveSearch({
        initialValue: search,
        url: routes?.index,
    });

    const showToast = React.useCallback((message, type = 'success') => {
        if (toastTimerRef.current) {
            clearTimeout(toastTimerRef.current);
        }

        setToast({
            id: Date.now(),
            message: String(message || '').trim(),
            type,
        });

        toastTimerRef.current = window.setTimeout(() => {
            setToast(null);
        }, 2500);
    }, []);

    React.useEffect(() => {
        return () => {
            if (toastTimerRef.current) {
                clearTimeout(toastTimerRef.current);
            }
        };
    }, []);

    const copyLicense = async (licenseKey) => {
        const key = String(licenseKey || '').trim();
        if (!key) {
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(key);
                showToast('License key copied.');
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
        showToast('License key copied.');
    };

    const syncLicense = async (license) => {
        const licenseId = Number(license?.id || 0);
        const syncRoute = String(license?.routes?.sync || '');
        if (!licenseId || !syncRoute || syncingIds[licenseId]) {
            return;
        }

        setSyncingIds((previous) => ({
            ...previous,
            [licenseId]: true,
        }));

        try {
            const response = await fetch(syncRoute, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload?.ok === false) {
                throw new Error(payload?.message || 'License sync failed.');
            }

            showToast(payload?.message || 'License sync completed.');
        } catch (error) {
            showToast(error?.message || 'License sync failed.', 'error');
        } finally {
            setSyncingIds((previous) => ({
                ...previous,
                [licenseId]: false,
            }));
        }
    };

    return (
        <>
            <Head title={pageTitle} />
            {toast?.message ? (
                <div className="pointer-events-none fixed right-4 top-4 z-50">
                    <div
                        className={`rounded-xl border px-4 py-2 text-sm font-semibold shadow-lg ${
                            toast.type === 'error'
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        }`}
                    >
                        {toast.message}
                    </div>
                </div>
            ) : null}

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex-1">
                    <form
                        id="licensesSearchForm"
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
                                placeholder="Search licenses..."
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm"
                            />
                        </div>
                    </form>
                </div>
                <a href={routes?.manage_subscriptions} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">
                    Manage in Subscriptions
                </a>
            </div>

            <div id="licensesTable">
                <div className="card overflow-x-auto">
                    <table className="w-full min-w-[1150px] text-left text-sm">
                        <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">ID</th>
                                <th className="px-4 py-3">Customer &amp; Product</th>
                                <th className="px-4 py-3">License &amp; URL</th>
                                <th className="px-4 py-3">True verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            {licenses.length > 0 ? (
                                licenses.map((license) => {
                                    const keyIndicator = licenseKeyIndicator(license);
                                    const domainIndicator = urlIndicator(license);

                                    return (
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
                                                    {license.product_name} - {license.plan_name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-teal-700">
                                                <div className="space-y-2">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${keyIndicator.className}`}
                                                            title={keyIndicator.title}
                                                            aria-label={keyIndicator.title}
                                                        >
                                                            {renderStatusIcon(keyIndicator.type)}
                                                        </span>
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
                                                    <div className="flex items-center gap-2 text-slate-900">
                                                        <span
                                                            className={`inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full ${domainIndicator.className}`}
                                                            title={domainIndicator.title}
                                                            aria-label={domainIndicator.title}
                                                        >
                                                            {renderStatusIcon(domainIndicator.type)}
                                                        </span>
                                                        <span>{license.domain}</span>
                                                        {license.can_sync ? (
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    syncLicense(license);
                                                                }}
                                                                disabled={Boolean(syncingIds[license.id])}
                                                                className={`transition-colors ${
                                                                    syncingIds[license.id] ? 'cursor-not-allowed text-slate-300' : 'text-slate-400 hover:text-teal-600'
                                                                }`}
                                                                title="Sync license"
                                                                aria-label="Sync license"
                                                            >
                                                                <svg
                                                                    className={`h-4 w-4 ${syncingIds[license.id] ? 'animate-spin' : ''}`}
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    strokeWidth="2"
                                                                    strokeLinecap="round"
                                                                    strokeLinejoin="round"
                                                                >
                                                                    <path d="M21 12a9 9 0 0 1-15.5 6.36L3 16" />
                                                                    <path d="M3 12a9 9 0 0 1 15.5-6.36L21 8" />
                                                                    <polyline points="3 21 3 16 8 16" />
                                                                    <polyline points="16 8 21 8 21 3" />
                                                                </svg>
                                                            </button>
                                                        ) : null}
                                                    </div>
                                                </div>
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
                                        </tr>
                                    );
                                })
                            ) : (
                                <tr>
                                    <td colSpan={4} className="px-4 py-6 text-center text-slate-500">
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
