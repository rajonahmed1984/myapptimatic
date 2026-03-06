import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

const reloadTargets = ['messages', 'selected_message', 'thread_messages', 'unread_count', 'sync_meta', 'history_email_filter', 'mailbox_switch', 'folder_filter'];

const rowClassName = (message) => {
    const base = 'group flex items-start justify-between gap-3 px-4 py-3 transition';

    if (message?.is_selected) {
        return `${base} bg-teal-50/80`;
    }

    return `${base} bg-white hover:bg-slate-50`;
};

const formatAttachmentSize = (bytes) => {
    const size = Number(bytes || 0);
    if (size <= 0) {
        return '';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < (1024 * 1024)) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const parseEmailList = (value) => {
    return String(value || '')
        .split(/[;,]+/)
        .map((part) => {
            const raw = String(part || '').trim();
            const match = raw.match(/<([^>]+)>/);
            const candidate = match ? String(match[1] || '').trim() : raw;

            return candidate.toLowerCase();
        })
        .filter((email) => email !== '' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
};

export default function Inbox({
    pageTitle = 'Apptimatic Email',
    unread_count = 0,
    portal_label = 'Admin portal',
    messages = [],
    selected_message = null,
    thread_messages = [],
    routes = {},
    sync_meta = {},
    history_email_filter = {},
    mailbox_switch = {},
    folder_filter = {},
    mailbox_email = '',
}) {
    const page = usePage();
    const pageErrors = page?.props?.errors || {};
    const flashStatus = page?.props?.flash?.status || '';
    const composeRequested = useMemo(() => {
        const rawUrl = String(page?.url || '');
        const queryString = rawUrl.includes('?') ? rawUrl.slice(rawUrl.indexOf('?') + 1) : '';
        if (queryString === '') {
            return false;
        }

        const composeQuery = String(new URLSearchParams(queryString).get('compose') || '').toLowerCase();
        return composeQuery === '1' || composeQuery === 'true' || composeQuery === 'new';
    }, [page?.url]);
    const isMasterAdmin = Boolean(page?.props?.permissions?.is_master_admin);
    const emailFilterEnabled = Boolean(isMasterAdmin && history_email_filter?.enabled);
    const selectedHistoryEmail = String(history_email_filter?.selected || '');
    const emailFilterOptions = Array.isArray(history_email_filter?.options) ? history_email_filter.options : [];
    const folderOptions = Array.isArray(folder_filter?.options) ? folder_filter.options : [];
    const selectedFolder = String(folder_filter?.selected || 'inbox');
    const selectedFolderLabel = useMemo(() => {
        const current = folderOptions.find((option) => String(option?.key || '') === selectedFolder);
        return String(current?.label || 'Inbox');
    }, [folderOptions, selectedFolder]);
    const mailboxSwitchEnabled = Boolean(isMasterAdmin && mailbox_switch?.enabled);
    const mailboxSwitchOptions = Array.isArray(mailbox_switch?.options) ? mailbox_switch.options : [];
    const mailboxSwitchCurrentEmail = String(mailbox_switch?.current_email || '');
    const firstMailboxSwitchEmail = String(mailboxSwitchOptions[0]?.email || '');
    const [mailboxSwitchEmail, setMailboxSwitchEmail] = useState(mailboxSwitchCurrentEmail || firstMailboxSwitchEmail);
    const activeMailboxEmail = String(mailbox_email || mailboxSwitchCurrentEmail || '').trim().toLowerCase();
    const selectedThreadMessage = thread_messages.find((thread) => Boolean(thread?.is_selected)) || thread_messages[thread_messages.length - 1] || null;
    const previousThreadMessages = selectedThreadMessage
        ? thread_messages.filter((thread) => String(thread?.id || '') !== String(selectedThreadMessage?.id || ''))
        : [];
    const selectedAttachments = Array.isArray(selectedThreadMessage?.attachments) ? selectedThreadMessage.attachments : [];
    const selectedImageAttachments = selectedAttachments.filter((attachment) => String(attachment?.mime || '').startsWith('image/'));
    const selectedFileAttachments = selectedAttachments.filter((attachment) => !String(attachment?.mime || '').startsWith('image/'));
    const hasSelectedMessage = Boolean(selected_message);
    const inboxQuery = useMemo(() => {
        const query = new URLSearchParams();
        if (selectedFolder && selectedFolder !== 'inbox') {
            query.set('folder', selectedFolder);
        }
        if (selectedHistoryEmail) {
            query.set('history_email', selectedHistoryEmail);
        }

        return query.toString();
    }, [selectedFolder, selectedHistoryEmail]);
    const inboxListUrl = routes?.inbox
        ? (inboxQuery ? `${routes.inbox}?${inboxQuery}` : routes.inbox)
        : '#';
    const [composeMode, setComposeMode] = useState('');
    const [composeForm, setComposeForm] = useState({
        to: '',
        cc: '',
        bcc: '',
        subject: '',
        body: '',
    });
    const [actionMenuOpen, setActionMenuOpen] = useState(false);
    const actionMenuRef = useRef(null);
    const [composeSending, setComposeSending] = useState(false);
    const isCurrentMailboxSelected = mailboxSwitchEmail !== '' && mailboxSwitchEmail === mailboxSwitchCurrentEmail;
    const composeIsOpen = composeMode === 'reply' || composeMode === 'reply_all' || composeMode === 'forward' || composeMode === 'new';
    const showDetailsPanel = hasSelectedMessage || composeMode === 'new';
    const listPanelVisibilityClass = showDetailsPanel ? 'hidden lg:block' : 'block';
    const detailsPanelVisibilityClass = showDetailsPanel ? 'block' : 'hidden lg:block';

    const defaultReplyDraft = () => {
        const sender = String(selectedThreadMessage?.sender_email || selected_message?.sender_email || '').trim();
        const rawSubject = String(selected_message?.subject || '').trim();
        const subject = rawSubject.toLowerCase().startsWith('re:') ? rawSubject : `Re: ${rawSubject}`;

        return {
            to: sender,
            cc: '',
            bcc: '',
            subject,
            body: '',
        };
    };

    const defaultReplyAllDraft = () => {
        const sender = String(selectedThreadMessage?.sender_email || selected_message?.sender_email || '').trim().toLowerCase();
        const rawSubject = String(selected_message?.subject || '').trim();
        const subject = rawSubject.toLowerCase().startsWith('re:') ? rawSubject : `Re: ${rawSubject}`;
        const toRecipients = parseEmailList(selectedThreadMessage?.to || selected_message?.to || '');

        const uniqueRecipients = [];
        const seen = new Set();

        [sender, ...toRecipients].forEach((address) => {
            const normalized = String(address || '').trim().toLowerCase();
            if (normalized === '' || seen.has(normalized)) {
                return;
            }

            seen.add(normalized);
            uniqueRecipients.push(normalized);
        });

        const filteredRecipients = uniqueRecipients.filter((address) => {
            if (activeMailboxEmail !== '' && address === activeMailboxEmail) {
                return false;
            }

            return true;
        });

        const to = filteredRecipients[0] || '';
        const cc = filteredRecipients.slice(1).join(', ');

        return {
            to,
            cc,
            bcc: '',
            subject,
            body: '',
        };
    };

    const defaultForwardDraft = () => {
        const senderName = String(selectedThreadMessage?.sender_name || selected_message?.sender_name || '').trim();
        const senderEmail = String(selectedThreadMessage?.sender_email || selected_message?.sender_email || '').trim();
        const to = String(selectedThreadMessage?.to || selected_message?.to || '').trim();
        const receivedAt = String(selectedThreadMessage?.received_at_display || selected_message?.received_at_display || '').trim();
        const rawSubject = String(selected_message?.subject || '').trim();
        const subject = rawSubject.toLowerCase().startsWith('fwd:') ? rawSubject : `Fwd: ${rawSubject}`;
        const body = String(selectedThreadMessage?.body || '').trim();

        return {
            to: '',
            cc: '',
            bcc: '',
            subject,
            body: [
                '',
                '',
                '---------- Forwarded message ----------',
                `From: ${senderName}${senderEmail ? ` <${senderEmail}>` : ''}`,
                to ? `To: ${to}` : '',
                receivedAt ? `Date: ${receivedAt}` : '',
                `Subject: ${rawSubject}`,
                '',
                body,
            ].filter((line) => line !== '').join('\n'),
        };
    };

    const defaultNewDraft = () => ({
        to: '',
        cc: '',
        bcc: '',
        subject: '',
        body: '',
    });

    const handleHistoryEmailChange = (event) => {
        const nextHistoryEmail = String(event?.target?.value || '');
        if (!routes?.inbox) {
            return;
        }

        const query = {};
        if (selectedFolder && selectedFolder !== 'inbox') {
            query.folder = selectedFolder;
        }
        if (nextHistoryEmail) {
            query.history_email = nextHistoryEmail;
        }

        router.get(
            routes.inbox,
            query,
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: reloadTargets,
            },
        );
    };

    const handleMailboxSwitch = () => {
        if (!routes?.login || mailboxSwitchEmail === '') {
            return;
        }

        const query = new URLSearchParams({
            switch: '1',
            email: mailboxSwitchEmail,
        });

        window.location.href = `${routes.login}?${query.toString()}`;
    };

    const syncNow = () => {
        router.reload({
            only: reloadTargets,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleComposeOpen = (mode) => {
        if ((mode === 'reply' || mode === 'reply_all' || mode === 'forward') && !selected_message) {
            return;
        }

        setActionMenuOpen(false);
        setComposeMode(mode);
        if (mode === 'forward') {
            setComposeForm(defaultForwardDraft());
            return;
        }

        if (mode === 'reply_all') {
            setComposeForm(defaultReplyAllDraft());
            return;
        }

        if (mode === 'reply') {
            setComposeForm(defaultReplyDraft());
            return;
        }

        setComposeForm(defaultNewDraft());
    };

    const handleComposeClose = () => {
        setComposeMode('');
        if (selected_message) {
            setComposeForm(defaultReplyDraft());
            return;
        }

        setComposeForm(defaultNewDraft());
    };

    const handleComposeSubmit = (event, action = 'send') => {
        event.preventDefault();
        if (!routes?.compose || composeSending || !composeIsOpen) {
            return;
        }

        setComposeSending(true);

        const payload = {
            action,
            message_id: selected_message?.id ? String(selected_message.id) : null,
            folder: selectedFolder,
            history_email: selectedHistoryEmail || null,
            to: composeForm.to,
            cc: composeForm.cc,
            bcc: composeForm.bcc,
            subject: composeForm.subject,
            body: composeForm.body,
        };

        router.post(
            routes.compose,
            payload,
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setComposeSending(false),
                onSuccess: () => {
                    if (action === 'draft') {
                        syncNow();
                        return;
                    }

                    setComposeForm(composeMode === 'new'
                        ? defaultNewDraft()
                        : {
                            ...defaultReplyDraft(),
                            to: composeMode === 'forward' ? '' : defaultReplyDraft().to,
                            cc: composeMode === 'reply_all' ? defaultReplyAllDraft().cc : '',
                            subject: composeMode === 'forward' ? defaultForwardDraft().subject : defaultReplyDraft().subject,
                        });
                    syncNow();
                },
            },
        );
    };

    const handleComposeSend = (event) => handleComposeSubmit(event, 'send');
    const handleComposeDraft = (event) => handleComposeSubmit(event, 'draft');
    const handleMessageAction = (actionRoute) => {
        if (!actionRoute) {
            return;
        }

        setActionMenuOpen(false);
        router.post(actionRoute, {
            folder: selectedFolder,
            history_email: selectedHistoryEmail || null,
        }, {
            preserveScroll: true,
            preserveState: true,
            only: reloadTargets,
        });
    };

    useEffect(() => {
        const reloadInbox = () => {
            router.reload({
                only: reloadTargets,
                preserveScroll: true,
                preserveState: true,
            });
        };

        const intervalSeconds = Math.max(Number(sync_meta?.interval_seconds ?? 60), 15);

        let stream = null;
        if (routes?.stream && typeof window !== 'undefined' && typeof window.EventSource !== 'undefined') {
            stream = new window.EventSource(routes.stream, { withCredentials: true });
            stream.addEventListener('mail.updated', () => {
                reloadInbox();
            });
            stream.addEventListener('mail.expired', (event) => {
                try {
                    const payload = JSON.parse(event?.data || '{}');
                    if (payload?.login) {
                        window.location.href = payload.login;
                        return;
                    }
                } catch (_) {
                    // noop
                }

                if (routes?.login) {
                    window.location.href = routes.login;
                }
            });
        }

        const intervalId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            reloadInbox();
        }, intervalSeconds * 1000);

        return () => {
            window.clearInterval(intervalId);
            if (stream) {
                stream.close();
            }
        };
    }, [routes?.stream, routes?.login, sync_meta?.interval_seconds]);

    useEffect(() => {
        if (composeRequested) {
            setComposeMode('new');
            setComposeForm(defaultNewDraft());
            setActionMenuOpen(false);
            return;
        }

        if (!selected_message) {
            setComposeMode('');
            setComposeForm(defaultNewDraft());
            setActionMenuOpen(false);
            return;
        }

        setComposeMode('');
        setComposeForm(defaultReplyDraft());
        setActionMenuOpen(false);
    }, [composeRequested, selected_message?.id, selectedThreadMessage?.id]);

    useEffect(() => {
        setMailboxSwitchEmail(mailboxSwitchCurrentEmail || firstMailboxSwitchEmail);
    }, [mailboxSwitchCurrentEmail, firstMailboxSwitchEmail]);

    useEffect(() => {
        const badge = typeof document !== 'undefined'
            ? document.getElementById('apptimatic-email-sidebar-unread')
            : null;

        if (badge) {
            badge.textContent = String(Math.max(Number(unread_count || 0), 0));
        }
    }, [unread_count]);

    useEffect(() => {
        if (!actionMenuOpen || typeof document === 'undefined') {
            return () => {};
        }

        const closeMenuOnOutsideClick = (event) => {
            if (!actionMenuRef.current) {
                return;
            }

            if (!actionMenuRef.current.contains(event.target)) {
                setActionMenuOpen(false);
            }
        };

        const closeMenuOnEscape = (event) => {
            if (event.key === 'Escape') {
                setActionMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', closeMenuOnOutsideClick);
        document.addEventListener('keydown', closeMenuOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeMenuOnOutsideClick);
            document.removeEventListener('keydown', closeMenuOnEscape);
        };
    }, [actionMenuOpen]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="card overflow-hidden p-0">
                <div className="border-b border-slate-200 bg-white px-4 py-4 md:px-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold text-slate-900">Apptimatic Email</h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {portal_label} | Unread: {unread_count}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Sync mode: {sync_meta?.mode === 'live' ? 'Live IMAP' : 'Stub fallback'} | Push: {routes?.stream ? 'SSE enabled' : 'off'} | Auto refresh: {Math.max(Number(sync_meta?.interval_seconds ?? 60), 15)}s
                            </p>
                            {(pageErrors?.mail_action || pageErrors?.compose) ? (
                                <p className="mt-1 text-xs text-rose-600">{pageErrors.mail_action || pageErrors.compose}</p>
                            ) : null}
                        </div>
                        <div className="inline-flex items-center gap-2">
                            {mailboxSwitchEnabled ? (
                                <div className="inline-flex items-center gap-2 rounded-full bg-white text-xs font-semibold text-slate-600">
                                    <span>Mailbox</span>
                                    <select
                                        value={mailboxSwitchEmail}
                                        onChange={(event) => setMailboxSwitchEmail(String(event?.target?.value || ''))}
                                        className="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 focus:border-teal-400 focus:outline-none"
                                    >
                                        {mailboxSwitchOptions.map((mailbox) => (
                                            <option key={mailbox.id} value={mailbox.email}>
                                                {mailbox.label || mailbox.email}
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        type="button"
                                        onClick={handleMailboxSwitch}
                                        disabled={isCurrentMailboxSelected || mailboxSwitchEmail === ''}
                                        className="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {isCurrentMailboxSelected ? 'Current' : 'Switch'}
                                    </button>
                                </div>
                            ) : null}
                            {emailFilterEnabled ? (
                                <label className="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-semibold text-slate-600">
                                    <span>Email history</span>
                                    <select
                                        value={selectedHistoryEmail}
                                        onChange={handleHistoryEmailChange}
                                        className="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 focus:border-teal-400 focus:outline-none"
                                    >
                                        <option value="">All emails</option>
                                        {emailFilterOptions.map((email) => (
                                            <option key={email} value={email}>
                                                {email}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            ) : null}
                            {routes?.logout ? (
                                <form method="POST" action={routes.logout}>
                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                                    <button
                                        type="submit"
                                        className="rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                    >
                                        Logout from Email
                                    </button>
                                </form>
                            ) : null}
                        </div>
                    </div>
                </div>

                <div className="grid min-h-[65vh] grid-cols-1 lg:grid-cols-[minmax(20rem,38%)_1fr]">
                    <section className={`${listPanelVisibilityClass} border-b border-slate-200 bg-white lg:border-b-0 lg:border-r`}>
                        {messages.length > 0 ? (
                            <div className="h-full">
                                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{selectedFolderLabel}</div>
                                    <div className="text-xs text-slate-500">{messages.length} email(s)</div>
                                </div>
                                <div className="divide-y divide-slate-100">
                                    {messages.map((message) => (
                                        <a key={message.id} href={message?.routes?.show} data-native="true" className={rowClassName(message)}>
                                            <div className="min-w-0 flex flex-1 items-start gap-3">
                                                <span
                                                    className={
                                                        message?.unread
                                                            ? 'mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-700'
                                                            : 'mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600'
                                                    }
                                                >
                                                    {String(message?.sender_name || 'U').charAt(0).toUpperCase()}
                                                </span>

                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <div
                                                            className={
                                                                message?.unread
                                                                    ? 'truncate text-sm font-semibold text-slate-900'
                                                                    : 'truncate text-sm font-medium text-slate-700'
                                                            }
                                                        >
                                                            {message.sender_name}
                                                        </div>
                                                        {message?.unread ? (
                                                            <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-teal-700">
                                                                New
                                                            </span>
                                                        ) : null}
                                                    </div>

                                                    {message?.sender_email ? (
                                                        <div className="mt-0.5 truncate text-xs text-slate-500">{message.sender_email}</div>
                                                    ) : null}

                                                    <div className="mt-1 truncate text-sm text-slate-700">
                                                        <span
                                                            className={
                                                                message?.unread
                                                                    ? 'font-semibold text-slate-900'
                                                                    : 'font-medium text-slate-700'
                                                            }
                                                        >
                                                            {message.subject}
                                                        </span>
                                                        <span className="text-slate-500"> - {message.snippet}</span>
                                                        {message?.has_attachments ? (
                                                            <span className="ml-2 inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                                                Attachment
                                                            </span>
                                                        ) : null}
                                                    </div>

                                                    {message?.to ? (
                                                        <div className="mt-1 truncate text-xs text-slate-500">
                                                            To: {message.to}
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="ml-3 shrink-0 whitespace-nowrap text-xs text-slate-500">{message.received_at_display}</div>
                                        </a>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center text-slate-500">
                                {selectedHistoryEmail ? `No emails found for ${selectedHistoryEmail} in ${selectedFolderLabel}.` : `No emails in ${selectedFolderLabel}.`}
                            </div>
                        )}
                    </section>

                    <section className={`${detailsPanelVisibilityClass} bg-slate-50/60`}>
                        {!selected_message && !composeIsOpen ? (
                            <div className="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center">
                                <div>
                                    <div className="text-sm font-semibold text-slate-700">Select a message</div>
                                    <div className="mt-1 text-sm text-slate-500">Open any inbox item to view the thread.</div>
                                </div>
                            </div>
                        ) : (
                            <div className="h-full overflow-y-auto">
                                <div className="px-4 pt-4 md:px-6 lg:hidden">
                                    <a
                                        href={inboxListUrl}
                                        data-native="true"
                                        className="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                    >
                                        Back to inbox
                                    </a>
                                </div>
                                <div className="space-y-4 px-4 py-4 md:px-6">
                                    {selected_message ? (
                                        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                        <div className="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                            <h3 className="text-xl font-semibold text-slate-900">{selected_message.subject}</h3>
                                            <div className="flex items-center gap-2">
                                                <div className="ml-1 text-xs text-slate-500">
                                                    {selected_message.thread_count} message(s)
                                                </div>
                                                <div className="relative" ref={actionMenuRef}>
                                                    <button
                                                        type="button"
                                                        onClick={() => setActionMenuOpen((open) => !open)}
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 bg-white text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                                        aria-label="More actions"
                                                        title="More actions"
                                                    >
                                                        <svg viewBox="0 0 20 20" fill="currentColor" className="h-5 w-5">
                                                            <path d="M10 4.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                                        </svg>
                                                    </button>

                                                    {actionMenuOpen ? (
                                                        <div className="absolute right-0 top-full z-20 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                                                            <button
                                                                type="button"
                                                                onClick={() => handleComposeOpen('reply')}
                                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                            >
                                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                    <path d="M8 5 3 10l5 5" strokeLinecap="round" strokeLinejoin="round" />
                                                                    <path d="M4 10h8a5 5 0 0 1 5 5" strokeLinecap="round" />
                                                                </svg>
                                                                <span>Reply</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleComposeOpen('reply_all')}
                                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                            >
                                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                    <path d="M10 5 5 10l5 5" strokeLinecap="round" strokeLinejoin="round" />
                                                                    <path d="M6 10h7a5 5 0 0 1 4 2" strokeLinecap="round" />
                                                                    <path d="M6 5 1 10l5 5" strokeLinecap="round" strokeLinejoin="round" />
                                                                </svg>
                                                                <span>Reply to all</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleComposeOpen('forward')}
                                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                            >
                                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                    <path d="m12 5 5 5-5 5" strokeLinecap="round" strokeLinejoin="round" />
                                                                    <path d="M16 10H8a5 5 0 0 0-5 5" strokeLinecap="round" />
                                                                </svg>
                                                                <span>Forward</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleMessageAction(selectedFolder === 'trash'
                                                                    ? selected_message?.routes?.delete
                                                                    : selected_message?.routes?.move_trash)}
                                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                            >
                                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                    <path d="M4.5 6h11" strokeLinecap="round" />
                                                                    <path d="M7 6V4.8A1.8 1.8 0 0 1 8.8 3h2.4A1.8 1.8 0 0 1 13 4.8V6" strokeLinecap="round" />
                                                                    <path d="m6 6 .7 9.3A1.8 1.8 0 0 0 8.5 17h3a1.8 1.8 0 0 0 1.8-1.7L14 6" strokeLinecap="round" />
                                                                </svg>
                                                                <span>{selectedFolder === 'trash' ? 'Delete forever' : 'Delete'}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleMessageAction(selected_message?.routes?.mark_unread)}
                                                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                            >
                                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                    <rect x="3" y="5" width="14" height="10" rx="2" />
                                                                    <path d="m3.5 6 6.5 5 6.5-5" strokeLinecap="round" strokeLinejoin="round" />
                                                                </svg>
                                                                <span>Mark as unread</span>
                                                            </button>
                                                            {selectedFolder === 'trash' ? (
                                                                <button
                                                                    type="button"
                                                                    onClick={() => handleMessageAction(selected_message?.routes?.restore)}
                                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                                                >
                                                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-4 w-4">
                                                                        <path d="M6 5h8a2 2 0 0 1 2 2v8" strokeLinecap="round" />
                                                                        <path d="m9 8-3-3-3 3" strokeLinecap="round" strokeLinejoin="round" />
                                                                        <path d="M3 5h8a2 2 0 0 1 2 2v8" strokeLinecap="round" />
                                                                    </svg>
                                                                    <span>Restore</span>
                                                                </button>
                                                            ) : null}
                                                        </div>
                                                    ) : null}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-start gap-3">
                                            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">
                                                {String(selectedThreadMessage?.sender_name || selected_message.sender_name || 'U').charAt(0).toUpperCase()}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <div>
                                                        <div className="text-sm font-semibold text-slate-900">
                                                            {selectedThreadMessage?.sender_name || selected_message.sender_name}
                                                        </div>
                                                        <div className="text-xs text-slate-500">
                                                            {selectedThreadMessage?.sender_email || selected_message.sender_email}
                                                        </div>
                                                    </div>
                                                    <div className="text-xs text-slate-500">
                                                        {selectedThreadMessage?.received_at_display || selected_message.received_at_display}
                                                    </div>
                                                </div>
                                                <div className="mt-1 text-xs text-slate-500">
                                                    To: {selectedThreadMessage?.to || selected_message.to}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="mt-5 whitespace-pre-line text-[15px] leading-7 text-slate-700">
                                            {selectedThreadMessage?.body || 'No message body available.'}
                                        </div>

                                        {selectedAttachments.length > 0 ? (
                                            <div className="mt-5 space-y-3 border-t border-slate-100 pt-4">
                                                <div className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Attachments</div>

                                                {selectedImageAttachments.length > 0 ? (
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                                        {selectedImageAttachments.map((attachment) => (
                                                            <a
                                                                key={`selected-image-${attachment.part}`}
                                                                href={attachment?.routes?.download || '#'}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="overflow-hidden rounded-xl border border-slate-200 bg-white"
                                                            >
                                                                <img
                                                                    src={attachment?.routes?.preview || ''}
                                                                    alt={attachment?.filename || 'Attachment'}
                                                                    className="h-32 w-full bg-slate-50 object-cover"
                                                                    loading="lazy"
                                                                />
                                                                <div className="border-t border-slate-100 px-2 py-1.5 text-[11px] text-slate-600">
                                                                    <div className="truncate font-medium">{attachment?.filename || 'image'}</div>
                                                                    <div className="text-slate-500">{formatAttachmentSize(attachment?.size)}</div>
                                                                </div>
                                                            </a>
                                                        ))}
                                                    </div>
                                                ) : null}

                                                {selectedFileAttachments.length > 0 ? (
                                                    <div className="flex flex-wrap gap-2">
                                                        {selectedFileAttachments.map((attachment) => (
                                                            <a
                                                                key={`selected-file-${attachment.part}`}
                                                                href={attachment?.routes?.download || '#'}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 transition hover:border-teal-300 hover:text-teal-600"
                                                            >
                                                                <span className="truncate">{attachment?.filename || 'attachment'}</span>
                                                                {formatAttachmentSize(attachment?.size) ? (
                                                                    <span className="text-slate-500">{formatAttachmentSize(attachment?.size)}</span>
                                                                ) : null}
                                                            </a>
                                                        ))}
                                                    </div>
                                                ) : null}
                                            </div>
                                        ) : null}
                                        </article>
                                    ) : null}

                                    {composeIsOpen ? (
                                        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                            <div className="mb-4 flex items-center justify-between gap-3">
                                                <h4 className="text-sm font-semibold uppercase tracking-[0.2em] text-slate-600">
                                                    {composeMode === 'forward'
                                                        ? 'Forward'
                                                        : (composeMode === 'reply_all'
                                                            ? 'Reply to all'
                                                            : (composeMode === 'new' ? 'Compose' : 'Reply'))}
                                                </h4>
                                                <div className="flex items-center gap-3">
                                                    {flashStatus ? <div className="text-xs text-emerald-600">{flashStatus}</div> : null}
                                                    <button
                                                        type="button"
                                                        onClick={handleComposeClose}
                                                        className="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                                    >
                                                        Close
                                                    </button>
                                                </div>
                                            </div>
                                            <form onSubmit={handleComposeSend} className="space-y-3">
                                                <div className="grid gap-3 md:grid-cols-3">
                                                    <label className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                        To
                                                        <input
                                                            type="text"
                                                            value={composeForm.to}
                                                            onChange={(e) => setComposeForm((prev) => ({ ...prev, to: e.target.value }))}
                                                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                            placeholder="recipient@example.com"
                                                            required
                                                        />
                                                    </label>
                                                    <label className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                        Cc
                                                        <input
                                                            type="text"
                                                            value={composeForm.cc}
                                                            onChange={(e) => setComposeForm((prev) => ({ ...prev, cc: e.target.value }))}
                                                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                            placeholder="cc1@example.com, cc2@example.com"
                                                        />
                                                    </label>
                                                    <label className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                        Bcc
                                                        <input
                                                            type="text"
                                                            value={composeForm.bcc}
                                                            onChange={(e) => setComposeForm((prev) => ({ ...prev, bcc: e.target.value }))}
                                                            className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                            placeholder="bcc@example.com"
                                                        />
                                                    </label>
                                                </div>

                                                <label className="block text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                    Subject
                                                    <input
                                                        type="text"
                                                        value={composeForm.subject}
                                                        onChange={(e) => setComposeForm((prev) => ({ ...prev, subject: e.target.value }))}
                                                        className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                        required
                                                    />
                                                </label>

                                                <label className="block text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                    Message
                                                    <textarea
                                                        value={composeForm.body}
                                                        onChange={(e) => setComposeForm((prev) => ({ ...prev, body: e.target.value }))}
                                                        className="mt-1 h-36 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6 text-slate-700 focus:border-teal-400 focus:outline-none"
                                                        placeholder={
                                                            composeMode === 'forward'
                                                                ? 'Write your forward note...'
                                                                : (composeMode === 'new' ? 'Write your email...' : 'Write your reply...')
                                                        }
                                                        required
                                                    />
                                                </label>

                                                {(pageErrors?.compose || pageErrors?.reply) ? (
                                                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                                        {pageErrors.compose || pageErrors.reply}
                                                    </div>
                                                ) : null}

                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={handleComposeDraft}
                                                        disabled={composeSending}
                                                        className="rounded-full border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600 disabled:cursor-not-allowed disabled:opacity-60"
                                                    >
                                                        {composeSending ? 'Saving...' : 'Save Draft'}
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        disabled={composeSending}
                                                        className="rounded-full border border-teal-500 bg-teal-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-60"
                                                    >
                                                        {composeSending ? 'Sending...' : (
                                                            composeMode === 'forward'
                                                                ? 'Send Forward'
                                                                : (composeMode === 'reply_all'
                                                                    ? 'Send Reply All'
                                                                    : (composeMode === 'new' ? 'Send Email' : 'Send Reply'))
                                                        )}
                                                    </button>
                                                </div>
                                            </form>
                                        </article>
                                    ) : null}

                                    {previousThreadMessages.length > 0 ? (
                                        <div className="space-y-3">
                                            <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Earlier in thread</div>
                                            {previousThreadMessages.map((thread) => (
                                                <article key={thread.id || `${thread.subject}-${thread.received_at_display}`} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div className="min-w-0">
                                                            <div className="truncate text-sm font-semibold text-slate-800">
                                                                {thread.sender_name}
                                                            </div>
                                                            <div className="truncate text-xs text-slate-500">
                                                                {thread.sender_email}
                                                            </div>
                                                        </div>
                                                        <div className="text-xs text-slate-500">{thread.received_at_display}</div>
                                                    </div>
                                                    <div className="mt-1 text-xs text-slate-500">To: {thread.to}</div>
                                                    <div className="mt-3 text-sm font-medium text-slate-800">{thread.subject}</div>
                                                    <div className="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{thread.body}</div>

                                                    {Array.isArray(thread?.attachments) && thread.attachments.length > 0 ? (
                                                        <div className="mt-3 flex flex-wrap gap-2">
                                                            {thread.attachments.map((attachment) => (
                                                                <a
                                                                    key={`${thread.id || 'thread'}-${attachment.part}`}
                                                                    href={attachment?.routes?.download || '#'}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 transition hover:border-teal-300 hover:text-teal-600"
                                                                >
                                                                    <span className="truncate">{attachment?.filename || 'attachment'}</span>
                                                                    {formatAttachmentSize(attachment?.size) ? (
                                                                        <span className="text-slate-500">{formatAttachmentSize(attachment?.size)}</span>
                                                                    ) : null}
                                                                </a>
                                                            ))}
                                                        </div>
                                                    ) : null}
                                                </article>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
