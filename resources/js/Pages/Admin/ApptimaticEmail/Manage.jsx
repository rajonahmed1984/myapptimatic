import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';
import axios from '../../../http-client';

export default function Manage({
    pageTitle = 'Apptimatic Email Settings',
    initialAccounts = [],
    routes = {},
    serverSettings = {},
    phpImapEnabled = true,
}) {
    const [accounts, setAccounts] = useState(Array.isArray(initialAccounts) ? initialAccounts : []);
    const [accountQuery, setAccountQuery] = useState('');
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [busy, setBusy] = useState(false);

    // Central SMTP & Mail Server Form
    const [serverForm, setServerForm] = useState({
        smtp_host: serverSettings?.smtp_host || '',
        smtp_port: serverSettings?.smtp_port || 587,
        smtp_encryption: serverSettings?.smtp_encryption || 'tls',
        imap_host: serverSettings?.imap_host || '',
        imap_port: serverSettings?.imap_port || 993,
        imap_encryption: serverSettings?.imap_encryption || 'ssl',
        domain: serverSettings?.domain || '',
        auto_provision: Boolean(serverSettings?.auto_provision ?? true),
        validate_cert: Boolean(serverSettings?.validate_cert ?? true),
    });
    const [serverSaving, setServerSaving] = useState(false);
    const [testingConnection, setTestingConnection] = useState(false);
    const [testResult, setTestResult] = useState(null);

    useEffect(() => {
        if (!error) return;
        const timeoutId = window.setTimeout(() => setError(''), 6000);
        return () => window.clearTimeout(timeoutId);
    }, [error]);

    useEffect(() => {
        if (!success) return;
        const timeoutId = window.setTimeout(() => setSuccess(''), 5000);
        return () => window.clearTimeout(timeoutId);
    }, [success]);

    const accountsBase = routes?.accounts_base || '';

    const filteredAccounts = useMemo(() => {
        const query = accountQuery.trim().toLowerCase();
        if (query === '') return accounts;
        return accounts.filter((account) => {
            const haystack = `${account?.display_name || ''} ${account?.email || ''}`.toLowerCase();
            return haystack.includes(query);
        });
    }, [accounts, accountQuery]);

    const encryptionOptions = [
        { value: 'tls', label: 'TLS (Port 587 - Recommended)' },
        { value: 'ssl', label: 'SSL (Port 465)' },
        { value: 'none', label: 'None (Plain / Port 25)' },
    ];

    const imapEncryptionOptions = [
        { value: 'ssl', label: 'SSL / TLS (Port 993 - Recommended)' },
        { value: 'tls', label: 'STARTTLS (Port 143)' },
        { value: 'none', label: 'None (Port 143)' },
    ];

    const loadAccounts = async () => {
        if (!accountsBase) return;
        try {
            const response = await axios.get(accountsBase);
            setAccounts(Array.isArray(response?.data?.data) ? response.data.data : []);
        } catch {
            // silent catch on background refresh
        }
    };

    // Save Central Server Settings
    const saveServerSettings = async (event) => {
        event.preventDefault();
        setError('');
        setSuccess('');
        setServerSaving(true);
        setTestResult(null);

        try {
            const updateUrl = routes?.settings_update;
            if (!updateUrl) {
                throw new Error('Settings update route is not configured.');
            }

            const response = await axios.put(updateUrl, serverForm);
            setSuccess(response?.data?.message || 'Mail server & SMTP settings saved successfully.');
            if (response?.data?.settings) {
                setServerForm((prev) => ({
                    ...prev,
                    ...response.data.settings,
                }));
            }
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to save settings.';
            setError(message);
        } finally {
            setServerSaving(false);
        }
    };

    // Test Server Connection
    const testServerConnection = async () => {
        setError('');
        setTestingConnection(true);
        setTestResult(null);

        try {
            const testUrl = routes?.settings_test;
            if (!testUrl) {
                throw new Error('Connection test route is not configured.');
            }

            const response = await axios.post(testUrl, serverForm);
            setTestResult(response?.data || null);
            if (response?.data?.success) {
                setSuccess('Connection to SMTP & IMAP mail server successful!');
            }
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to test connection.';
            setError(message);
        } finally {
            setTestingConnection(false);
        }
    };

    // Delete Mailbox
    const deleteMailbox = async (accountId, email) => {
        if (!confirm(`Delete mailbox (${email}) from portal records? Users can reconnect anytime by logging in.`)) {
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await axios.delete(`${accountsBase}/${accountId}`);
            await loadAccounts();
            setSuccess('Mailbox record removed.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to delete mailbox.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={pageTitle} />

            <div className="space-y-6">
                {/* Header Card */}
                <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3.5">
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-teal-600 to-emerald-500 text-white shadow-md">
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900">Apptimatic Email Settings</h1>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Configure central SMTP & Mail Server for all email accounts under your domain.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {routes?.inbox ? (
                                <a
                                    href={routes.inbox}
                                    data-native="true"
                                    className="inline-flex items-center gap-2 rounded-[10px] border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-teal-500 hover:text-teal-600 shadow-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                    Back to Inbox
                                </a>
                            ) : null}
                        </div>
                    </div>
                </div>

                {/* Toast Alerts */}
                {error ? (
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm animate-fade-in">
                        <div className="flex items-start gap-2.5">
                            <svg className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span className="font-medium">{error}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setError('')}
                            className="rounded-lg p-1 text-rose-500 hover:bg-rose-100"
                        >
                            &times;
                        </button>
                    </div>
                ) : null}

                {success ? (
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm animate-fade-in">
                        <div className="flex items-start gap-2.5">
                            <svg className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span className="font-medium">{success}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSuccess('')}
                            className="rounded-lg p-1 text-emerald-500 hover:bg-emerald-100"
                        >
                            &times;
                        </button>
                    </div>
                ) : null}

                {/* PHP IMAP Extension Requirement Notice */}
                {!phpImapEnabled ? (
                    <div className="rounded-2xl border border-amber-300 bg-amber-50/90 p-5 shadow-sm">
                        <div className="flex items-start gap-3.5">
                            <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-sm font-bold text-base">
                                !
                            </div>
                            <div className="text-xs text-amber-950 leading-relaxed">
                                <strong className="font-bold text-sm block mb-1 text-amber-900">
                                    PHP "imap" Extension Missing on Server
                                </strong>
                                To allow users to authenticate and read their incoming mailboxes, the PHP <code>imap</code> module must be enabled on this hosting/server.
                                <div className="mt-2.5 rounded-[10px] border border-amber-200 bg-white/90 p-3 text-[11px] text-slate-800 font-medium">
                                    <span className="font-bold text-teal-800">How to fix in cPanel:</span> Go to <strong>cPanel</strong> &rarr; <strong>Select PHP Version</strong> &rarr; <strong>Extensions</strong> tab &rarr; check <strong>"imap"</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                ) : null}

                {/* Central Mail Server Configuration Card */}
                <form onSubmit={saveServerSettings} className="space-y-6">
                    {/* Guidance Notice */}
                    <div className="rounded-2xl border border-teal-200 bg-gradient-to-r from-teal-50/90 via-white to-emerald-50/70 p-5 shadow-sm">
                        <div className="flex items-start gap-3.5">
                            <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm">
                                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div className="text-xs text-slate-700 leading-relaxed">
                                <strong className="text-teal-900 font-bold text-sm block mb-1">
                                    One-Time Central Mail Server Setup
                                </strong>
                                Configure your SMTP and IMAP settings once below. Any email account associated with this mail server (e.g. cPanel, Webmail, Google Workspace, Microsoft 365, Postfix) can log in to our portal with their corporate email and password. Individual mailboxes are provisioned and synchronized automatically!
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Outgoing Mail Server (SMTP) */}
                        <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-700">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">Outgoing Mail (SMTP)</h2>
                                    <p className="text-[11px] text-slate-500">For sending and replying to emails</p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                        SMTP Host <span className="text-rose-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                        placeholder="mail.apptimatic.com or smtp.gmail.com"
                                        value={serverForm.smtp_host}
                                        onChange={(e) => {
                                            const val = e.target.value;
                                            setServerForm((prev) => ({
                                                ...prev,
                                                smtp_host: val,
                                                imap_host: prev.imap_host === '' || prev.imap_host === prev.smtp_host ? val : prev.imap_host,
                                            }));
                                        }}
                                        required
                                    />
                                    <div className="mt-1 text-[11px] text-slate-400">
                                        Hostname of your SMTP server.
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            SMTP Port
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="65535"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder="587"
                                            value={serverForm.smtp_port}
                                            onChange={(e) => setServerForm((prev) => ({ ...prev, smtp_port: Number(e.target.value || 587) }))}
                                        />
                                        <div className="mt-1 text-[11px] text-slate-400">587 (TLS) or 465 (SSL)</div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            Encryption
                                        </label>
                                        <SearchableSelect
                                            name="smtp_encryption"
                                            value={String(serverForm.smtp_encryption || 'tls')}
                                            onChange={(val) => setServerForm((prev) => ({ ...prev, smtp_encryption: String(val || 'tls') }))}
                                            options={encryptionOptions}
                                            placeholder="Select encryption"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Incoming Mail Server (IMAP) */}
                        <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                            <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 className="text-sm font-bold text-slate-900">Incoming Mail (IMAP)</h2>
                                    <p className="text-[11px] text-slate-500">For reading emails and authenticating login</p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <div className="flex items-center justify-between mb-1.5">
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                            IMAP Host
                                        </label>
                                        {serverForm.smtp_host && serverForm.imap_host !== serverForm.smtp_host && (
                                            <button
                                                type="button"
                                                onClick={() => setServerForm((prev) => ({ ...prev, imap_host: prev.smtp_host }))}
                                                className="text-[11px] text-teal-600 hover:underline font-semibold"
                                            >
                                                Same as SMTP Host
                                            </button>
                                        )}
                                    </div>
                                    <input
                                        type="text"
                                        className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                        placeholder={serverForm.smtp_host || 'mail.apptimatic.com'}
                                        value={serverForm.imap_host}
                                        onChange={(e) => setServerForm((prev) => ({ ...prev, imap_host: e.target.value }))}
                                    />
                                    <div className="mt-1 text-[11px] text-slate-400">
                                        Leave empty to automatically match SMTP Host.
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            IMAP Port
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="65535"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder="993"
                                            value={serverForm.imap_port}
                                            onChange={(e) => setServerForm((prev) => ({ ...prev, imap_port: Number(e.target.value || 993) }))}
                                        />
                                        <div className="mt-1 text-[11px] text-slate-400">993 (SSL) or 143</div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            Encryption
                                        </label>
                                        <SearchableSelect
                                            name="imap_encryption"
                                            value={String(serverForm.imap_encryption || 'ssl')}
                                            onChange={(val) => setServerForm((prev) => ({ ...prev, imap_encryption: String(val || 'ssl') }))}
                                            options={imapEncryptionOptions}
                                            placeholder="Select encryption"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Access & Security Card */}
                    <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                        <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">Domain & Access Settings</h2>
                                <p className="text-[11px] text-slate-500">
                                    Manage domain restrictions and login provisioning
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-6 md:grid-cols-2 items-center">
                            <div>
                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Allowed Mail Domain (Optional)
                                </label>
                                <input
                                    type="text"
                                    className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                    placeholder="apptimatic.com (Leave blank for any domain)"
                                    value={serverForm.domain}
                                    onChange={(e) => setServerForm((prev) => ({ ...prev, domain: e.target.value }))}
                                />
                                <div className="mt-1 text-[11px] text-slate-400">
                                    e.g. <code>apptimatic.com</code>. If specified, only email accounts ending in @this-domain can log in.
                                </div>
                            </div>

                            <div className="space-y-3 pt-2">
                                <label className="flex items-start gap-3 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                        checked={Boolean(serverForm.auto_provision)}
                                        onChange={(e) => setServerForm((prev) => ({ ...prev, auto_provision: e.target.checked }))}
                                    />
                                    <div>
                                        <div className="text-xs font-bold text-slate-800">
                                            Auto-provision mailboxes on login
                                        </div>
                                        <div className="text-[11px] text-slate-500">
                                            Users logging in with valid email credentials on this server will instantly get access without manual admin setup.
                                        </div>
                                    </div>
                                </label>

                                <label className="flex items-start gap-3 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                        checked={Boolean(serverForm.validate_cert)}
                                        onChange={(e) => setServerForm((prev) => ({ ...prev, validate_cert: e.target.checked }))}
                                    />
                                    <div>
                                        <div className="text-xs font-bold text-slate-800">
                                            Validate SSL/TLS certificate
                                        </div>
                                        <div className="text-[11px] text-slate-500">
                                            Uncheck only if your mail server uses an internal self-signed certificate.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {/* Connection Test Diagnostics Result */}
                    {testResult && (
                        <div className={`rounded-2xl border p-5 shadow-sm space-y-3 ${
                            testResult.success ? 'border-emerald-200 bg-emerald-50/70' : 'border-amber-200 bg-amber-50/70'
                        }`}>
                            <div className="flex items-center gap-2">
                                {testResult.success ? (
                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white text-xs font-bold">✓</span>
                                ) : (
                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-white text-xs font-bold">!</span>
                                )}
                                <h3 className="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    Connection Diagnostic Results
                                </h3>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 text-xs">
                                <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                                    <div className="font-bold text-slate-700 flex items-center justify-between">
                                        <span>SMTP ({serverForm.smtp_host}:{serverForm.smtp_port})</span>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                            testResult?.results?.smtp?.success ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                                        }`}>
                                            {testResult?.results?.smtp?.success ? 'Connected' : 'Failed'}
                                        </span>
                                    </div>
                                    <div className="mt-1 text-slate-500 text-[11px]">
                                        {testResult?.results?.smtp?.message}
                                    </div>
                                </div>

                                <div className="rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-xs">
                                    <div className="font-bold text-slate-700 flex items-center justify-between">
                                        <span>IMAP ({serverForm.imap_host || serverForm.smtp_host}:{serverForm.imap_port})</span>
                                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                            testResult?.results?.imap?.success ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'
                                        }`}>
                                            {testResult?.results?.imap?.success ? 'Connected' : 'Failed'}
                                        </span>
                                    </div>
                                    <div className="mt-1 text-slate-500 text-[11px]">
                                        {testResult?.results?.imap?.message}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Actions Bar */}
                    <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                        <button
                            type="button"
                            onClick={testServerConnection}
                            disabled={testingConnection || serverSaving || !serverForm.smtp_host}
                            className="inline-flex items-center gap-2 rounded-[10px] border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:border-teal-500 hover:text-teal-600 disabled:opacity-50 transition shadow-sm"
                        >
                            {testingConnection ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Testing Connection...
                                </>
                            ) : (
                                <>
                                    <svg className="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Test Server Connection
                                </>
                            )}
                        </button>

                        <button
                            type="submit"
                            disabled={serverSaving || testingConnection}
                            className="inline-flex items-center gap-2 rounded-[10px] bg-gradient-to-r from-teal-600 to-emerald-600 px-6 py-2.5 text-xs font-bold text-white shadow-md hover:from-teal-700 hover:to-emerald-700 disabled:opacity-60 transition"
                        >
                            {serverSaving ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Saving Settings...
                                </>
                            ) : (
                                <>
                                    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Save Server Settings
                                </>
                            )}
                        </button>
                    </div>
                </form>

                {/* Section 2: Connected User Accounts List */}
                <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-slate-100">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-700">
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 className="text-sm font-bold text-slate-900">Connected User Mailboxes</h2>
                                <p className="text-[11px] text-slate-500">
                                    Email accounts that have logged in to this portal using the server credentials
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <span className="rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">
                                {accounts.length} Connected
                            </span>
                            <input
                                className="rounded-[10px] border border-slate-300 px-3.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 w-full sm:w-56"
                                placeholder="Search connected email..."
                                value={accountQuery}
                                onChange={(e) => setAccountQuery(e.target.value)}
                            />
                        </div>
                    </div>

                    {filteredAccounts.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center text-xs text-slate-500">
                            {accounts.length === 0
                                ? 'No mailboxes connected yet. Once your SMTP settings above are saved, any user can log in with their email address and password.'
                                : 'No connected mailbox matches your search.'}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs text-slate-600">
                                <thead className="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="py-3 px-4">Email Address</th>
                                        <th className="py-3 px-4">Name / Label</th>
                                        <th className="py-3 px-4">Status</th>
                                        <th className="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredAccounts.map((account) => (
                                        <tr key={account.id} className="hover:bg-slate-50/50 transition">
                                            <td className="py-3 px-4 font-semibold text-slate-900">
                                                <div className="flex items-center gap-2">
                                                    <span className={`h-2 w-2 rounded-full ${
                                                        account.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500'
                                                    }`} />
                                                    <span>{account.email}</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-slate-600">
                                                {account.display_name || '—'}
                                            </td>
                                            <td className="py-3 px-4">
                                                <span className={`inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold ${
                                                    account.status === 'active'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-rose-100 text-rose-800'
                                                }`}>
                                                    {account.status === 'active' ? 'Active' : 'Auth Failed'}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-right">
                                                <button
                                                    type="button"
                                                    disabled={busy}
                                                    onClick={() => deleteMailbox(account.id, account.email)}
                                                    className="rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-100 hover:border-rose-300 disabled:opacity-50 transition"
                                                    title="Remove this mailbox record"
                                                >
                                                    Disconnect
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
