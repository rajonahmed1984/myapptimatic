import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

const reloadTargets = ['messages', 'selected_message', 'thread_messages', 'unread_count', 'sync_meta', 'history_email_filter', 'mailbox_switch', 'folder_filter'];

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

const getInitials = (name) => {
    const trimmed = String(name || '').trim();
    if (!trimmed) return '?';
    const parts = trimmed.split(' ').filter(Boolean);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return trimmed.substring(0, 2).toUpperCase();
};

const getAvatarColor = (name) => {
    const colors = [
        'bg-teal-600 text-white',
        'bg-indigo-600 text-white',
        'bg-sky-600 text-white',
        'bg-amber-600 text-white',
        'bg-emerald-600 text-white',
        'bg-rose-600 text-white',
        'bg-violet-600 text-white',
        'bg-cyan-600 text-white',
    ];
    let hash = 0;
    for (let i = 0; i < (name || '').length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
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

    // Search query for instant filtering
    const [searchQuery, setSearchQuery] = useState('');

    // Folders
    const folderOptions = Array.isArray(folder_filter?.options) && folder_filter.options.length > 0
        ? folder_filter.options
        : [
            { key: 'inbox', label: 'Inbox' },
            { key: 'sent', label: 'Sent' },
            { key: 'drafts', label: 'Drafts' },
            { key: 'spam', label: 'Spam' },
            { key: 'trash', label: 'Trash' },
        ];
    const selectedFolder = String(folder_filter?.selected || 'inbox');
    const selectedFolderLabel = useMemo(() => {
        const current = folderOptions.find((option) => String(option?.key || '') === selectedFolder);
        return String(current?.label || 'Inbox');
    }, [folderOptions, selectedFolder]);

    // Permissions & filters
    const isMasterAdmin = Boolean(page?.props?.permissions?.is_master_admin);
    const emailFilterEnabled = Boolean(isMasterAdmin && history_email_filter?.enabled);
    const selectedHistoryEmail = String(history_email_filter?.selected || '');
    const emailFilterOptions = Array.isArray(history_email_filter?.options) ? history_email_filter.options : [];

    const mailboxSwitchEnabled = Boolean(isMasterAdmin && mailbox_switch?.enabled);
    const mailboxSwitchOptions = Array.isArray(mailbox_switch?.options) ? mailbox_switch.options : [];
    const mailboxSwitchCurrentEmail = String(mailbox_switch?.current_email || '');
    const firstMailboxSwitchEmail = String(mailboxSwitchOptions[0]?.email || '');
    const [mailboxSwitchEmail, setMailboxSwitchEmail] = useState(mailboxSwitchCurrentEmail || firstMailboxSwitchEmail);
    const isCurrentMailboxSelected = mailboxSwitchEmail !== '' && mailboxSwitchEmail === mailboxSwitchCurrentEmail;

    const activeMailboxEmail = String(mailbox_email || mailboxSwitchCurrentEmail || '').trim().toLowerCase();

    // Checkbox selections for batch / toolbar actions
    const [selectedIds, setSelectedIds] = useState(new Set());
    const [starredIds, setStarredIds] = useState(new Set());

    // Compose state (Gmail Floating Window)
    const [composeMode, setComposeMode] = useState(''); // '', 'new', 'reply', 'reply_all', 'forward'
    const [composeMinimized, setComposeMinimized] = useState(false);
    const [composeMaximized, setComposeMaximized] = useState(false);
    const [showCc, setShowCc] = useState(false);
    const [showBcc, setShowBcc] = useState(false);
    const [composeSending, setComposeSending] = useState(false);
    const [composeForm, setComposeForm] = useState({
        to: '',
        cc: '',
        bcc: '',
        subject: '',
        body: '',
    });

    // Inline quick reply state
    const [inlineReplyOpen, setInlineReplyOpen] = useState(false);
    const [inlineReplyMode, setInlineReplyMode] = useState('reply'); // 'reply', 'reply_all', 'forward'
    const [inlineReplyBody, setInlineReplyBody] = useState('');
    const [inlineSending, setInlineSending] = useState(false);

    // Thread messages
    const selectedThreadMessage = thread_messages.find((thread) => Boolean(thread?.is_selected)) || thread_messages[thread_messages.length - 1] || null;
    const previousThreadMessages = selectedThreadMessage
        ? thread_messages.filter((thread) => String(thread?.id || '') !== String(selectedThreadMessage?.id || ''))
        : [];
    const selectedAttachments = Array.isArray(selectedThreadMessage?.attachments) ? selectedThreadMessage.attachments : [];
    const selectedImageAttachments = selectedAttachments.filter((attachment) => String(attachment?.mime || '').startsWith('image/'));
    const selectedFileAttachments = selectedAttachments.filter((attachment) => !String(attachment?.mime || '').startsWith('image/'));

    // Checking if compose was requested via query param (?compose=new)
    const composeRequested = useMemo(() => {
        const rawUrl = String(page?.url || '');
        const queryString = rawUrl.includes('?') ? rawUrl.slice(rawUrl.indexOf('?') + 1) : '';
        if (queryString === '') return false;
        const composeQuery = String(new URLSearchParams(queryString).get('compose') || '').toLowerCase();
        return composeQuery === '1' || composeQuery === 'true' || composeQuery === 'new';
    }, [page?.url]);

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

    // Filter messages by search term
    const filteredMessages = useMemo(() => {
        const q = searchQuery.trim().toLowerCase();
        if (!q) return messages;
        return messages.filter((m) => {
            const senderName = String(m.sender_name || '').toLowerCase();
            const senderEmail = String(m.sender_email || '').toLowerCase();
            const subject = String(m.subject || '').toLowerCase();
            const snippet = String(m.snippet || '').toLowerCase();
            const to = String(m.to || '').toLowerCase();
            return senderName.includes(q) || senderEmail.includes(q) || subject.includes(q) || snippet.includes(q) || to.includes(q);
        });
    }, [messages, searchQuery]);

    // Selection handlers
    const allSelected = filteredMessages.length > 0 && filteredMessages.every((m) => selectedIds.has(m.id));
    const isIndeterminate = selectedIds.size > 0 && !allSelected;

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(filteredMessages.map((m) => m.id)));
        }
    };

    const toggleSelectOne = (id, e) => {
        if (e) e.stopPropagation();
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleStar = (id, e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        setStarredIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    // Default draft templates
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
            if (normalized === '' || seen.has(normalized)) return;
            seen.add(normalized);
            uniqueRecipients.push(normalized);
        });

        const filteredRecipients = uniqueRecipients.filter((address) => {
            if (activeMailboxEmail !== '' && address === activeMailboxEmail) return false;
            return true;
        });

        return {
            to: filteredRecipients[0] || '',
            cc: filteredRecipients.slice(1).join(', '),
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

    const syncNow = () => {
        router.reload({
            only: reloadTargets,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleFolderClick = (folderKey) => {
        if (folderKey === selectedFolder && !selected_message) {
            syncNow();
            return;
        }

        const query = {};
        if (folderKey && folderKey !== 'inbox') {
            query.folder = folderKey;
        }
        if (selectedHistoryEmail) {
            query.history_email = selectedHistoryEmail;
        }

        router.get(routes.inbox, query, {
            preserveScroll: true,
            preserveState: false,
            only: reloadTargets,
        });
    };

    const handleHistoryEmailChange = (nextValue) => {
        const nextHistoryEmail = String(nextValue || '');
        if (!routes?.inbox) return;

        const query = {};
        if (selectedFolder && selectedFolder !== 'inbox') {
            query.folder = selectedFolder;
        }
        if (nextHistoryEmail) {
            query.history_email = nextHistoryEmail;
        }

        router.get(routes.inbox, query, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: reloadTargets,
        });
    };

    const handleMailboxSwitch = (emailOverride) => {
        const target = typeof emailOverride === 'string' && emailOverride !== '' ? emailOverride : mailboxSwitchEmail;
        if (!routes?.login || !target) return;
        const query = new URLSearchParams({
            switch: '1',
            email: target,
        });
        window.location.href = `${routes.login}?${query.toString()}`;
    };

    const handleComposeOpen = (mode = 'new') => {
        setComposeMode(mode);
        setComposeMinimized(false);
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
        setComposeMinimized(false);
        setComposeMaximized(false);
        setComposeForm(defaultNewDraft());
    };

    const handleComposeSubmit = (event, action = 'send') => {
        if (event) event.preventDefault();
        if (!routes?.compose || composeSending || !composeMode) return;

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

        router.post(routes.compose, payload, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setComposeSending(false),
            onSuccess: () => {
                if (action === 'draft') {
                    syncNow();
                    return;
                }
                handleComposeClose();
                syncNow();
            },
        });
    };

    const handleComposeSend = (e) => handleComposeSubmit(e, 'send');
    const handleComposeDraft = (e) => handleComposeSubmit(e, 'draft');

    // Inline reply handler
    const handleInlineReplySubmit = (e) => {
        e.preventDefault();
        if (!routes?.compose || inlineSending || !inlineReplyBody.trim()) return;

        setInlineSending(true);

        let to = selectedThreadMessage?.sender_email || selected_message?.sender_email || '';
        let cc = '';
        if (inlineReplyMode === 'reply_all') {
            const allDraft = defaultReplyAllDraft();
            to = allDraft.to;
            cc = allDraft.cc;
        } else if (inlineReplyMode === 'forward') {
            to = composeForm.to; // user fills to
        }

        const rawSubject = String(selected_message?.subject || '');
        const subject = rawSubject.toLowerCase().startsWith('re:') ? rawSubject : `Re: ${rawSubject}`;

        const payload = {
            action: 'send',
            message_id: selected_message?.id ? String(selected_message.id) : null,
            folder: selectedFolder,
            history_email: selectedHistoryEmail || null,
            to,
            cc,
            bcc: '',
            subject,
            body: inlineReplyBody,
        };

        router.post(routes.compose, payload, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setInlineSending(false),
            onSuccess: () => {
                setInlineReplyBody('');
                setInlineReplyOpen(false);
                syncNow();
            },
        });
    };

    const handleMessageAction = (actionRoute) => {
        if (!actionRoute) return;
        router.post(actionRoute, {
            folder: selectedFolder,
            history_email: selectedHistoryEmail || null,
        }, {
            preserveScroll: true,
            preserveState: true,
            only: reloadTargets,
        });
    };

    // Quick row action: mark unread
    const handleRowMarkUnread = (msg, e) => {
        e.preventDefault();
        e.stopPropagation();
        if (routes?.inbox && msg?.id) {
            router.post(`${routes.inbox}/messages/${msg.id}/mark-unread`, {
                folder: selectedFolder,
                history_email: selectedHistoryEmail || null,
            }, {
                preserveScroll: true,
                preserveState: true,
                only: reloadTargets,
            });
        }
    };

    // Quick row action: move trash / delete
    const handleRowDelete = (msg, e) => {
        e.preventDefault();
        e.stopPropagation();
        if (routes?.inbox && msg?.id) {
            const actionPath = selectedFolder === 'trash' ? 'delete' : 'move-trash';
            router.post(`${routes.inbox}/messages/${msg.id}/${actionPath}`, {
                folder: selectedFolder,
                history_email: selectedHistoryEmail || null,
            }, {
                preserveScroll: true,
                preserveState: true,
                only: reloadTargets,
            });
        }
    };

    // SSE Streaming and interval refresh
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
            stream.addEventListener('mail.updated', () => reloadInbox());
            stream.addEventListener('mail.expired', (event) => {
                try {
                    const payload = JSON.parse(event?.data || '{}');
                    if (payload?.login) {
                        window.location.href = payload.login;
                        return;
                    }
                } catch (_) {}
                if (routes?.login) {
                    window.location.href = routes.login;
                }
            });
        }

        const intervalId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            reloadInbox();
        }, intervalSeconds * 1000);

        return () => {
            window.clearInterval(intervalId);
            if (stream) stream.close();
        };
    }, [routes?.stream, routes?.login, sync_meta?.interval_seconds]);

    // Handle compose URL param
    useEffect(() => {
        if (composeRequested) {
            handleComposeOpen('new');
        }
    }, [composeRequested]);

    // Update global sidebar counter if present
    useEffect(() => {
        const badge = typeof document !== 'undefined'
            ? document.getElementById('apptimatic-email-sidebar-unread')
            : null;
        if (badge) {
            badge.textContent = String(Math.max(Number(unread_count || 0), 0));
        }
    }, [unread_count]);

    const mailboxSwitchSelectOptions = mailboxSwitchOptions.map((mailbox) => ({
        value: String(mailbox.email),
        label: mailbox.label || mailbox.email,
    }));
    const historyEmailSelectOptions = [
        { value: '', label: 'All emails' },
        ...emailFilterOptions.map((email) => ({ value: String(email), label: email })),
    ];

    // Folder icon lookup
    const renderFolderIcon = (key) => {
        switch (key) {
            case 'inbox':
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                );
            case 'sent':
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                );
            case 'drafts':
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                );
            case 'spam':
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                );
            case 'trash':
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                );
            default:
                return (
                    <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                    </svg>
                );
        }
    };

    return (
        <>
            <Head title={`${pageTitle} - ${selectedFolderLabel}`} />

            <div className="flex flex-col h-[calc(100vh-5rem)] min-h-[620px] bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden font-sans">
                {/* 1. Gmail-style Top Header Bar */}
                <header className="flex flex-wrap md:flex-nowrap items-center justify-between gap-2 sm:gap-3 lg:gap-4 px-3 sm:px-4 py-2.5 sm:py-3 border-b border-slate-200 bg-slate-50/90 backdrop-blur shrink-0 min-w-0">
                    {/* Left: Brand & Main Navigation Controls */}
                    <div className="flex items-center gap-2 sm:gap-3 shrink-0 min-w-0">

                        {/* Compose Button */}
                        <button
                            type="button"
                            onClick={() => handleComposeOpen('new')}
                            className="inline-flex items-center gap-1.5 px-2.5 sm:px-3.5 py-1.5 rounded-[10px] bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold shadow-xs hover:shadow transition group shrink-0"
                            title="Compose new email"
                        >
                            <svg className="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span className="hidden sm:inline">Compose</span>
                        </button>

                        {/* Current Active Folder Pill */}
                        <div className="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-[10px] bg-white border border-slate-200 text-slate-800 text-xs font-semibold shadow-2xs shrink-0 whitespace-nowrap">
                            <span className="text-teal-600">{renderFolderIcon(selectedFolder)}</span>
                            <span>{selectedFolderLabel}</span>
                            {selectedFolder === 'inbox' && unread_count > 0 && (
                                <span className="ml-1 px-1.5 py-0.2 rounded-full bg-teal-600 text-white text-[10px] font-bold">
                                    {unread_count}
                                </span>
                            )}
                        </div>

                        {/* Active mailbox display for non-admin/single-account views */}
                        {!mailboxSwitchEnabled && activeMailboxEmail && (
                            <div className="hidden 2xl:flex items-center gap-1.5 px-2.5 py-1 rounded-[10px] bg-teal-50 border border-teal-200 text-teal-800 text-xs font-medium shrink-0">
                                <span className="w-2 h-2 rounded-full bg-teal-500 animate-pulse shrink-0"></span>
                                <span className="truncate max-w-[160px]">{activeMailboxEmail}</span>
                            </div>
                        )}
                    </div>

                    {/* Center: Gmail-style Search Bar */}
                    <div className="order-last md:order-none w-full md:w-auto md:flex-1 min-w-[180px] max-w-xl">
                        <div className="relative flex items-center w-full">
                            <div className="absolute left-3 text-slate-400 pointer-events-none">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Search in mail..."
                                className="w-full pl-9 md:pl-10 pr-8 md:pr-9 py-1.5 sm:py-2 rounded-full bg-white hover:bg-slate-100/70 focus:bg-white text-xs md:text-sm text-slate-800 placeholder-slate-400 border border-slate-200 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition shadow-inner focus:shadow-xs"
                            />
                            {searchQuery && (
                                <button
                                    type="button"
                                    onClick={() => setSearchQuery('')}
                                    className="absolute right-2.5 p-0.5 rounded-full hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition"
                                    title="Clear search"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Right: Action controls */}
                    <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
                        {/* Live Sync Status */}
                        <span
                            className="hidden 2xl:inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-500 bg-slate-100 px-2.5 py-1.5 rounded-[10px] border border-slate-200 shrink-0"
                            title={`Sync mode: ${sync_meta?.mode === 'live' ? 'Live IMAP' : 'Synced'}`}
                        >
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span>{sync_meta?.mode === 'live' ? 'Live IMAP' : 'Synced'}</span>
                        </span>

                        {/* Sync Refresh */}
                        <button
                            type="button"
                            onClick={syncNow}
                            className="p-1.5 sm:p-2 rounded-[10px] border border-slate-200 text-slate-600 hover:text-teal-600 hover:bg-teal-50 hover:border-teal-200 transition shrink-0"
                            title="Refresh emails"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        {/* Manage mailboxes button */}
                        {routes?.manage && (
                            <a
                                href={routes.manage}
                                data-native="true"
                                className="p-1.5 sm:p-2 rounded-[10px] border border-slate-200 text-slate-600 hover:text-teal-700 hover:bg-teal-50 hover:border-teal-200 transition shrink-0"
                                title="Manage Mailboxes"
                            >
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </a>
                        )}

                        {/* Mailbox Switcher for Master Admin */}
                        {mailboxSwitchEnabled && (
                            <div className="hidden lg:flex items-center gap-1.5 shrink-0">
                                <div className="w-36 xl:w-44 2xl:w-52">
                                    <SearchableSelect
                                        name="mailbox_switch_email"
                                        value={mailboxSwitchEmail}
                                        onChange={(nextValue) => {
                                            const val = String(nextValue || '');
                                            setMailboxSwitchEmail(val);
                                            if (val && val !== activeMailboxEmail) {
                                                handleMailboxSwitch(val);
                                            }
                                        }}
                                        options={mailboxSwitchSelectOptions}
                                        placeholder="Switch mailbox"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => handleMailboxSwitch()}
                                    disabled={isCurrentMailboxSelected || mailboxSwitchEmail === ''}
                                    className="hidden 2xl:inline-flex px-2.5 py-1.5 rounded-[10px] border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:border-teal-400 hover:text-teal-600 disabled:opacity-40 disabled:cursor-not-allowed transition shrink-0"
                                >
                                    Switch
                                </button>
                            </div>
                        )}

                        {/* History filter */}
                        {emailFilterEnabled && (
                            <div className="hidden 2xl:block w-40 shrink-0">
                                <SearchableSelect
                                    name="history_email"
                                    value={selectedHistoryEmail}
                                    onChange={handleHistoryEmailChange}
                                    options={historyEmailSelectOptions}
                                    placeholder="Filter by contact"
                                />
                            </div>
                        )}

                        {/* Logout from webmail session */}
                        {routes?.logout && (
                            <form method="POST" action={routes.logout} className="inline shrink-0">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''} />
                                <button
                                    type="submit"
                                    className="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-[10px] border border-slate-200 text-xs font-medium text-slate-600 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 transition shrink-0"
                                    title="Sign out from this mailbox"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span className="hidden xl:inline">Logout</span>
                                </button>
                            </form>
                        )}
                    </div>
                </header>

                {/* 2. Main Body: Full-Width Mail Content */}
                <div className="flex-1 flex min-h-0 overflow-hidden">
                    {/* Email List or Email Thread Reading Pane */}
                    <main className="flex-1 flex flex-col min-w-0 bg-white overflow-hidden">
                        {/* Flash / Page Errors */}
                        {(flashStatus || pageErrors?.mail_action || pageErrors?.compose || pageErrors?.reply) && (
                            <div className="px-4 py-2 bg-slate-50 border-b border-slate-200 shrink-0">
                                {flashStatus && <div className="text-xs text-emerald-600 font-medium">{flashStatus}</div>}
                                {(pageErrors?.mail_action || pageErrors?.compose || pageErrors?.reply) && (
                                    <div className="text-xs text-rose-600 font-medium">
                                        {pageErrors?.mail_action || pageErrors?.compose || pageErrors?.reply}
                                    </div>
                                )}
                            </div>
                        )}

                        {selected_message ? (
                            /* ------------------------------------------------------------- */
                            /* 3A. Thread / Reading View (Gmail style)                      */
                            /* ------------------------------------------------------------- */
                            <div className="flex-1 flex flex-col min-h-0 overflow-y-auto">
                                {/* Thread Top Toolbar */}
                                <div className="flex items-center justify-between gap-3 px-4 md:px-6 py-3 border-b border-slate-200 bg-white sticky top-0 z-10">
                                    <div className="flex items-center gap-2">
                                        <a
                                            href={inboxListUrl}
                                            data-native="true"
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                            title="Back to email list"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                            </svg>
                                            <span>Back</span>
                                        </a>

                                        <div className="h-5 w-[1px] bg-slate-200 mx-1"></div>

                                        {/* Move to Trash / Delete Forever */}
                                        <button
                                            type="button"
                                            onClick={() => handleMessageAction(selectedFolder === 'trash' ? selected_message?.routes?.delete : selected_message?.routes?.move_trash)}
                                            className="p-1.5 rounded-lg text-slate-600 hover:text-rose-600 hover:bg-rose-50 transition"
                                            title={selectedFolder === 'trash' ? 'Delete forever' : 'Move to Trash'}
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>

                                        {/* Mark as Unread */}
                                        <button
                                            type="button"
                                            onClick={() => handleMessageAction(selected_message?.routes?.mark_unread)}
                                            className="p-1.5 rounded-lg text-slate-600 hover:text-teal-600 hover:bg-teal-50 transition"
                                            title="Mark as unread"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </button>

                                        {/* Restore if in trash */}
                                        {selectedFolder === 'trash' && (
                                            <button
                                                type="button"
                                                onClick={() => handleMessageAction(selected_message?.routes?.restore)}
                                                className="p-1.5 rounded-lg text-slate-600 hover:text-teal-600 hover:bg-teal-50 transition"
                                                title="Restore to Inbox"
                                            >
                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        )}
                                    </div>

                                    {/* Action Shortcuts */}
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => handleComposeOpen('reply')}
                                            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:border-teal-400 hover:text-teal-700 transition"
                                        >
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a5 5 0 015 5v2m-15-7l4-4m-4 4l4 4" />
                                            </svg>
                                            <span>Reply</span>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => handleComposeOpen('forward')}
                                            className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:border-teal-400 hover:text-teal-700 transition"
                                        >
                                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4" />
                                            </svg>
                                            <span>Forward</span>
                                        </button>
                                    </div>
                                </div>

                                {/* Thread Content */}
                                <div className="p-4 md:p-8 max-w-4xl space-y-6">
                                    {/* Subject Title */}
                                    <div className="flex items-start justify-between gap-4">
                                        <h2 className="text-xl md:text-2xl font-bold text-slate-900 leading-tight">
                                            {selected_message.subject || '(No subject)'}
                                        </h2>
                                        {selected_message.thread_count > 1 && (
                                            <span className="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold shrink-0">
                                                {selected_message.thread_count} messages
                                            </span>
                                        )}
                                    </div>

                                    {/* Message Card */}
                                    <article className="rounded-2xl border border-slate-200 bg-white p-5 md:p-6 shadow-sm">
                                        {/* Sender Card */}
                                        <div className="flex items-start justify-between gap-4 pb-4 border-b border-slate-100">
                                            <div className="flex items-center gap-3.5 min-w-0">
                                                <div className={`w-11 h-11 rounded-full flex items-center justify-center font-bold text-sm shrink-0 shadow-xs ${getAvatarColor(selectedThreadMessage?.sender_name || selected_message.sender_name)}`}>
                                                    {getInitials(selectedThreadMessage?.sender_name || selected_message.sender_name)}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex items-baseline gap-2 flex-wrap">
                                                        <span className="font-bold text-slate-900 text-sm md:text-base">
                                                            {selectedThreadMessage?.sender_name || selected_message.sender_name}
                                                        </span>
                                                        <span className="text-xs text-slate-500 truncate">
                                                            &lt;{selectedThreadMessage?.sender_email || selected_message.sender_email}&gt;
                                                        </span>
                                                    </div>
                                                    <div className="text-xs text-slate-500 mt-0.5">
                                                        to: <span className="text-slate-700 font-medium">{selectedThreadMessage?.to || selected_message.to || 'me'}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="text-right shrink-0">
                                                <span className="text-xs text-slate-500 font-medium">
                                                    {selectedThreadMessage?.received_at_display || selected_message.received_at_display}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Message Body */}
                                        <div className="py-6 text-slate-800 text-sm md:text-[15px] leading-relaxed whitespace-pre-line font-normal">
                                            {selectedThreadMessage?.body || 'No message body available.'}
                                        </div>

                                        {/* Attachments Section */}
                                        {selectedAttachments.length > 0 && (
                                            <div className="mt-4 pt-4 border-t border-slate-100 space-y-3">
                                                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                                                    <svg className="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                    <span>Attachments ({selectedAttachments.length})</span>
                                                </div>

                                                {/* Images Grid */}
                                                {selectedImageAttachments.length > 0 && (
                                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                                        {selectedImageAttachments.map((attachment) => (
                                                            <a
                                                                key={`img-${attachment.part}`}
                                                                href={attachment?.routes?.download || '#'}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="group block rounded-xl border border-slate-200 overflow-hidden hover:border-teal-400 hover:shadow-md transition"
                                                            >
                                                                <div className="h-28 bg-slate-100 overflow-hidden flex items-center justify-center">
                                                                    <img
                                                                        src={attachment?.routes?.preview || ''}
                                                                        alt={attachment?.filename || 'Attachment'}
                                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                                                        loading="lazy"
                                                                    />
                                                                </div>
                                                                <div className="p-2 bg-white">
                                                                    <div className="text-xs font-semibold text-slate-800 truncate">{attachment?.filename}</div>
                                                                    <div className="text-[11px] text-slate-500">{formatAttachmentSize(attachment?.size)}</div>
                                                                </div>
                                                            </a>
                                                        ))}
                                                    </div>
                                                )}

                                                {/* Files / Documents */}
                                                {selectedFileAttachments.length > 0 && (
                                                    <div className="flex flex-wrap gap-2">
                                                        {selectedFileAttachments.map((attachment) => (
                                                            <a
                                                                key={`file-${attachment.part}`}
                                                                href={attachment?.routes?.download || '#'}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-white hover:border-teal-400 hover:shadow-sm transition text-xs font-medium text-slate-700"
                                                            >
                                                                <svg className="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                                </svg>
                                                                <span className="truncate max-w-[200px]">{attachment?.filename}</span>
                                                                {formatAttachmentSize(attachment?.size) && (
                                                                    <span className="text-[11px] text-slate-400">({formatAttachmentSize(attachment?.size)})</span>
                                                                )}
                                                            </a>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </article>

                                    {/* Earlier Messages in Thread */}
                                    {previousThreadMessages.length > 0 && (
                                        <div className="space-y-3">
                                            <div className="text-xs font-bold uppercase tracking-wider text-slate-400">Earlier in this thread</div>
                                            {previousThreadMessages.map((msg) => (
                                                <article key={msg.id} className="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                                    <div className="flex items-center justify-between text-xs mb-2">
                                                        <div className="font-semibold text-slate-800">{msg.sender_name} &lt;{msg.sender_email}&gt;</div>
                                                        <div className="text-slate-500">{msg.received_at_display}</div>
                                                    </div>
                                                    <div className="text-xs text-slate-700 whitespace-pre-line leading-relaxed">{msg.body}</div>
                                                </article>
                                            ))}
                                        </div>
                                    )}

                                    {/* Inline Quick Reply Box (Gmail style) */}
                                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                                        {!inlineReplyOpen ? (
                                            <div className="flex items-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setInlineReplyMode('reply');
                                                        setInlineReplyOpen(true);
                                                    }}
                                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-teal-500 transition"
                                                >
                                                    <svg className="w-3.5 h-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a5 5 0 015 5v2m-15-7l4-4m-4 4l4 4" />
                                                    </svg>
                                                    <span>Reply</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setInlineReplyMode('forward');
                                                        handleComposeOpen('forward');
                                                    }}
                                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-teal-500 transition"
                                                >
                                                    <svg className="w-3.5 h-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 10H11a5 5 0 00-5 5v2m15-7l-4-4m4 4l-4 4" />
                                                    </svg>
                                                    <span>Forward</span>
                                                </button>
                                            </div>
                                        ) : (
                                            <form onSubmit={handleInlineReplySubmit} className="space-y-3">
                                                <div className="flex items-center justify-between border-b border-slate-100 pb-2">
                                                    <span className="text-xs font-bold text-slate-700">
                                                        Replying to: {selectedThreadMessage?.sender_name || selected_message.sender_name}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setInlineReplyOpen(false)}
                                                        className="text-xs text-slate-400 hover:text-slate-600"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>

                                                <textarea
                                                    value={inlineReplyBody}
                                                    onChange={(e) => setInlineReplyBody(e.target.value)}
                                                    placeholder="Write your reply here... (Press Send when done)"
                                                    rows={4}
                                                    className="w-full text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0 border-0 p-0 resize-y"
                                                    autoFocus
                                                ></textarea>

                                                <div className="flex items-center justify-between pt-2 border-t border-slate-100">
                                                    <button
                                                        type="submit"
                                                        disabled={inlineSending || !inlineReplyBody.trim()}
                                                        className="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold shadow-sm disabled:opacity-50 transition"
                                                    >
                                                        {inlineSending ? 'Sending...' : 'Send Reply'}
                                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                                        </svg>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            setInlineReplyBody('');
                                                            setInlineReplyOpen(false);
                                                        }}
                                                        className="p-1.5 text-slate-400 hover:text-rose-600 rounded-full hover:bg-rose-50 transition"
                                                        title="Discard draft"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </form>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            /* ------------------------------------------------------------- */
                            /* 3B. Gmail Message List View                                   */
                            /* ------------------------------------------------------------- */
                            <div className="flex-1 flex flex-col min-h-0">
                                {/* Action Toolbar */}
                                <div className="flex items-center justify-between gap-4 px-4 py-2.5 border-b border-slate-200 bg-white select-none">
                                    <div className="flex items-center gap-3">
                                        {/* Select All Checkbox */}
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                checked={allSelected}
                                                ref={(el) => {
                                                    if (el) el.indeterminate = isIndeterminate;
                                                }}
                                                onChange={toggleSelectAll}
                                                className="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300 cursor-pointer"
                                                title="Select all"
                                            />
                                        </div>

                                        {/* Refresh */}
                                        <button
                                            type="button"
                                            onClick={syncNow}
                                            className="p-1.5 rounded-lg text-slate-500 hover:text-teal-600 hover:bg-slate-100 transition"
                                            title="Refresh"
                                        >
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </button>

                                        {/* Active selection actions */}
                                        {selectedIds.size > 0 && (
                                            <div className="flex items-center gap-1.5 pl-2 border-l border-slate-200">
                                                <span className="text-xs font-semibold text-slate-700 pr-1">
                                                    {selectedIds.size} selected
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Right: Message Counter */}
                                    <div className="text-xs text-slate-500 font-medium">
                                        {filteredMessages.length > 0 ? (
                                            <span>
                                                {searchQuery ? `Found ${filteredMessages.length} email(s)` : `1–${filteredMessages.length} of ${messages.length}`}
                                            </span>
                                        ) : (
                                            <span>0 emails</span>
                                        )}
                                    </div>
                                </div>

                                {/* Messages Table / List */}
                                <div className="flex-1 overflow-y-auto divide-y divide-slate-100">
                                    {filteredMessages.length > 0 ? (
                                        filteredMessages.map((message) => {
                                            const isSelected = selectedIds.has(message.id);
                                            const isStarred = starredIds.has(message.id);
                                            const isUnread = Boolean(message?.unread);

                                            return (
                                                <div
                                                    key={message.id}
                                                    onClick={() => {
                                                        if (message?.routes?.show) {
                                                            router.visit(message.routes.show);
                                                        }
                                                    }}
                                                    className={`group flex items-center gap-3 px-4 py-3 cursor-pointer transition select-none ${
                                                        isUnread ? 'bg-white font-semibold' : 'bg-slate-50/50 hover:bg-white text-slate-700'
                                                    } ${isSelected ? 'bg-teal-50/70 hover:bg-teal-50' : 'hover:bg-slate-50 hover:shadow-xs'}`}
                                                >
                                                    {/* Checkbox */}
                                                    <div className="flex items-center shrink-0" onClick={(e) => e.stopPropagation()}>
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={(e) => toggleSelectOne(message.id, e)}
                                                            className="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 border-slate-300 cursor-pointer"
                                                        />
                                                    </div>

                                                    {/* Star */}
                                                    <button
                                                        type="button"
                                                        onClick={(e) => toggleStar(message.id, e)}
                                                        className={`shrink-0 transition ${
                                                            isStarred ? 'text-amber-400' : 'text-slate-300 group-hover:text-slate-400'
                                                        }`}
                                                        title={isStarred ? 'Starred' : 'Not starred'}
                                                    >
                                                        <svg className="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                        </svg>
                                                    </button>

                                                    {/* Avatar Initial */}
                                                    <div
                                                        className={`w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold shrink-0 ${getAvatarColor(
                                                            message.sender_name
                                                        )}`}
                                                    >
                                                        {getInitials(message.sender_name)}
                                                    </div>

                                                    {/* Sender Name */}
                                                    <div className="w-36 md:w-48 truncate shrink-0">
                                                        <span className={`text-xs md:text-sm ${isUnread ? 'font-bold text-slate-900' : 'font-medium text-slate-700'}`}>
                                                            {message.sender_name}
                                                        </span>
                                                    </div>

                                                    {/* Subject & Snippet */}
                                                    <div className="flex-1 min-w-0 flex items-center gap-1.5 truncate">
                                                        <span className={`text-xs md:text-sm truncate ${isUnread ? 'font-bold text-slate-900' : 'text-slate-800 font-medium'}`}>
                                                            {message.subject}
                                                        </span>
                                                        <span className="text-xs md:text-sm text-slate-400 truncate hidden sm:inline">
                                                            - {message.snippet}
                                                        </span>
                                                    </div>

                                                    {/* Attachment icon */}
                                                    {message.has_attachments && (
                                                        <div className="text-slate-400 shrink-0" title="Has attachment">
                                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                            </svg>
                                                        </div>
                                                    )}

                                                    {/* Date / Hover Actions */}
                                                    <div className="relative shrink-0 text-right min-w-[70px]">
                                                        {/* Static Date */}
                                                        <span className="group-hover:hidden text-[11px] md:text-xs text-slate-500 font-medium whitespace-nowrap">
                                                            {message.received_at_display}
                                                        </span>

                                                        {/* Hover Action Buttons (Gmail style) */}
                                                        <div className="hidden group-hover:flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                                                            <button
                                                                type="button"
                                                                onClick={(e) => handleRowDelete(message, e)}
                                                                className="p-1 text-slate-500 hover:text-rose-600 rounded hover:bg-rose-50 transition"
                                                                title={selectedFolder === 'trash' ? 'Delete forever' : 'Move to trash'}
                                                            >
                                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                </svg>
                                                            </button>

                                                            <button
                                                                type="button"
                                                                onClick={(e) => handleRowMarkUnread(message, e)}
                                                                className="p-1 text-slate-500 hover:text-teal-600 rounded hover:bg-teal-50 transition"
                                                                title="Mark as unread"
                                                            >
                                                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <div className="h-64 flex flex-col items-center justify-center text-center p-6">
                                            <div className="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-3">
                                                <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                </svg>
                                            </div>
                                            <div className="text-sm font-semibold text-slate-700">No emails found</div>
                                            <div className="text-xs text-slate-400 mt-1">
                                                {searchQuery
                                                    ? `No matches for "${searchQuery}"`
                                                    : `Your ${selectedFolderLabel} folder is currently empty.`}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </main>
                </div>
            </div>

            {/* ========================================================================= */}
            {/* 4. Gmail Floating / Docked Compose Modal Window                            */}
            {/* ========================================================================= */}
            {composeMode && (
                <div
                    className={`fixed bottom-0 right-4 md:right-8 z-50 bg-white rounded-t-2xl shadow-2xl border border-slate-300 transition-all flex flex-col overflow-hidden ${
                        composeMaximized
                            ? 'top-8 left-8 right-8 bottom-0 w-auto'
                            : composeMinimized
                            ? 'w-72 h-12'
                            : 'w-full sm:w-[580px] max-w-[96vw] h-[520px]'
                    }`}
                >
                    {/* Header Bar */}
                    <div
                        className="flex items-center justify-between px-4 py-2.5 bg-slate-900 text-white cursor-pointer select-none shrink-0"
                        onClick={() => {
                            if (composeMinimized) setComposeMinimized(false);
                        }}
                    >
                        <span className="text-xs md:text-sm font-semibold truncate">
                            {composeMode === 'forward'
                                ? 'Forward message'
                                : composeMode === 'reply'
                                ? 'Reply'
                                : composeMode === 'reply_all'
                                ? 'Reply to all'
                                : composeForm.subject
                                ? composeForm.subject
                                : 'New Message'}
                        </span>

                        <div className="flex items-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                            {/* Minimize */}
                            <button
                                type="button"
                                onClick={() => setComposeMinimized(!composeMinimized)}
                                className="p-1 hover:bg-slate-700 rounded text-slate-300 hover:text-white transition"
                                title={composeMinimized ? 'Restore' : 'Minimize'}
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M20 12H4" />
                                </svg>
                            </button>

                            {/* Maximize */}
                            {!composeMinimized && (
                                <button
                                    type="button"
                                    onClick={() => setComposeMaximized(!composeMaximized)}
                                    className="p-1 hover:bg-slate-700 rounded text-slate-300 hover:text-white transition"
                                    title={composeMaximized ? 'Exit full screen' : 'Full screen'}
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                    </svg>
                                </button>
                            )}

                            {/* Close */}
                            <button
                                type="button"
                                onClick={handleComposeClose}
                                className="p-1 hover:bg-slate-700 rounded text-slate-300 hover:text-white transition"
                                title="Close"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {/* Compose Body (only when not minimized) */}
                    {!composeMinimized && (
                        <form onSubmit={handleComposeSend} className="flex-1 flex flex-col min-h-0 bg-white">
                            {/* To Field */}
                            <div className="flex items-center gap-2 px-4 py-2 border-b border-slate-200">
                                <span className="text-xs font-semibold text-slate-400 w-12 shrink-0">To</span>
                                <input
                                    type="text"
                                    value={composeForm.to}
                                    onChange={(e) => setComposeForm((prev) => ({ ...prev, to: e.target.value }))}
                                    placeholder="recipients@example.com"
                                    className="flex-1 text-xs md:text-sm text-slate-800 border-0 p-0 focus:outline-none focus:ring-0"
                                    required
                                />
                                <div className="flex items-center gap-2 text-xs font-medium text-slate-500 shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => setShowCc(!showCc)}
                                        className={`hover:text-teal-600 ${showCc ? 'text-teal-600 font-bold' : ''}`}
                                    >
                                        Cc
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowBcc(!showBcc)}
                                        className={`hover:text-teal-600 ${showBcc ? 'text-teal-600 font-bold' : ''}`}
                                    >
                                        Bcc
                                    </button>
                                </div>
                            </div>

                            {/* Optional Cc Field */}
                            {showCc && (
                                <div className="flex items-center gap-2 px-4 py-2 border-b border-slate-200">
                                    <span className="text-xs font-semibold text-slate-400 w-12 shrink-0">Cc</span>
                                    <input
                                        type="text"
                                        value={composeForm.cc}
                                        onChange={(e) => setComposeForm((prev) => ({ ...prev, cc: e.target.value }))}
                                        placeholder="cc@example.com"
                                        className="flex-1 text-xs md:text-sm text-slate-800 border-0 p-0 focus:outline-none focus:ring-0"
                                    />
                                </div>
                            )}

                            {/* Optional Bcc Field */}
                            {showBcc && (
                                <div className="flex items-center gap-2 px-4 py-2 border-b border-slate-200">
                                    <span className="text-xs font-semibold text-slate-400 w-12 shrink-0">Bcc</span>
                                    <input
                                        type="text"
                                        value={composeForm.bcc}
                                        onChange={(e) => setComposeForm((prev) => ({ ...prev, bcc: e.target.value }))}
                                        placeholder="bcc@example.com"
                                        className="flex-1 text-xs md:text-sm text-slate-800 border-0 p-0 focus:outline-none focus:ring-0"
                                    />
                                </div>
                            )}

                            {/* Subject Field */}
                            <div className="flex items-center gap-2 px-4 py-2 border-b border-slate-200">
                                <input
                                    type="text"
                                    value={composeForm.subject}
                                    onChange={(e) => setComposeForm((prev) => ({ ...prev, subject: e.target.value }))}
                                    placeholder="Subject"
                                    className="w-full text-xs md:text-sm font-medium text-slate-800 border-0 p-0 focus:outline-none focus:ring-0"
                                    required
                                />
                            </div>

                            {/* Body Textarea */}
                            <div className="flex-1 p-4 min-h-0">
                                <textarea
                                    value={composeForm.body}
                                    onChange={(e) => setComposeForm((prev) => ({ ...prev, body: e.target.value }))}
                                    onKeyDown={(e) => {
                                        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                                            handleComposeSend(e);
                                        }
                                    }}
                                    placeholder="Write your email here... (Press Ctrl+Enter to send)"
                                    className="w-full h-full text-sm text-slate-800 placeholder-slate-400 border-0 p-0 focus:outline-none focus:ring-0 resize-none font-sans leading-relaxed"
                                    required
                                    autoFocus
                                ></textarea>
                            </div>

                            {/* Error if any */}
                            {pageErrors?.compose && (
                                <div className="px-4 py-1.5 bg-rose-50 text-xs text-rose-600 font-medium">
                                    {pageErrors.compose}
                                </div>
                            )}

                            {/* Footer Action Bar */}
                            <div className="flex items-center justify-between px-4 py-3 border-t border-slate-200 bg-slate-50 shrink-0">
                                <div className="flex items-center gap-2">
                                    {/* Send Button */}
                                    <button
                                        type="submit"
                                        disabled={composeSending}
                                        className="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-teal-600 hover:bg-teal-700 active:bg-teal-800 text-white font-bold text-xs shadow-md disabled:opacity-50 transition"
                                    >
                                        <span>{composeSending ? 'Sending...' : 'Send'}</span>
                                        <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                        </svg>
                                    </button>

                                    {/* Save Draft */}
                                    <button
                                        type="button"
                                        onClick={handleComposeDraft}
                                        disabled={composeSending}
                                        className="px-3.5 py-2 rounded-full border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 font-semibold text-xs transition disabled:opacity-50"
                                    >
                                        Save Draft
                                    </button>
                                </div>

                                {/* Discard / Trash */}
                                <button
                                    type="button"
                                    onClick={handleComposeClose}
                                    className="p-2 rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                    title="Discard draft"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            )}
        </>
    );
}
