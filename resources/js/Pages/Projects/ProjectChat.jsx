import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

const EMOJIS = ['\u{1F44D}', '\u2764\uFE0F', '\u{1F602}', '\u{1F62E}', '\u{1F64F}'];

const statusBadgeClass = (status) => {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'active' || normalized === 'online') {
        return 'bg-emerald-500';
    }
    if (normalized === 'away') {
        return 'bg-amber-400';
    }
    return 'bg-slate-300';
};

const escapeRegex = (value) => String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const messageContainsMention = (message, label) => {
    const normalizedLabel = String(label || '').trim();
    if (!normalizedLabel) {
        return false;
    }
    const pattern = new RegExp(`(^|\\s)@${escapeRegex(normalizedLabel)}(?=\\s|$|[\\p{P}])`, 'iu');
    return pattern.test(String(message || ''));
};

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
const COMPOSER_MAX_HEIGHT = 144;

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
    mentionables = [],
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
    const [attachmentPreviewUrl, setAttachmentPreviewUrl] = useState('');
    const [selectedMentions, setSelectedMentions] = useState([]);
    const [mentionMenu, setMentionMenu] = useState({
        open: false,
        start: -1,
        cursor: 0,
        query: '',
        activeIndex: 0,
    });
    const [notice, setNotice] = useState('');
    const [busy, setBusy] = useState(false);
    const messageViewportRef = useRef(null);
    const fileInputRef = useRef(null);
    const composerRef = useRef(null);

    const replyTarget = useMemo(
        () => items.find((row) => Number(row.id || 0) === Number(replyToId || 0)) || null,
        [items, replyToId]
    );

    const latestId = useMemo(() => {
        if (!Array.isArray(items) || items.length === 0) {
            return 0;
        }

        return Number(items[items.length - 1]?.id || 0);
    }, [items]);
    const safeParticipants = Array.isArray(participants) ? participants : [];
    const safeMentionables = Array.isArray(mentionables) ? mentionables : [];
    const pinnedItem = useMemo(
        () => items.find((row) => Boolean(row?.is_pinned)) || null,
        [items]
    );
    const mentionCandidates = useMemo(() => {
        if (!mentionMenu.open) {
            return [];
        }

        const query = String(mentionMenu.query || '').trim().toLowerCase();
        const filtered = safeMentionables.filter((entry) => {
            const label = String(entry?.label || '').toLowerCase();
            return query === '' || label.includes(query);
        });

        const people = filtered.filter((entry) => String(entry?.type || '') !== 'project_task');
        const tasks = filtered.filter((entry) => String(entry?.type || '') === 'project_task');
        return [...people, ...tasks].slice(0, 12);
    }, [mentionMenu.open, mentionMenu.query, safeMentionables]);
    const mentionPeople = useMemo(
        () => mentionCandidates.filter((entry) => String(entry?.type || '') !== 'project_task'),
        [mentionCandidates]
    );
    const mentionTasks = useMemo(
        () => mentionCandidates.filter((entry) => String(entry?.type || '') === 'project_task'),
        [mentionCandidates]
    );

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

    const resizeComposer = () => {
        const el = composerRef.current;
        if (!el) {
            return;
        }

        el.style.height = 'auto';
        const nextHeight = Math.min(el.scrollHeight, COMPOSER_MAX_HEIGHT);
        el.style.height = `${nextHeight}px`;
        el.style.overflowY = el.scrollHeight > COMPOSER_MAX_HEIGHT ? 'auto' : 'hidden';
    };

    const updateMentionMenuState = (nextText, cursor) => {
        const text = String(nextText || '');
        const caret = Math.max(0, Number(cursor || 0));
        const left = text.slice(0, caret);
        const tokenMatch = left.match(/(^|\s)@([^\s@]*)$/u);

        if (!tokenMatch) {
            setMentionMenu((prev) => ({ ...prev, open: false, start: -1, query: '', cursor: caret, activeIndex: 0 }));
            return;
        }

        const token = tokenMatch[0] || '';
        const atPos = left.length - token.length + token.lastIndexOf('@');
        const query = tokenMatch[2] || '';
        setMentionMenu((prev) => ({
            ...prev,
            open: true,
            start: atPos,
            cursor: caret,
            query,
            activeIndex: 0,
        }));
    };

    const syncSelectedMentions = (nextBody) => {
        setSelectedMentions((prev) => prev.filter((mention) => messageContainsMention(nextBody, mention?.label)));
    };

    const applyMentionSelection = (entry) => {
        if (!entry || mentionMenu.start < 0) {
            return;
        }

        const label = String(entry.label || '').trim();
        if (!label) {
            return;
        }

        const mentionText = `@${label} `;
        const before = body.slice(0, mentionMenu.start);
        const after = body.slice(mentionMenu.cursor);
        const nextBody = `${before}${mentionText}${after}`;
        const nextCursor = before.length + mentionText.length;

        setBody(nextBody);
        setSelectedMentions((prev) => {
            const key = `${entry.type}:${entry.id}`;
            const withoutCurrent = prev.filter((row) => `${row.type}:${row.id}` !== key);
            return [...withoutCurrent, { type: entry.type, id: entry.id, label: entry.label }];
        });
        setMentionMenu((prev) => ({ ...prev, open: false, start: -1, query: '', cursor: nextCursor, activeIndex: 0 }));

        requestAnimationFrame(() => {
            if (composerRef.current) {
                composerRef.current.focus();
                composerRef.current.setSelectionRange(nextCursor, nextCursor);
            }
        });
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
            const effectiveMentions = selectedMentions.filter((mention) => messageContainsMention(trimmed, mention?.label));
            if (effectiveMentions.length > 0) {
                formData.append('mentions', JSON.stringify(effectiveMentions));
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
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            setSelectedMentions([]);
            setMentionMenu((prev) => ({ ...prev, open: false, start: -1, query: '', cursor: 0, activeIndex: 0 }));
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

    useEffect(() => {
        const viewport = messageViewportRef.current;
        if (!viewport) {
            return;
        }
        viewport.scrollTop = viewport.scrollHeight;
    }, [items.length]);

    useEffect(() => {
        if (!mentionMenu.open) {
            return;
        }
        if (mentionCandidates.length === 0) {
            setMentionMenu((prev) => ({ ...prev, open: false, activeIndex: 0 }));
            return;
        }
        if (mentionMenu.activeIndex >= mentionCandidates.length) {
            setMentionMenu((prev) => ({ ...prev, activeIndex: 0 }));
        }
    }, [mentionCandidates.length, mentionMenu.activeIndex, mentionMenu.open]);

    useEffect(() => {
        if (!attachmentFile || !String(attachmentFile?.type || '').startsWith('image/')) {
            setAttachmentPreviewUrl('');
            return undefined;
        }

        const preview = URL.createObjectURL(attachmentFile);
        setAttachmentPreviewUrl(preview);

        return () => {
            URL.revokeObjectURL(preview);
        };
    }, [attachmentFile]);

    useEffect(() => {
        resizeComposer();
    }, [body]);

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-4">
                <a
                    href={routes?.back || '#'}
                    data-native="true"
                    className="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                >
                    Back
                </a>
            </div>

            <div className="h-[calc(100dvh-9rem)] min-h-[30rem] w-full max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_16px_40px_rgba(15,23,42,0.08)] sm:h-[calc(100dvh-10rem)]">
                <div className="grid h-full min-w-0 lg:grid-cols-[minmax(0,1fr)_19rem]">
                    <section className="flex h-full min-h-0 min-w-0 flex-col">
                        <header className="flex min-w-0 items-center gap-3 overflow-hidden border-b border-slate-200 bg-[#f0f2f5] px-4 py-3">
                            <div className="flex min-w-0 flex-1 items-center gap-3">
                                <div className="grid h-10 w-10 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white">
                                    {String(project?.name || 'P').charAt(0).toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-slate-800">{project?.name || 'Project Chat'}</p>
                                    <p className="truncate text-[11px] text-slate-500">{safeParticipants.length} participant(s)</p>
                                </div>
                            </div>
                            <div className="ml-auto flex shrink-0 items-center gap-2">
                                <span className="hidden text-[11px] text-slate-500 sm:inline">{items.length} messages</span>
                                <button
                                    type="button"
                                    onClick={fetchMessages}
                                    disabled={busy}
                                    className="rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
                                    title="Refresh messages"
                                >
                                    <span aria-hidden="true">&#8635;</span>
                                </button>
                            </div>
                        </header>

                        {(pinnedItem || pinnedSummary?.summary) ? (
                            <div className="border-b border-amber-200 bg-amber-50/90 px-4 py-2">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-amber-700">Pinned Message</p>
                                        <p className="truncate text-xs text-amber-900">
                                            {String(
                                                pinnedItem?.message
                                                    || pinnedItem?.reply_to_message_text
                                                    || pinnedSummary?.summary
                                                    || ''
                                            )}
                                        </p>
                                    </div>
                                    {pinnedItem?.id ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const node = document.getElementById(`project-chat-message-${pinnedItem.id}`);
                                                node?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            }}
                                            className="rounded-full border border-amber-300 bg-white px-2 py-1 text-[11px] font-semibold text-amber-700 hover:bg-amber-100"
                                        >
                                            Open
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                        ) : null}

                        <div
                            ref={messageViewportRef}
                            className="flex-1 overflow-x-hidden overflow-y-auto bg-[#efeae2] bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.35)_0%,rgba(255,255,255,0)_60%)] px-3 py-4 sm:px-5"
                        >
                            <div className="space-y-3">
                                {items.length === 0 ? (
                                    <div className="mx-auto max-w-sm rounded-xl border border-slate-200 bg-white/80 px-4 py-3 text-center text-sm text-slate-600">
                                        No messages yet. Start the conversation.
                                    </div>
                                ) : items.map((item) => {
                                    const isMine = Boolean(item.can_edit);
                                    const reactions = Array.isArray(item.reactions) ? item.reactions : [];
                                    return (
                                        <div
                                            key={item.id}
                                            id={`project-chat-message-${item.id}`}
                                            className={`flex min-w-0 ${isMine ? 'justify-end' : 'justify-start'}`}
                                        >
                                            <div className="w-full max-w-[86%] min-w-0 sm:max-w-[75%]">
                                                <div
                                                    className={`rounded-2xl border border-black/5 px-3 py-2 shadow-sm ${
                                                        isMine
                                                            ? 'rounded-br-md bg-[#d9fdd3] text-slate-800'
                                                            : 'rounded-bl-md bg-white text-slate-800'
                                                    }`}
                                                >
                                                    <div className="mb-1 flex items-center gap-2 text-[11px] text-slate-500">
                                                        <span className="font-semibold text-slate-700">{item.author_name || 'User'}</span>
                                                        <span>&bull;</span>
                                                        <span>{item.author_type_label || 'User'}</span>
                                                        {item.is_pinned ? (
                                                            <>
                                                                <span>&bull;</span>
                                                                <span className="font-semibold text-amber-700">Pinned</span>
                                                            </>
                                                        ) : null}
                                                    </div>

                                                    {item.reply_to_message_id ? (
                                                        <div
                                                            className={`mb-1 rounded-md px-2 py-1 text-xs ${
                                                                isMine ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-600'
                                                            }`}
                                                        >
                                                            Reply to #{item.reply_to_message_id}: {item.reply_to_message_text || '--'}
                                                        </div>
                                                    ) : null}
                                                    {item.message ? (
                                                        <div className="whitespace-pre-line break-all text-sm leading-relaxed text-slate-800">{item.message}</div>
                                                    ) : null}

                                                    {item.attachment_url ? (
                                                        <div className="mt-2">
                                                            {item.attachment_is_image ? (
                                                                <a href={item.attachment_url} target="_blank" rel="noopener" className="block">
                                                                    <img
                                                                        src={item.attachment_url}
                                                                        alt={item.attachment_name || 'Attachment preview'}
                                                                        className="max-h-64 w-full rounded-lg border border-black/10 object-cover"
                                                                        loading="lazy"
                                                                    />
                                                                </a>
                                                            ) : null}
                                                            <a
                                                                href={item.attachment_url}
                                                                target="_blank"
                                                                rel="noopener"
                                                                className="mt-1 inline-flex max-w-full break-all rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                            >
                                                                {item.attachment_name || 'Attachment'}
                                                            </a>
                                                        </div>
                                                    ) : null}

                                                    <div className="mt-1 text-right text-[11px] text-slate-500">{item.created_at_display || '--'}</div>
                                                </div>

                                                <div className={`mt-1 flex flex-wrap items-center gap-1.5 ${isMine ? 'justify-end' : 'justify-start'}`}>
                                                    <button
                                                        type="button"
                                                        onClick={() => setReplyToId(item.id)}
                                                        className="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        Reply
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => togglePin(item)}
                                                        className="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        {item.is_pinned ? 'Unpin' : 'Pin'}
                                                    </button>
                                                    {item.can_edit ? (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => updateMessage(item)}
                                                                className="rounded-full border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                                                            >
                                                                Edit
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => deleteMessage(item)}
                                                                className="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-100"
                                                            >
                                                                Delete
                                                            </button>
                                                        </>
                                                    ) : null}
                                                </div>

                                                <div className={`mt-1 flex flex-wrap items-center gap-1 ${isMine ? 'justify-end' : 'justify-start'}`}>
                                                    {EMOJIS.map((emoji) => {
                                                        const reaction = reactions.find((row) => row?.emoji === emoji);
                                                        const count = Number(reaction?.count || 0);
                                                        const reacted = Boolean(reaction?.reacted);
                                                        return (
                                                            <button
                                                                key={`${item.id}-${emoji}`}
                                                                type="button"
                                                                onClick={() => toggleReaction(item, emoji)}
                                                                className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${
                                                                    reacted
                                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                                                        : 'border-slate-300 bg-white text-slate-700'
                                                                }`}
                                                            >
                                                                {emoji} {count > 0 ? count : ''}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        {canPost ? (
                            <form onSubmit={submit} className="min-w-0 overflow-hidden border-t border-slate-200 bg-[#f0f2f5] px-3 py-3 sm:px-4">
                                {replyToId > 0 ? (
                                    <div className="mb-2 flex items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-600">
                                        <span>
                                            Replying to #{replyToId}
                                            {replyTarget?.message ? `: ${String(replyTarget.message).slice(0, 90)}` : ''}
                                        </span>
                                        <button type="button" onClick={() => setReplyToId(0)} className="font-semibold text-slate-700">
                                            Clear
                                        </button>
                                    </div>
                                ) : null}
                                {attachmentPreviewUrl ? (
                                    <div className="mb-2 flex min-w-0 items-start justify-between gap-3 rounded-lg border border-slate-300 bg-white p-2">
                                        <img
                                            src={attachmentPreviewUrl}
                                            alt="Selected preview"
                                            className="h-16 w-16 rounded-md border border-slate-200 object-cover"
                                        />
                                        <div className="min-w-0 flex-1 text-xs text-slate-600">
                                            <p className="truncate font-semibold text-slate-700">{attachmentFile?.name || 'Selected image'}</p>
                                            <p>Image will be sent with your message.</p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setAttachmentFile(null);
                                                if (fileInputRef.current) {
                                                    fileInputRef.current.value = '';
                                                }
                                            }}
                                            className="text-xs font-semibold text-slate-600 hover:text-slate-800"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                ) : null}
                                {mentionMenu.open && mentionCandidates.length > 0 ? (
                                    <div className="mb-2 max-h-56 overflow-y-auto rounded-lg border border-slate-300 bg-white shadow-lg">
                                        {mentionPeople.length > 0 ? (
                                            <div className="border-b border-slate-100 p-2">
                                                <p className="px-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">People</p>
                                                <div className="mt-1 space-y-1">
                                                    {mentionPeople.map((entry) => {
                                                        const index = mentionCandidates.findIndex((row) => row?.type === entry?.type && row?.id === entry?.id);
                                                        const active = mentionMenu.activeIndex === index;
                                                        return (
                                                            <button
                                                                key={`${entry.type}:${entry.id}`}
                                                                type="button"
                                                                onMouseDown={(event) => {
                                                                    event.preventDefault();
                                                                    applyMentionSelection(entry);
                                                                }}
                                                                className={`flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm ${
                                                                    active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-100'
                                                                }`}
                                                            >
                                                                <span className="truncate">{entry.label}</span>
                                                                <span className="ml-2 shrink-0 text-xs text-slate-400">{entry.role || 'User'}</span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ) : null}
                                        {mentionTasks.length > 0 ? (
                                            <div className="p-2">
                                                <p className="px-2 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-500">Tasks</p>
                                                <div className="mt-1 space-y-1">
                                                    {mentionTasks.map((entry) => {
                                                        const index = mentionCandidates.findIndex((row) => row?.type === entry?.type && row?.id === entry?.id);
                                                        const active = mentionMenu.activeIndex === index;
                                                        return (
                                                            <button
                                                                key={`${entry.type}:${entry.id}`}
                                                                type="button"
                                                                onMouseDown={(event) => {
                                                                    event.preventDefault();
                                                                    applyMentionSelection(entry);
                                                                }}
                                                                className={`flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm ${
                                                                    active ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-100'
                                                                }`}
                                                            >
                                                                <span className="truncate">{entry.label}</span>
                                                                <span className="ml-2 shrink-0 text-xs text-slate-400">{entry.role || 'Task'}</span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        ) : null}
                                    </div>
                                ) : null}
                                <div className="flex min-w-0 max-w-full items-end gap-2">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        onChange={(event) => setAttachmentFile(event.target.files?.[0] || null)}
                                        className="hidden"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => fileInputRef.current?.click()}
                                        className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-lg text-slate-600 hover:bg-slate-100"
                                        title="Attach file"
                                    >
                                        +
                                    </button>
                                    <textarea
                                        ref={composerRef}
                                        value={body}
                                        onChange={(event) => {
                                            const next = event.target.value;
                                            setBody(next);
                                            syncSelectedMentions(next);
                                            updateMentionMenuState(next, event.target.selectionStart);
                                        }}
                                        onClick={(event) => {
                                            updateMentionMenuState(event.currentTarget.value, event.currentTarget.selectionStart);
                                        }}
                                        onKeyUp={(event) => {
                                            updateMentionMenuState(event.currentTarget.value, event.currentTarget.selectionStart);
                                        }}
                                        onKeyDown={(event) => {
                                            if (!mentionMenu.open || mentionCandidates.length === 0) {
                                                return;
                                            }

                                            if (event.key === 'ArrowDown') {
                                                event.preventDefault();
                                                setMentionMenu((prev) => ({
                                                    ...prev,
                                                    activeIndex: (prev.activeIndex + 1) % mentionCandidates.length,
                                                }));
                                                return;
                                            }

                                            if (event.key === 'ArrowUp') {
                                                event.preventDefault();
                                                setMentionMenu((prev) => ({
                                                    ...prev,
                                                    activeIndex: (prev.activeIndex - 1 + mentionCandidates.length) % mentionCandidates.length,
                                                }));
                                                return;
                                            }

                                            if (event.key === 'Enter' || event.key === 'Tab') {
                                                event.preventDefault();
                                                const current = mentionCandidates[mentionMenu.activeIndex] || mentionCandidates[0];
                                                if (current) {
                                                    applyMentionSelection(current);
                                                }
                                                return;
                                            }

                                            if (event.key === 'Escape') {
                                                event.preventDefault();
                                                setMentionMenu((prev) => ({ ...prev, open: false }));
                                            }
                                        }}
                                        rows={1}
                                        placeholder="Type a message"
                                        className="min-h-[2.5rem] min-w-0 flex-1 resize-none rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none"
                                    />
                                    <button
                                        type="submit"
                                        disabled={busy}
                                        className="inline-flex h-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 px-3 sm:px-4 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                        title="Send message"
                                    >
                                        <span className="sm:hidden">&#10148;</span>
                                        <span className="hidden sm:inline">Send</span>
                                    </button>
                                </div>
                                <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <span className="text-xs text-slate-500">{attachmentFile ? `Attached: ${attachmentFile.name}` : notice}</span>
                                    {attachmentFile ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setAttachmentFile(null);
                                                if (fileInputRef.current) {
                                                    fileInputRef.current.value = '';
                                                }
                                            }}
                                            className="text-xs font-semibold text-slate-600 hover:text-slate-800"
                                        >
                                            Remove attachment
                                        </button>
                                    ) : null}
                                </div>
                            </form>
                        ) : (
                            <div className="border-t border-slate-200 bg-[#f0f2f5] px-4 py-3 text-sm text-slate-500">
                                You do not have permission to post in this chat.
                            </div>
                        )}
                    </section>

                    <aside className="hidden overflow-y-auto border-l border-slate-200 bg-slate-50 lg:block">
                        <div className="border-b border-slate-200 px-4 py-3 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Chat Info
                        </div>
                        <div className="space-y-4 p-4">
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <div className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Participants</div>
                                <div className="mt-3 space-y-2">
                                    {safeParticipants.length === 0 ? (
                                        <div className="text-xs text-slate-500">No participants found.</div>
                                    ) : safeParticipants.map((participant) => {
                                        const key = participant?.key || `${participant?.type || 'x'}:${participant?.id || 0}`;
                                        const status = participantStatuses?.[key] || 'offline';
                                        return (
                                            <div key={key} className="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-2 py-1.5">
                                                <span className="truncate text-sm text-slate-700">{participant?.label || 'Participant'}</span>
                                                <span className="inline-flex items-center gap-1 text-[11px] text-slate-500">
                                                    <span className={`h-2 w-2 rounded-full ${statusBadgeClass(status)}`}></span>
                                                    {status}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            {allParticipantsReadUpTo?.message_id ? (
                                <div className="rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-600">
                                    Everyone read up to message #{allParticipantsReadUpTo.message_id}
                                </div>
                            ) : null}

                            {aiReady && routes?.aiSummary ? (
                                <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800">
                                    AI summary is available for this chat.
                                </div>
                            ) : null}
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}
