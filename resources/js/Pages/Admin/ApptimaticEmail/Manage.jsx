import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

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
}) {
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

    useEffect(() => {
        if (!error) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setError('');
        }, 5000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [error]);

    useEffect(() => {
        if (!success) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setSuccess('');
        }, 4000);

        return () => {
            window.clearTimeout(timeoutId);
        };
    }, [success]);

    const accountsBase = routes?.accounts_base || '';

    const selectedAccount = useMemo(() => {
        return accounts.find((item) => item.id === selectedAccountId) || null;
    }, [accounts, selectedAccountId]);

    const filteredAccounts = useMemo(() => {
        const query = mailboxQuery.trim().toLowerCase();
        if (query === '') {
            return accounts;
        }

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

        if (query === '') {
            return source;
        }

        return source.filter((assignment) => {
            const label = assignmentLabel(assignment).toLowerCase();
            const meta = `${assignment?.assignee_type || ''} ${assignment?.assignee_id || ''}`.toLowerCase();

            return label.includes(query) || meta.includes(query);
        });
    }, [assignmentQuery, selectedAccount, assignees]);

    useEffect(() => {
        setSelectedAssignmentIds([]);
    }, [selectedAccountId]);

    const loadAccounts = async () => {
        if (!accountsBase) {
            return;
        }

        const response = await window.axios.get(accountsBase);
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

    const startEditMailbox = (account) => {
        setEditingMailboxId(account.id);
        setMailboxForm({
            email: account.email || '',
            display_name: account.display_name || '',
            imap_host: account.imap_host || '',
            imap_port: account.imap_port || 993,
            imap_encryption: account.imap_encryption || 'ssl',
            imap_validate_cert: Boolean(account.imap_validate_cert),
            status: account.status || 'active',
        });
    };

    const openMailboxConfiguration = (account) => {
        if (!account) {
            return;
        }

        setSelectedAccountId(account.id);
        startEditMailbox(account);
    };

    const saveMailbox = async (event) => {
        event.preventDefault();
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            if (!accountsBase) {
                throw new Error('Accounts route is not configured.');
            }

            if (editingMailboxId) {
                await window.axios.put(`${accountsBase}/${editingMailboxId}`, mailboxForm);
            } else {
                await window.axios.post(accountsBase, mailboxForm);
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
        if (!confirm('Delete this mailbox and all related assignments/sessions?')) {
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await window.axios.delete(`${accountsBase}/${accountId}`);
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
            setSuccess('');
            return;
        }

        if (!assignmentForm.assignee_id) {
            setError('Choose an assignee.');
            setSuccess('');
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await window.axios.post(`${accountsBase}/${selectedAccount.id}/assignments`, {
                ...assignmentForm,
                assignee_id: Number(assignmentForm.assignee_id),
            });

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
        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await window.axios.put(`${accountsBase}/${selectedAccount.id}/assignments/${assignment.id}`, {
                can_read: Boolean(assignment.can_read),
                can_manage: !Boolean(assignment.can_manage),
            });

            await loadAccounts();
            setSuccess('Assignment permissions updated.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to update assignment.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const deleteAssignment = async (assignmentId) => {
        if (!selectedAccount?.id) {
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await window.axios.delete(`${accountsBase}/${selectedAccount.id}/assignments/${assignmentId}`);
            await loadAccounts();
            setSelectedAssignmentIds((prev) => prev.filter((id) => Number(id) !== Number(assignmentId)));
            setSuccess('Assignment removed successfully.');
        } catch (requestError) {
            const message = requestError?.response?.data?.message || requestError?.message || 'Failed to delete assignment.';
            setError(message);
        } finally {
            setBusy(false);
        }
    };

    const toggleAssignmentSelection = (assignmentId) => {
        const id = Number(assignmentId);

        setSelectedAssignmentIds((prev) => {
            if (prev.includes(id)) {
                return prev.filter((item) => item !== id);
            }

            return [...prev, id];
        });
    };

    const toggleSelectAllVisibleAssignments = () => {
        const visibleIds = filteredAssignments.map((assignment) => Number(assignment.id));
        const allSelected = visibleIds.length > 0 && visibleIds.every((id) => selectedAssignmentIds.includes(id));

        setSelectedAssignmentIds((prev) => {
            if (allSelected) {
                return prev.filter((id) => !visibleIds.includes(id));
            }

            return Array.from(new Set([...prev, ...visibleIds]));
        });
    };

    const bulkDeleteSelectedAssignments = async () => {
        if (!selectedAccount?.id || selectedAssignmentIds.length === 0) {
            return;
        }

        if (!confirm(`Remove ${selectedAssignmentIds.length} selected assignment(s)?`)) {
            return;
        }

        setError('');
        setSuccess('');
        setBusy(true);

        try {
            await Promise.all(
                selectedAssignmentIds.map((assignmentId) =>
                    window.axios.delete(`${accountsBase}/${selectedAccount.id}/assignments/${assignmentId}`)
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

            <div className="space-y-5">
                <div className="card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">Apptimatic Email Settings</h1>
                            <p className="mt-1 text-sm text-slate-500">Manage shared mailboxes and portal assignments.</p>
                        </div>
                        <div className="inline-flex items-center gap-2">
                            {routes?.inbox ? (
                                <a
                                    href={routes.inbox}
                                    data-native="true"
                                    className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                >
                                    Back to Inbox
                                </a>
                            ) : null}
                        </div>
                    </div>
                </div>

                {error ? (
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <div className="flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3Z" />
                            </svg>
                            <span>{error}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setError('')}
                            className="rounded-full px-2 py-0.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                            aria-label="Dismiss error"
                        >
                            x
                        </button>
                    </div>
                ) : null}

                {success ? (
                    <div className="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        <div className="flex items-start gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" className="mt-0.5 h-4 w-4 shrink-0" stroke="currentColor" strokeWidth="2">
                                <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 5 5L20 7" />
                            </svg>
                            <span>{success}</span>
                        </div>
                        <button
                            type="button"
                            onClick={() => setSuccess('')}
                            className="rounded-full px-2 py-0.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                            aria-label="Dismiss success"
                        >
                            x
                        </button>
                    </div>
                ) : null}

                <div className="grid gap-5 xl:grid-cols-[minmax(18rem,26rem)_1fr]">
                    <div className="card p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-slate-800">Mailboxes</h2>
                            <button
                                type="button"
                                onClick={resetMailboxForm}
                                className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600"
                            >
                                New
                            </button>
                        </div>
                        <div className="mb-3">
                            <input
                                className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                placeholder="Search mailbox by name or email"
                                value={mailboxQuery}
                                onChange={(event) => setMailboxQuery(event.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            {filteredAccounts.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-slate-300 px-3 py-5 text-center text-xs text-slate-500">
                                    No mailbox matches your search.
                                </div>
                            ) : (
                                filteredAccounts.map((account) => (
                                    <div
                                        key={account.id}
                                        className={`w-full rounded-xl border px-3 py-2 text-left text-sm ${Number(selectedAccountId) === Number(account.id) ? 'border-teal-300 bg-teal-50 text-teal-900' : 'border-slate-200 bg-white text-slate-700'}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setSelectedAccountId(account.id)}
                                            className="w-full text-left"
                                        >
                                            <div className="font-semibold">{account.display_name || account.email}</div>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => openMailboxConfiguration(account)}
                                            className="mt-0.5 max-w-full truncate text-xs font-medium text-teal-700 transition hover:text-teal-800 hover:underline"
                                            title="Click to load mailbox configuration in the form"
                                        >
                                            {account.email}
                                        </button>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="space-y-5">
                        <form onSubmit={saveMailbox} className="card space-y-4 p-5">
                            <h2 className="text-sm font-semibold text-slate-800">
                                {editingMailboxId ? 'Edit Mailbox' : 'Create Mailbox'}
                            </h2>
                            <div className="grid gap-3 md:grid-cols-2">
                                <input className="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Mailbox email" value={mailboxForm.email} onChange={(e) => setMailboxForm((prev) => ({ ...prev, email: e.target.value }))} required />
                                <input className="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="Display name" value={mailboxForm.display_name} onChange={(e) => setMailboxForm((prev) => ({ ...prev, display_name: e.target.value }))} />
                                <input className="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="IMAP host" value={mailboxForm.imap_host} onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_host: e.target.value }))} />
                                <input className="rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="IMAP port" type="number" min="1" max="65535" value={mailboxForm.imap_port} onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_port: Number(e.target.value || 993) }))} />
                                <select className="rounded-xl border border-slate-300 px-3 py-2 text-sm" value={mailboxForm.imap_encryption} onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_encryption: e.target.value }))}>
                                    <option value="ssl">SSL</option>
                                    <option value="tls">TLS</option>
                                    <option value="none">None</option>
                                </select>
                                <select className="rounded-xl border border-slate-300 px-3 py-2 text-sm" value={mailboxForm.status} onChange={(e) => setMailboxForm((prev) => ({ ...prev, status: e.target.value }))}>
                                    <option value="active">Active</option>
                                    <option value="auth_failed">Auth failed</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                            <label className="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" checked={Boolean(mailboxForm.imap_validate_cert)} onChange={(e) => setMailboxForm((prev) => ({ ...prev, imap_validate_cert: e.target.checked }))} />
                                Validate IMAP certificate
                            </label>
                            <div className="flex gap-2">
                                <button type="submit" disabled={busy} className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">
                                    {editingMailboxId ? 'Update Mailbox' : 'Create Mailbox'}
                                </button>
                                {editingMailboxId ? (
                                    <button type="button" onClick={resetMailboxForm} className="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600">
                                        Cancel
                                    </button>
                                ) : null}
                            </div>
                        </form>

                        <div className="card space-y-4 p-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <h2 className="text-sm font-semibold text-slate-800">Selected Mailbox Assignments</h2>
                                {selectedAccount ? (
                                    <div className="inline-flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() => startEditMailbox(selectedAccount)}
                                            className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600"
                                        >
                                            Edit mailbox
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => deleteMailbox(selectedAccount.id)}
                                            className="rounded-full border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700"
                                        >
                                            Delete mailbox
                                        </button>
                                    </div>
                                ) : null}
                            </div>

                            {!selectedAccount ? (
                                <div className="text-sm text-slate-500">Select a mailbox to manage assignments.</div>
                            ) : (
                                <>
                                    <div className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                        {selectedAccount.display_name || selectedAccount.email}
                                    </div>

                                    <div className="grid gap-2 md:grid-cols-[1fr_auto_auto] md:items-center">
                                        <input
                                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            placeholder="Search assignments"
                                            value={assignmentQuery}
                                            onChange={(event) => setAssignmentQuery(event.target.value)}
                                        />
                                        <button
                                            type="button"
                                            onClick={toggleSelectAllVisibleAssignments}
                                            className="rounded-full border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600"
                                        >
                                            Toggle visible
                                        </button>
                                        <button
                                            type="button"
                                            disabled={busy || selectedAssignmentIds.length === 0}
                                            onClick={bulkDeleteSelectedAssignments}
                                            className="rounded-full border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 disabled:opacity-50"
                                        >
                                            Revoke selected ({selectedAssignmentIds.length})
                                        </button>
                                    </div>

                                    <form onSubmit={saveAssignment} className="grid gap-3 md:grid-cols-2">
                                        <select
                                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            value={assignmentForm.assignee_type}
                                            onChange={(e) => setAssignmentForm((prev) => ({ ...prev, assignee_type: e.target.value, assignee_id: '' }))}
                                        >
                                            <option value="support">Support</option>
                                            <option value="user">Admin user</option>
                                            <option value="employee">Employee</option>
                                            <option value="sales_rep">Sales rep</option>
                                        </select>
                                        <select
                                            className="rounded-xl border border-slate-300 px-3 py-2 text-sm"
                                            value={assignmentForm.assignee_id}
                                            onChange={(e) => setAssignmentForm((prev) => ({ ...prev, assignee_id: e.target.value }))}
                                        >
                                            <option value="">Select assignee</option>
                                            {assigneeOptions.map((option) => (
                                                <option key={option.id} value={option.id}>{option.label}</option>
                                            ))}
                                        </select>
                                        <label className="inline-flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" checked={Boolean(assignmentForm.can_read)} onChange={(e) => setAssignmentForm((prev) => ({ ...prev, can_read: e.target.checked }))} />
                                            Can read
                                        </label>
                                        <label className="inline-flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" checked={Boolean(assignmentForm.can_manage)} onChange={(e) => setAssignmentForm((prev) => ({ ...prev, can_manage: e.target.checked }))} />
                                            Can manage
                                        </label>
                                        <div className="md:col-span-2">
                                            <button type="submit" disabled={busy} className="rounded-full bg-teal-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-60">
                                                Save assignment
                                            </button>
                                        </div>
                                    </form>

                                    <div className="space-y-2">
                                        {filteredAssignments.length === 0 ? (
                                            <div className="rounded-xl border border-dashed border-slate-300 px-3 py-5 text-center text-xs text-slate-500">
                                                No assignments match your search.
                                            </div>
                                        ) : (
                                            filteredAssignments.map((assignment) => (
                                                <div key={assignment.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                                                    <div className="flex min-w-0 items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            className="mt-1"
                                                            checked={selectedAssignmentIds.includes(Number(assignment.id))}
                                                            onChange={() => toggleAssignmentSelection(assignment.id)}
                                                        />
                                                        <div>
                                                            <div className="font-semibold text-slate-800">{assignmentLabel(assignment)}</div>
                                                            <div className="text-xs text-slate-500">{assignment.assignee_type} | Read: {assignment.can_read ? 'Yes' : 'No'} | Manage: {assignment.can_manage ? 'Yes' : 'No'}</div>
                                                        </div>
                                                    </div>
                                                    <div className="inline-flex gap-2">
                                                        <button type="button" onClick={() => toggleAssignmentManage(assignment)} className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600">
                                                            Toggle manage
                                                        </button>
                                                        <button type="button" onClick={() => deleteAssignment(assignment.id)} className="rounded-full border border-rose-300 px-3 py-1 text-xs font-semibold text-rose-700">
                                                            Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
