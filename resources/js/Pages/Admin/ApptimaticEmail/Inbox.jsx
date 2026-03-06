import React, { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';

const reloadTargets = ['messages', 'selected_message', 'thread_messages', 'unread_count', 'sync_meta', 'history_email_filter', 'mailbox_switch'];

const rowClassName = (message) => {
    const base = 'group flex items-start justify-between gap-3 px-4 py-3 transition';

    if (message?.is_selected) {
        return `${base} bg-teal-50/80`;
    }

    return `${base} bg-white hover:bg-slate-50`;
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
}) {
    const page = usePage();
    const pageErrors = page?.props?.errors || {};
    const flashStatus = page?.props?.flash?.status || '';
    const isMasterAdmin = Boolean(page?.props?.permissions?.is_master_admin);
    const emailFilterEnabled = Boolean(isMasterAdmin && history_email_filter?.enabled);
    const selectedHistoryEmail = String(history_email_filter?.selected || '');
    const emailFilterOptions = Array.isArray(history_email_filter?.options) ? history_email_filter.options : [];
    const mailboxSwitchEnabled = Boolean(isMasterAdmin && mailbox_switch?.enabled);
    const mailboxSwitchOptions = Array.isArray(mailbox_switch?.options) ? mailbox_switch.options : [];
    const mailboxSwitchCurrentEmail = String(mailbox_switch?.current_email || '');
    const firstMailboxSwitchEmail = String(mailboxSwitchOptions[0]?.email || '');
    const [mailboxSwitchEmail, setMailboxSwitchEmail] = useState(mailboxSwitchCurrentEmail || firstMailboxSwitchEmail);
    const selectedThreadMessage = thread_messages.find((thread) => Boolean(thread?.is_selected)) || thread_messages[thread_messages.length - 1] || null;
    const previousThreadMessages = selectedThreadMessage
        ? thread_messages.filter((thread) => String(thread?.id || '') !== String(selectedThreadMessage?.id || ''))
        : [];
    const [replyForm, setReplyForm] = useState({
        to: '',
        cc: '',
        subject: '',
        body: '',
    });
    const [replySending, setReplySending] = useState(false);
    const isCurrentMailboxSelected = mailboxSwitchEmail !== '' && mailboxSwitchEmail === mailboxSwitchCurrentEmail;

    const handleHistoryEmailChange = (event) => {
        const nextHistoryEmail = String(event?.target?.value || '');
        if (!routes?.inbox) {
            return;
        }

        router.get(
            routes.inbox,
            nextHistoryEmail ? { history_email: nextHistoryEmail } : {},
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

    const handleReplySubmit = (event) => {
        event.preventDefault();
        if (!routes?.reply || !selected_message?.id || replySending) {
            return;
        }

        setReplySending(true);

        router.post(
            routes.reply,
            {
                message_id: String(selected_message.id),
                to: replyForm.to,
                cc: replyForm.cc,
                subject: replyForm.subject,
                body: replyForm.body,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setReplySending(false),
                onSuccess: () => {
                    setReplyForm((prev) => ({ ...prev, body: '' }));
                    syncNow();
                },
            },
        );
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
        if (!selected_message) {
            setReplyForm({ to: '', cc: '', subject: '', body: '' });
            return;
        }

        const sender = String(selectedThreadMessage?.sender_email || selected_message.sender_email || '').trim();
        const rawSubject = String(selected_message.subject || '').trim();
        const subject = rawSubject.toLowerCase().startsWith('re:') ? rawSubject : `Re: ${rawSubject}`;

        setReplyForm((prev) => ({
            ...prev,
            to: sender,
            subject,
            body: '',
        }));
    }, [selected_message?.id, selectedThreadMessage?.id]);

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
                            {routes?.manage ? (
                                <a
                                    href={routes.manage}
                                    data-native="true"
                                    className="rounded-full border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-teal-300 hover:text-teal-600"
                                >
                                    Manage Mailboxes
                                </a>
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

                <div className="grid min-h-[65vh] grid-cols-1 2xl:grid-cols-[minmax(20rem,38%)_1fr]">
                    <section className="border-b border-slate-200 bg-white 2xl:border-b-0 2xl:border-r">
                        {messages.length > 0 ? (
                            <div className="h-full">
                                <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Inbox</div>
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
                                {selectedHistoryEmail ? `No emails found for ${selectedHistoryEmail}.` : 'No emails yet.'}
                            </div>
                        )}
                    </section>

                    <section className="bg-slate-50/60">
                        {!selected_message ? (
                            <div className="grid h-full min-h-[18rem] place-items-center px-6 py-10 text-center">
                                <div>
                                    <div className="text-sm font-semibold text-slate-700">Select a message</div>
                                    <div className="mt-1 text-sm text-slate-500">Open any inbox item to view the thread.</div>
                                </div>
                            </div>
                        ) : (
                            <div className="h-full overflow-y-auto">
                                <div className="space-y-4 px-4 py-4 md:px-6">
                                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                        <div className="mb-4 flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                            <h3 className="text-xl font-semibold text-slate-900">{selected_message.subject}</h3>
                                            <div className="text-xs text-slate-500">
                                                {selected_message.thread_count} message(s)
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
                                    </article>

                                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                        <div className="mb-4 flex items-center justify-between gap-3">
                                            <h4 className="text-sm font-semibold uppercase tracking-[0.2em] text-slate-600">Reply</h4>
                                            {flashStatus ? <div className="text-xs text-emerald-600">{flashStatus}</div> : null}
                                        </div>
                                        <form onSubmit={handleReplySubmit} className="space-y-3">
                                            <div className="grid gap-3 md:grid-cols-2">
                                                <label className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                    To
                                                    <input
                                                        type="text"
                                                        value={replyForm.to}
                                                        onChange={(e) => setReplyForm((prev) => ({ ...prev, to: e.target.value }))}
                                                        className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                        placeholder="recipient@example.com"
                                                        required
                                                    />
                                                </label>
                                                <label className="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                    Cc
                                                    <input
                                                        type="text"
                                                        value={replyForm.cc}
                                                        onChange={(e) => setReplyForm((prev) => ({ ...prev, cc: e.target.value }))}
                                                        className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                        placeholder="cc1@example.com, cc2@example.com"
                                                    />
                                                </label>
                                            </div>

                                            <label className="block text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                Subject
                                                <input
                                                    type="text"
                                                    value={replyForm.subject}
                                                    onChange={(e) => setReplyForm((prev) => ({ ...prev, subject: e.target.value }))}
                                                    className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-teal-400 focus:outline-none"
                                                    required
                                                />
                                            </label>

                                            <label className="block text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                                Message
                                                <textarea
                                                    value={replyForm.body}
                                                    onChange={(e) => setReplyForm((prev) => ({ ...prev, body: e.target.value }))}
                                                    className="mt-1 h-36 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm leading-6 text-slate-700 focus:border-teal-400 focus:outline-none"
                                                    placeholder="Write your reply..."
                                                    required
                                                />
                                            </label>

                                            {pageErrors?.reply ? (
                                                <div className="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                                    {pageErrors.reply}
                                                </div>
                                            ) : null}

                                            <div className="flex justify-end">
                                                <button
                                                    type="submit"
                                                    disabled={replySending}
                                                    className="rounded-full border border-teal-500 bg-teal-500 px-4 py-2 text-xs font-semibold text-white transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    {replySending ? 'Sending...' : 'Send Reply'}
                                                </button>
                                            </div>
                                        </form>
                                    </article>

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
