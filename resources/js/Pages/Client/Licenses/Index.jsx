import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import DataTable from '../../../Components/Table/DataTable';
import MobileCard from '../../../Components/Mobile/MobileCard';

export default function Index({ licenses = [], routes = {} }) {
    const [copiedKey, setCopiedKey] = useState(null);

    const copyLicense = (key) => {
        if (!key || key === '-') return;
        if (navigator?.clipboard?.writeText) {
            navigator.clipboard.writeText(key).catch(() => {});
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = key;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
            } catch (e) {}
            document.body.removeChild(textarea);
        }
        setCopiedKey(key);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    return (
        <>
            <Head title="Licenses" />

            {licenses.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No licenses found.</div>
            ) : (
                <DataTable
                    rows={licenses}
                    columns={[
                        { key: 'id', header: 'ID', cellClassName: 'text-slate-500', render: (l) => l.id },
                        {
                            key: 'site',
                            header: 'Site',
                            render: (l) => (l.site_url ? (
                                <a href={l.site_url} target="_blank" rel="noreferrer" className="font-medium text-slate-700 hover:text-teal-600">{l.domain}</a>
                            ) : <span className="text-slate-400">--</span>),
                        },
                        { key: 'product', header: 'Product', cellClassName: 'text-slate-600', render: (l) => l.product_name },
                        { key: 'plan', header: 'Plan', cellClassName: 'text-slate-600', render: (l) => l.plan_name },
                        { key: 'installed', header: 'Installed on', cellClassName: 'text-slate-500', render: (l) => l.installed_on },
                        {
                            key: 'license',
                            header: 'License',
                            cellClassName: 'font-mono text-xs text-slate-700',
                            render: (l) => {
                                const key = l.license_key && l.license_key !== '-' ? l.license_key : l.masked_key;
                                const isCopied = copiedKey === key && key !== '-';
                                return (
                                    <div className="flex items-center gap-1.5 font-mono text-xs">
                                        <span
                                            className="inline-block max-w-[220px] truncate font-mono text-xs font-semibold text-slate-800 select-all"
                                            title={key}
                                        >
                                            {key || '-'}
                                        </span>
                                        {key && key !== '-' && (
                                            <button
                                                type="button"
                                                onClick={() => copyLicense(key)}
                                                className="inline-flex items-center justify-center rounded p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-teal-600 focus:outline-none"
                                                title={isCopied ? 'Copied!' : 'Copy license key'}
                                                aria-label="Copy license key"
                                            >
                                                {isCopied ? (
                                                    <svg className="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                ) : (
                                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                )}
                                            </button>
                                        )}
                                        {isCopied && (
                                            <span className="text-[11px] font-semibold text-emerald-600">Copied!</span>
                                        )}
                                    </div>
                                );
                            },
                        },
                        {
                            key: 'status',
                            header: 'Status',
                            render: (l) => (
                                <span className={l.is_active ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700' : 'rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700'}>
                                    {l.status_label}
                                </span>
                            ),
                        },
                    ]}
                    renderMobileCard={(license) => {
                        const key = license.license_key && license.license_key !== '-' ? license.license_key : license.masked_key;
                        const isCopied = copiedKey === key && key !== '-';
                        return (
                            <MobileCard
                                title={license.site_url ? (
                                    <a href={license.site_url} target="_blank" rel="noreferrer" className="hover:text-teal-600">{license.domain}</a>
                                ) : (license.domain || '--')}
                                subtitle={`${license.product_name} · ${license.plan_name}`}
                                badge={license.status_label}
                                badgeColor={license.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}
                                metrics={[
                                    { label: 'Installed on', value: license.installed_on || '--' },
                                ]}
                            >
                                <div className="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                                    <span className="font-mono text-xs text-slate-700 truncate select-all">{key}</span>
                                    {key && key !== '-' && (
                                        <button
                                            type="button"
                                            onClick={() => copyLicense(key)}
                                            className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 hover:bg-teal-50 hover:text-teal-700"
                                        >
                                            {isCopied ? (
                                                <>
                                                    <svg className="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span className="text-emerald-600 font-semibold">Copied!</span>
                                                </>
                                            ) : (
                                                <>
                                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                    </svg>
                                                    <span>Copy</span>
                                                </>
                                            )}
                                        </button>
                                    )}
                                </div>
                            </MobileCard>
                        );
                    }}
                />
            )}
        </>
    );
}
