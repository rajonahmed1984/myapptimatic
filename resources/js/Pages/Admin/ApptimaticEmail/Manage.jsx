import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';
import axios from '../../../http-client';

const DEFAULT_MAILBOX_FORM = {
    email: '',
    display_name: '',
    imap_host: '',
    imap_port: 993,
    imap_encryption: 'ssl',
    imap_validate_cert: true,
    status: 'active',
};

const DEFAULT_ASSIGNMENT_FORM = {
    assignee_type: 'support',
    assignee_id: '',
    can_read: true,
    can_manage: false,
};

export default function Manage({
    pageTitle = 'Apptimatic Email Settings',
    initialAccounts = [],
    assignees = {},
    routes = {},
    serverSettings = {},
}) {
    const [activeTab, setActiveTab] = useState('server');
    const [accounts, setAccounts] = useState(Array.isArray(initialAccounts) ? initialAccounts : []);
    const [selectedAccountId, setSelectedAccountId] = useState(initialAccounts?.[0]?.id ?? null);
    const [mailboxForm, setMailboxForm] = useState(DEFAULT_MAILBOX_FORM);
    const [assignmentForm, setAssignmentForm] = useState(DEFAULT_ASSIGNMENT_FORM);
    const [editingMailboxId, setEditingMailboxId] = useState(null);
    const [mailboxQuery, setMailboxQuery] = useState('');
    const [assignmentQuery, setAssignmentQuery] = useState('');
    const [selectedAssignmentIds, setSelectedAssignmentIds] = useState([]);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [busy, setBusy] = useState(false);

    // Global SMTP & Mail Server Configuration Form
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

    const selectedAccount = useMemo(() => {
        return accounts.find((item) => item.id === selectedAccountId) || null;
    }, [accounts, selectedAccountId]);

    const filteredAccounts = useMemo(() => {
        const query = mailboxQuery.trim().toLowerCase();
        if (query === '') return accounts;
        return accounts.filter((account) => {
            const haystack = `${account?.display_name || ''} ${account?.email || ''}`.toLowerCase();
            return haystack.includes(query);
        });
    }, [accounts, mailboxQuery]);

    const assigneeOptions = useMemo(() => {
        const type = assignmentForm.assignee_type;
        const options = assignees?.[type];
        return Array.isArray(options) ? options : [];
    }, [assignees, assignmentForm.assignee_type]);

    const assignmentLabel = (assignment) => {
        const options = Array.isArray(assignees?.[assignment.assignee_type]) ? assignees[assignment.assignee_type] : [];
        const match = options.find((option) => Number(option.id) === Number(assignment.assignee_id));
        return match?.label || `${assignment.assignee_type} #${assignment.assignee_id}`;
    };

    const filteredAssignments = useMemo(() => {
        const source = Array.isArray(selectedAccount?.assignments) ? selectedAccount.assignments : [];
        const query = assignmentQuery.trim().toLowerCase();
        if (query === '') return source;
        return source.filter((assignment) => {
            const label = assignmentLabel(assignment).toLowerCase();
            const meta = `${assignment?.assignee_type || ''} ${assignment?.assignee_id || ''}`.toLowerCase();
            return label.includes(query) || meta.includes(query);
        });
    }, [assignmentQuery, selectedAccount, assignees]);

    const encryptionOptions = [
        { value: 'tls', label: 'TLS (Recommended for SMTP: 587)' },
        { value: 'ssl', label: 'SSL (Recommended for IMAP: 993, SMTP: 465)' },
        { value: 'none', label: 'None (Plain / Unencrypted)' },
    ];

    const imapEncryptionOptions = [
        { value: 'ssl', label: 'SSL / TLS (Port 993 - Default)' },
        { value: 'tls', label: 'STARTTLS (Port 143)' },
        { value: 'none', label: 'None / Plain (Port 143)' },
    ];

    const mailboxStatusOptions = [
        { value: 'active', label: 'Active' },
        { value: 'auth_failed', label: 'Auth failed' },
        { value: 'disabled', label: 'Disabled' },
    ];

    const assigneeTypeOptions = [
        { value: 'support', label: 'Support' },
        { value: 'user', label: 'Admin user' },
        { value: 'employee', label: 'Employee' },
        { value: 'sales_rep', label: 'Sales rep' },
    ];

    const assigneeSelectOptions = [
        { value: '', label: 'Select assignee' },
        ...assigneeOptions.map((option) => ({ value: String(option.id), label: option.label })),
    ];

    useEffect(() => {
        setSelectedAssignmentIds([]);
    }, [selectedAccountId]);

    const loadAccounts = async () => {
        if (!accountsBase) return;
        const response = await axios.get(accountsBase);
        const nextAccounts = Array.isArray(response?.data?.data) ? response.data.data : [];
        setAccounts(nextAccounts);
        if (!nextAccounts.some((item) => item.id === selectedAccountId)) {
            setSelectedAccountId(nextAccounts[0]?.id ?? null);
        }
    };

    const resetMailboxForm = () => {
        setMailboxForm(DEFAULT_MAILBOX_FORM);
        setEditingMailboxId(null);
    };

    const mapAccountToMailboxForm = (account) => ({
        email: account?.email || '',
        display_name: account?.display_name || '',
        imap_host: account?.imap_host || '',
        imap_port: account?.imap_port || 993,
        imap_encryption: account?.imap_encryption || 'ssl',
        imap_validate_cert: Boolean(account?.imap_validate_cert),
        status: account?.status || 'active',
    });

    const startEditMailbox = (account) => {
        if (!account) return;
        setSelectedAccountId(account.id);
        setEditingMailboxId(account.id);
        setMailboxForm(mapAccountToMailboxForm(account));
        setActiveTab('mailboxes');
    };

    const openMailboxConfiguration = (account) => {
        if (!account) return;
        startEditMailbox(account);
    };

    // Save Global Server & SMTP Settings
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

    // Mailbox CRUD
    const saveMailbox = async (event) => {
        event.preventDefault();
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            if (!accountsBase) throw new Error('Accounts route is not configured.');
            if (editingMailboxId) {
                await axios.put(`${accountsBase}/${editingMailboxId}`, mailboxForm);
            } else {
                await axios.post(accountsBase, mailboxForm);
            }

            await loadAccounts();
            resetMailboxForm();
            setSuccess(editingMailboxId ? 'Mailbox updated successfully.' : 'Mailbox created successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to save mailbox.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const deleteMailbox = async (accountId) => {
        if (!confirm('Delete this mailbox and all related assignments/sessions?')) return;
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await axios.delete(`${accountsBase}/${accountId}`);
            await loadAccounts();
            if (Number(selectedAccountId) === Number(accountId)) {
                setSelectedAccountId(null);
            }
            setSuccess('Mailbox deleted successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to delete mailbox.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const saveAssignment = async (event) => {
        event.preventDefault();
        if (!selectedAccount?.id) {
            setError('Select a mailbox first.');
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await axios.post(`${accountsBase}/${selectedAccount.id}/assignments`, assignmentForm);
            await loadAccounts();
            setAssignmentForm(DEFAULT_ASSIGNMENT_FORM);
            setSuccess('Assignment saved successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to save assignment.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const toggleAssignmentManage = async (assignment) => {
        if (!selectedAccount?.id || !assignment?.id) return;
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await axios.put(`${accountsBase}/${selectedAccount.id}/assignments/${assignment.id}`, {
                can_read: Boolean(assignment.can_read),
                can_manage: !assignment.can_manage,
            });
            await loadAccounts();
            setSuccess('Assignment updated successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to update assignment.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const deleteAssignment = async (assignmentId) => {
        if (!selectedAccount?.id || !confirm('Remove this assignment?')) return;
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await axios.delete(`${accountsBase}/${selectedAccount.id}/assignments/${assignmentId}`);
            await loadAccounts();
            setSuccess('Assignment removed successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to remove assignment.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const toggleAssignmentSelection = (assignmentId) => {
        const id = Number(assignmentId);
        setSelectedAssignmentIds((prev) => prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]);
    };

    const toggleSelectAllVisibleAssignments = () => {
        const visibleIds = filteredAssignments.map((item) => Number(item.id)).filter(Boolean);
        if (visibleIds.length === 0) return;
        setSelectedAssignmentIds((prev) => {
            const allSelected = visibleIds.every((id) => prev.includes(id));
            if (allSelected) {
                return prev.filter((id) => !visibleIds.includes(id));
            }
            return Array.from(new Set([...prev, ...visibleIds]));
        });
    };

    const bulkDeleteSelectedAssignments = async () => {
        if (!selectedAccount?.id || selectedAssignmentIds.length === 0) return;
        if (!confirm(`Remove ${selectedAssignmentIds.length} selected assignment(s)?`)) return;

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await Promise.all(
                selectedAssignmentIds.map((assignmentId) =>
                    axios.delete(`${accountsBase}/${selectedAccount.id}/assignments/${assignmentId}`)
                )
            );
            await loadAccounts();
            setSelectedAssignmentIds([]);
            setSuccess('Selected assignments revoked successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to remove selected assignments.';
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
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-slate-900">Email Portal Configuration</h1>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Configure central SMTP & Mail Server or manage individual mailboxes and permissions.
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
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Back to Inbox
                                </a>
                            ) : null}
                        </div>
                    </div>

                    {/* Navigation Tabs */}
                    <div className="mt-6 flex border-b border-slate-200">
                        <button
                            type="button"
                            onClick={() => setActiveTab('server')}
                            className={`relative -mb-px flex items-center gap-2 py-3 px-4 text-xs font-bold transition border-b-2 ${
                                activeTab === 'server'
                                    ? 'border-teal-600 text-teal-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                            Global SMTP & Mail Server
                            <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-700">Central</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('mailboxes')}
                            className={`relative -mb-px flex items-center gap-2 py-3 px-4 text-xs font-bold transition border-b-2 ${
                                activeTab === 'mailboxes'
                                    ? 'border-teal-600 text-teal-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-800'
                            }`}
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Individual Mailboxes & Permissions
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                {accounts.length}
                            </span>
                        </button>
                    </div>
                </div>

                {/* Alerts */}
                {error ? (
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm animate-fade-in">
                        <div className="flex items-start gap-2.5">
                            <svg className="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                {/* TAB 1: GLOBAL SMTP & MAIL SERVER CONFIGURATION */}
                {activeTab === 'server' && (
                    <div className="space-y-6">
                        {/* Informative Guidance Banner */}
                        <div className="rounded-2xl border border-teal-200 bg-gradient-to-r from-teal-50/80 via-white to-emerald-50/60 p-5 shadow-sm">
                            <div className="flex items-start gap-3.5">
                                <div className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm">
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div className="text-xs text-slate-700 leading-relaxed">
                                    <strong className="text-teal-900 font-bold text-sm block mb-1">
                                        One-Time Mail Server Setup for All Email Accounts
                                    </strong>
                                    Configure your SMTP & IMAP mail server details here. Once saved, <strong>any email account</strong> on this mail server (e.g. cPanel, Webmail, Google Workspace, Microsoft 365, Postfix) can log in to our portal using their corporate email address and mail password. Mailboxes, inbox syncing, and sending credentials are handled automatically without needing manual mailbox creation!
                                </div>
                            </div>
                        </div>

                        <form onSubmit={saveServerSettings} className="space-y-6">
                            <div className="grid gap-6 lg:grid-cols-2">
                                {/* SMTP Section */}
                                <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                                    <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-700">
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 className="text-sm font-bold text-slate-900">Outgoing Mail Server (SMTP)</h2>
                                            <p className="text-[11px] text-slate-500">Used for composing, replying, and sending emails</p>
                                        </div>
                                    </div>

                                    <div className="space-y-3.5">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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
                                                The hostname of your SMTP mail server.
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-3">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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
                                                <div className="mt-1 text-[11px] text-slate-400">Default: 587 (TLS) or 465 (SSL)</div>
                                            </div>

                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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

                                {/* IMAP Section */}
                                <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                                    <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h2 className="text-sm font-bold text-slate-900">Incoming Mail Server (IMAP)</h2>
                                            <p className="text-[11px] text-slate-500">Used for receiving emails and verifying credentials</p>
                                        </div>
                                    </div>

                                    <div className="space-y-3.5">
                                        <div>
                                            <div className="flex items-center justify-between mb-1">
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
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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
                                                <div className="mt-1 text-[11px] text-slate-400">Default: 993 (SSL) or 143</div>
                                            </div>

                                            <div>
                                                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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

                            {/* Access Control & Auto Provisioning Card */}
                            <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                                <div className="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                        <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 className="text-sm font-bold text-slate-900">Access Control & Auto-Provisioning</h2>
                                        <p className="text-[11px] text-slate-500">
                                            Control which email accounts can log in and enable automatic mailbox creation
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2 items-center">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
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
                                            e.g. <code>apptimatic.com</code>. If specified, only email addresses ending in @this-domain will be allowed to log in.
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
                                                    Auto-provision mailboxes on user login
                                                </div>
                                                <div className="text-[11px] text-slate-500">
                                                    When enabled, users with valid email credentials on this server will instantly get a mailbox provisioned upon first login.
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
                                                    Uncheck only if you use a self-signed mail server certificate.
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
                                            Connection Test Results
                                        </h3>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2 text-xs">
                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs">
                                            <div className="font-bold text-slate-700 flex items-center justify-between">
                                                <span>SMTP Server ({serverForm.smtp_host}:{serverForm.smtp_port})</span>
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

                                        <div className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs">
                                            <div className="font-bold text-slate-700 flex items-center justify-between">
                                                <span>IMAP Server ({serverForm.imap_host || serverForm.smtp_host}:{serverForm.imap_port})</span>
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
                            <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
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
                                            Testing Connectivity...
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
                                            Save Settings
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* TAB 2: INDIVIDUAL MAILBOXES & PERMISSIONS */}
                {activeTab === 'mailboxes' && (
                    <div className="grid gap-6 xl:grid-cols-[minmax(18rem,26rem)_1fr]">
                        {/* Mailboxes Sidebar */}
                        <div className="card p-5 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                            <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                                <div className="flex items-center gap-2">
                                    <h2 className="text-sm font-bold text-slate-900">Provisioned Mailboxes</h2>
                                    <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-700">
                                        {accounts.length}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    onClick={resetMailboxForm}
                                    className="inline-flex items-center gap-1 rounded-[10px] border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-teal-500 hover:text-teal-600"
                                >
                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    New
                                </button>
                            </div>

                            <div>
                                <input
                                    className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                    placeholder="Search mailbox by name or email..."
                                    value={mailboxQuery}
                                    onChange={(event) => setMailboxQuery(event.target.value)}
                                />
                            </div>

                            <div className="space-y-2 max-h-[600px] overflow-y-auto pr-1">
                                {filteredAccounts.length === 0 ? (
                                    <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                                        No mailbox found matching your query.
                                    </div>
                                ) : (
                                    filteredAccounts.map((account) => {
                                        const isSelected = Number(selectedAccountId) === Number(account.id);
                                        return (
                                            <div
                                                key={account.id}
                                                className={`w-full rounded-xl border p-3.5 text-left transition cursor-pointer ${
                                                    isSelected
                                                        ? 'border-teal-400 bg-teal-50/60 shadow-xs ring-1 ring-teal-400'
                                                        : 'border-slate-200/80 bg-white hover:border-slate-300 hover:bg-slate-50/50'
                                                }`}
                                                onClick={() => openMailboxConfiguration(account)}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <div className="font-bold text-xs text-slate-900 truncate">
                                                        {account.display_name || account.email}
                                                    </div>
                                                    <span className={`inline-block h-2 w-2 rounded-full ${
                                                        account.status === 'active' ? 'bg-emerald-500' : 'bg-rose-500'
                                                    }`} />
                                                </div>
                                                <div className="mt-1 max-w-full truncate text-[11px] font-semibold text-teal-700">
                                                    {account.email}
                                                </div>
                                                <div className="mt-1 flex items-center justify-between text-[10px] text-slate-400">
                                                    <span>{account?.assignments?.length || 0} assigned user(s)</span>
                                                    <span>{account.imap_host || 'Server default'}</span>
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                        </div>

                        {/* Mailbox Form & Assignments */}
                        <div className="space-y-6">
                            {/* Create / Edit Form */}
                            <form onSubmit={saveMailbox} className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                                <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                                    <h2 className="text-sm font-bold text-slate-900">
                                        {editingMailboxId ? 'Edit Mailbox Details' : 'Add Individual Mailbox Override'}
                                    </h2>
                                    {editingMailboxId && (
                                        <button
                                            type="button"
                                            onClick={resetMailboxForm}
                                            className="text-xs text-slate-500 hover:text-slate-800"
                                        >
                                            Cancel Editing
                                        </button>
                                    )}
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            Email Address <span className="text-rose-500">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder="youremail@apptimatic.com"
                                            value={mailboxForm.email}
                                            onChange={(e) => setMailboxForm((prev) => ({ ...prev, email: e.target.value }))}
                                            required
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            Display Name
                                        </label>
                                        <input
                                            type="text"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder="Support Inbox"
                                            value={mailboxForm.display_name}
                                            onChange={(e) => setMailboxForm((prev) => ({ ...prev, display_name: e.target.value }))}
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            IMAP Host (Optional Override)
                                        </label>
                                        <input
                                            type="text"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder={serverForm.imap_host || serverForm.smtp_host || 'mail.apptimatic.com'}
                                            value={mailboxForm.imap_host}
                                            onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_host: e.target.value }))}
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            IMAP Port
                                        </label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="65535"
                                            className="w-full rounded-[10px] border border-slate-300 px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                            placeholder="993"
                                            value={mailboxForm.imap_port}
                                            onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_port: Number(e.target.value || 993) }))}
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            Encryption
                                        </label>
                                        <SearchableSelect
                                            name="imap_encryption"
                                            value={String(mailboxForm.imap_encryption || 'ssl')}
                                            onChange={(val) => setMailboxForm((prev) => ({ ...prev, imap_encryption: String(val || 'ssl') }))}
                                            options={imapEncryptionOptions}
                                            placeholder="Select encryption"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                            Status
                                        </label>
                                        <SearchableSelect
                                            name="mailbox_status"
                                            value={String(mailboxForm.status || 'active')}
                                            onChange={(val) => setMailboxForm((prev) => ({ ...prev, status: String(val || 'active') }))}
                                            options={mailboxStatusOptions}
                                            placeholder="Select status"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between pt-2">
                                    <label className="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                            checked={Boolean(mailboxForm.imap_validate_cert)}
                                            onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_validate_cert: e.target.checked }))}
                                        />
                                        Validate SSL/TLS Certificate
                                    </label>

                                    <div className="flex items-center gap-2">
                                        {editingMailboxId && (
                                            <button
                                                type="button"
                                                onClick={resetMailboxForm}
                                                className="rounded-[10px] border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                            >
                                                Cancel
                                            </button>
                                        )}
                                        <button
                                            type="submit"
                                            disabled={busy}
                                            className="rounded-[10px] bg-teal-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-teal-700 disabled:opacity-60 transition"
                                        >
                                            {editingMailboxId ? 'Update Mailbox' : 'Save Mailbox'}
                                        </button>
                                    </div>
                                </div>
                            </form>

                            {/* Assignments Section */}
                            <div className="card p-6 shadow-sm border border-slate-200/80 bg-white rounded-2xl space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-slate-100">
                                    <div>
                                        <h2 className="text-sm font-bold text-slate-900">Mailbox Access Assignments</h2>
                                        <p className="text-[11px] text-slate-500">
                                            Assign shared access to specific portal roles or staff
                                        </p>
                                    </div>
                                    {selectedAccount && (
                                        <div className="inline-flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() => startEditMailbox(selectedAccount)}
                                                className="rounded-[10px] border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                            >
                                                Edit Mailbox
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => deleteMailbox(selectedAccount.id)}
                                                className="rounded-[10px] border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition"
                                            >
                                                Delete Mailbox
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {!selectedAccount ? (
                                    <div className="rounded-xl border border-dashed border-slate-200 p-8 text-center text-xs text-slate-500">
                                        Select a mailbox from the left list to manage user access assignments.
                                    </div>
                                ) : (
                                    <>
                                        <div className="rounded-xl border border-teal-200 bg-teal-50/50 px-4 py-2.5 text-xs text-teal-900 font-semibold flex items-center justify-between">
                                            <span>Managing access for: <strong>{selectedAccount.display_name || selectedAccount.email}</strong></span>
                                            <span className="text-[11px] text-teal-700">{selectedAccount.email}</span>
                                        </div>

                                        {/* New Assignment Form */}
                                        <form onSubmit={saveAssignment} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                                            <div className="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                                Add User / Assignee Access
                                            </div>
                                            <div className="grid gap-3 md:grid-cols-2">
                                                <SearchableSelect
                                                    name="assignee_type"
                                                    value={assignmentForm.assignee_type}
                                                    onChange={(nextVal) => setAssignmentForm((prev) => ({ ...prev, assignee_type: String(nextVal || 'support'), assignee_id: '' }))}
                                                    options={assigneeTypeOptions}
                                                    placeholder="Select type"
                                                />
                                                <SearchableSelect
                                                    name="assignee_id"
                                                    value={assignmentForm.assignee_id}
                                                    onChange={(nextVal) => setAssignmentForm((prev) => ({ ...prev, assignee_id: String(nextVal || '') }))}
                                                    options={assigneeSelectOptions}
                                                    placeholder="Select assignee"
                                                />
                                            </div>
                                            <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
                                                <div className="flex items-center gap-4">
                                                    <label className="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                            checked={Boolean(assignmentForm.can_read)}
                                                            onChange={(e) => setAssignmentForm((prev) => ({ ...prev, can_read: e.target.checked }))}
                                                        />
                                                        Can Read
                                                    </label>
                                                    <label className="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                            checked={Boolean(assignmentForm.can_manage)}
                                                            onChange={(e) => setAssignmentForm((prev) => ({ ...prev, can_manage: e.target.checked }))}
                                                        />
                                                        Can Manage
                                                    </label>
                                                </div>

                                                <button
                                                    type="submit"
                                                    disabled={busy || !assignmentForm.assignee_id}
                                                    className="rounded-[10px] bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 disabled:opacity-50 transition"
                                                >
                                                    Grant Assignment
                                                </button>
                                            </div>
                                        </form>

                                        {/* Assignments List */}
                                        <div className="space-y-3 pt-2">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <input
                                                    className="rounded-[10px] border border-slate-300 px-3.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-1 focus:ring-teal-500 w-full sm:w-64"
                                                    placeholder="Filter assignments..."
                                                    value={assignmentQuery}
                                                    onChange={(event) => setAssignmentQuery(event.target.value)}
                                                />
                                                <div className="flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={toggleSelectAllVisibleAssignments}
                                                        className="rounded-[10px] border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                                                    >
                                                        Toggle Visible
                                                    </button>
                                                    {selectedAssignmentIds.length > 0 && (
                                                        <button
                                                            type="button"
                                                            onClick={bulkDeleteSelectedAssignments}
                                                            className="rounded-[10px] border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                                        >
                                                            Revoke Selected ({selectedAssignmentIds.length})
                                                        </button>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                {filteredAssignments.length === 0 ? (
                                                    <div className="rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-500">
                                                        No assignments for this mailbox.
                                                    </div>
                                                ) : (
                                                    filteredAssignments.map((assignment) => (
                                                        <div
                                                            key={assignment.id}
                                                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3 text-xs bg-white hover:border-slate-300 transition"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                <input
                                                                    type="checkbox"
                                                                    className="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                                    checked={selectedAssignmentIds.includes(Number(assignment.id))}
                                                                    onChange={() => toggleAssignmentSelection(assignment.id)}
                                                                />
                                                                <div>
                                                                    <div className="font-bold text-slate-800">
                                                                        {assignmentLabel(assignment)}
                                                                    </div>
                                                                    <div className="text-[11px] text-slate-500">
                                                                        Type: <span className="capitalize">{assignment.assignee_type}</span> | Read: {assignment.can_read ? 'Yes' : 'No'} | Manage: {assignment.can_manage ? 'Yes' : 'No'}
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div className="flex items-center gap-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() => toggleAssignmentManage(assignment)}
                                                                    className="rounded-[8px] border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50 transition"
                                                                >
                                                                    {assignment.can_manage ? 'Revoke Manage' : 'Grant Manage'}
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() => deleteAssignment(assignment.id)}
                                                                    className="rounded-[8px] border border-rose-300 px-2.5 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50 transition"
                                                                >
                                                                    Remove
                                                                </button>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}
