import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const EMOJIS = ['👍', '❤️', '😂', '😮', '🙏'];

const normalizeItem = (item) => {
    if (!item || typeof item !== 'object') {
        return null;
    }

    const message = item.message && typeof item.message === 'object' ? item.message : item;
    const id = Number(item.id || message.id || 0);
    if (!id) {
        return null;
    }

    return {
        id,
        ...message,
        meta: item.meta || {},
    };
};

const sortById = (rows) => [...rows].sort((a, b) => Number(a.id || 0) - Number(b.id || 0));

const upsertMessage = (rows, next) => {
    const map = new Map(rows.map((row) => [row.id, row]));
    map.set(next.id, next);
    return sortById(Array.from(map.values()));
};

export default function ProjectChat({
    pageTitle = 'Project Chat',
    project = {},
    initialItems = [],
    canPost = false,
    pinnedSummary = {},
    aiReady = false,
    participants = [],
    participantStatuses = {},
    allParticipantsReadUpTo = null,
    routes = {},
}) {
    const { csrf_token: csrfToken } = usePage().props;

    const [items, setItems] = useState(
        sortById((Array.isArray(initialItems) ? initialItems : []).map((item) => normalizeItem(item)).filter(Boolean))
    );
    const [body, setBody] = useState('');
    const [replyToId, setReplyToId] = useState(0);
    const [attachmentFile, setAttachmentFile] = useState(null);
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);

    const latestId = useMemo(() => {
        if (!Array.isArray(items) || items.length === 0) {
            return 0;
        }

        return Number(items[items.length - 1]?.id || 0);
    }, [items]);

    const fetchMessages = async () => {
        if (!routes?.messages) {
            return;
        }

        setBusy(true);
        try {
            const url = `${routes.messages}${routes.messages.includes('?') ? '&' : '?'}limit=100`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice('Unable to refresh messages.');
                return;
            }

            const rows = (Array.isArray(payload?.data?.items) ? payload.data.items : [])
                .map((item) => normalizeItem(item))
                .filter(Boolean);
            setItems(sortById(rows));
            setNotice('');
        } catch (_error) {
            setNotice('Unable to refresh messages.');
        } finally {
            setBusy(false);
        }
    };

    const markRead = async (lastReadId) => {
        if (!routes?.read || !lastReadId) {
            return;
        }

        try {
            await fetch(routes.read, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ last_read_id: lastReadId }),
            });
        } catch (_error) {
            // Best effort.
        }
    };

    const submit = async (event) => {
        event.preventDefault();

        if (!canPost || !routes?.storeMessage || busy) {
            return;
        }

        const trimmed = String(body || '').trim();
        if (!trimmed && !attachmentFile) {
            setNotice('Message cannot be empty.');
            return;
        }

        setBusy(true);
        try {
            const formData = new FormData();
            formData.append('message', trimmed);
            if (attachmentFile) {
                formData.append('attachment', attachmentFile);
            }
            if (replyToId > 0) {
                formData.append('reply_to_message_id', String(replyToId));
            }

            const response = await fetch(routes.storeMessage, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: formData,
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice(payload?.message || 'Unable to send message.');
                return;
            }

            const normalized = normalizeItem(payload?.data?.item);
            if (normalized) {
                setItems((prev) => upsertMessage(prev, normalized));
                await markRead(normalized.id);
            }
            setBody('');
            setReplyToId(0);
            setAttachmentFile(null);
            setNotice(payload?.message || 'Message sent.');
        } catch (_error) {
            setNotice('Unable to send message.');
        } finally {
            setBusy(false);
        }
    };

    const updateMessage = async (item) => {
        if (!item?.routes?.update || busy) {
            return;
        }

        const next = window.prompt('Edit message', item.message || '');
        if (next === null) {
            return;
        }

        setBusy(true);
        try {
            const response = await fetch(item.routes.update, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: next }),
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice(payload?.message || 'Unable to update message.');
                return;
            }

            const normalized = normalizeItem(payload?.data?.item);
            if (normalized) {
                setItems((prev) => upsertMessage(prev, normalized));
            }
            setNotice(payload?.message || 'Message updated.');
        } catch (_error) {
            setNotice('Unable to update message.');
        } finally {
            setBusy(false);
        }
    };

    const deleteMessage = async (item) => {
        if (!item?.routes?.delete || busy) {
            return;
        }
        if (!window.confirm('Delete this message?')) {
            return;
        }

        setBusy(true);
        try {
            const response = await fetch(item.routes.delete, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice(payload?.message || 'Unable to delete message.');
                return;
            }

            setItems((prev) => prev.filter((row) => row.id !== item.id));
            setNotice(payload?.message || 'Message deleted.');
        } catch (_error) {
            setNotice('Unable to delete message.');
        } finally {
            setBusy(false);
        }
    };

    const togglePin = async (item) => {
        if (!item?.routes?.pin || busy) {
            return;
        }

        setBusy(true);
        try {
            const response = await fetch(item.routes.pin, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice(payload?.message || 'Unable to pin message.');
                return;
            }

            const pinnedId = Number(payload?.data?.pinned_message_id || 0);
            setItems((prev) => prev.map((row) => ({
                ...row,
                is_pinned: pinnedId > 0 ? row.id === pinnedId : false,
            })));
            setNotice(payload?.message || 'Pin updated.');
        } catch (_error) {
            setNotice('Unable to pin message.');
        } finally {
            setBusy(false);
        }
    };

    const toggleReaction = async (item, emoji) => {
        if (!item?.routes?.react || busy) {
            return;
        }

        setBusy(true);
        try {
            const response = await fetch(item.routes.react, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ emoji }),
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || !payload?.ok) {
                setNotice(payload?.message || 'Unable to update reaction.');
                return;
            }

            const nextReactions = Array.isArray(payload?.data?.reactions) ? payload.data.reactions : [];
            setItems((prev) => prev.map((row) => (row.id === item.id ? { ...row, reactions: nextReactions } : row)));
        } catch (_error) {
            setNotice('Unable to update reaction.');
        } finally {
            setBusy(false);
        }
    };

    useEffect(() => {
        if (latestId > 0) {
            void markRead(latestId);
        }
    }, [latestId]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex items-center justify-between">
                <a href={routes?.back || '#'} data-native="true" className="text-sm font-semibold text-teal-600 hover:text-teal-700">
                    Back
                </a>
                <div className="text-xs text-slate-500">{project?.name || '--'}</div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                <div className="card p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Project Chat</div>
                        <button type="button" onClick={fetchMessages} disabled={busy} className="rounded-full border border-slate-300 px-3 py-1 text-[11px] font-semibold text-slate-700 disabled:opacity-50">
                            Refresh
                        </button>
                    </div>

                    {pinnedSummary?.summary ? (
                        <div className="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            {pinnedSummary.summary}
                        </div>
                    ) : null}

                    {canPost ? (
                        <form onSubmit={submit} className="mb-4 space-y-2">
                            {replyToId > 0 ? (
                                <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                    <span>Replying to message #{replyToId}</span>
                                    <button type="button" onClick={() => setReplyToId(0)} className="font-semibold text-slate-700">
                                        Clear
                                    </button>
                                </div>
                            ) : null}
                            <textarea
                                value={body}
                                onChange={(event) => setBody(event.target.value)}
                                rows={3}
                                placeholder="Write a message"
                                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm"
                            />
                            <input type="file" onChange={(event) => setAttachmentFile(event.target.files?.[0] || null)} className="w-full text-xs text-slate-600" />
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-500">{notice}</span>
                                <button type="submit" disabled={busy} className="rounded-full border border-teal-200 px-4 py-1.5 text-xs font-semibold text-teal-700 disabled:opacity-50">
                                    Send
                                </button>
                            </div>
                        </form>
                    ) : null}

                    <div className="space-y-3">
                        {items.length === 0 ? (
                            <div className="text-sm text-slate-500">No messages yet.</div>
                        ) : items.map((item) => (
                            <div key={item.id} className="rounded-xl border border-slate-200 bg-white p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="text-xs text-slate-500">
                                        {item.author_name || 'User'} ({item.author_type_label || 'User'}) | {item.created_at_display || '--'}
                                    </div>
                                    {item.is_pinned ? <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">Pinned</span> : null}
                                </div>

                                {item.reply_to_message_id ? (
                                    <div className="mt-1 rounded-md bg-slate-50 px-2 py-1 text-xs text-slate-500">
                                        Reply to #{item.reply_to_message_id}: {item.reply_to_message_text || '--'}
                                    </div>
                                ) : null}

                                {item.message ? <div className="mt-1 whitespace-pre-line text-sm text-slate-800">{item.message}</div> : null}
                                {item.attachment_url ? (
                                    <a href={item.attachment_url} target="_blank" rel="noopener" className="mt-1 inline-flex text-xs font-semibold text-teal-600">
                                        {item.attachment_name || 'Attachment'}
                                    </a>
                                ) : null}

                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <button type="button" onClick={() => setReplyToId(item.id)} className="rounded border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">Reply</button>
                                    <button type="button" onClick={() => togglePin(item)} className="rounded border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                        {item.is_pinned ? 'Unpin' : 'Pin'}
                                    </button>
                                    {item.can_edit ? (
                                        <>
                                            <button type="button" onClick={() => updateMessage(item)} className="rounded border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">Edit</button>
                                            <button type="button" onClick={() => deleteMessage(item)} className="rounded border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700">Delete</button>
                                        </>
                                    ) : null}
                                </div>

                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    {EMOJIS.map((emoji) => {
                                        const reaction = (Array.isArray(item.reactions) ? item.reactions : []).find((row) => row?.emoji === emoji);
                                        const count = Number(reaction?.count || 0);
                                        const reacted = Boolean(reaction?.reacted);
                                        return (
                                            <button
                                                key={`${item.id}-${emoji}`}
                                                type="button"
                                                onClick={() => toggleReaction(item, emoji)}
                                                className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${reacted ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-700'}`}
                                            >
                                                {emoji} {count > 0 ? count : ''}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="card p-4">
                        <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Participants</div>
                        <div className="mt-2 space-y-1 text-sm text-slate-700">
                            {(Array.isArray(participants) ? participants : []).map((participant) => {
                                const key = participant?.key || `${participant?.type || 'x'}:${participant?.id || 0}`;
                                const status = participantStatuses?.[key] || 'offline';
                                return (
                                    <div key={key} className="flex items-center justify-between gap-2">
                                        <span>{participant?.label || 'Participant'}</span>
                                        <span className="text-[11px] text-slate-500">{status}</span>
                                    </div>
                                );
                            })}
                            {(Array.isArray(participants) ? participants : []).length === 0 ? (
                                <div className="text-xs text-slate-500">No participants found.</div>
                            ) : null}
                        </div>
                    </div>

                    {allParticipantsReadUpTo?.message_id ? (
                        <div className="card p-4 text-xs text-slate-600">
                            Everyone read up to message #{allParticipantsReadUpTo.message_id}
                        </div>
                    ) : null}

                    {aiReady && routes?.aiSummary ? (
                        <div className="card p-4 text-xs text-slate-500">AI summary available.</div>
                    ) : null}
                </div>
            </div>
        </>
    );
}
