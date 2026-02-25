@extends($layout ?? 'layouts.admin')

@section('title', 'Task Chat')
@section('page-title', 'Task Chat')

@php
    $routePrefix = $routePrefix ?? (function () {
        $routeName = request()->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return 'admin';
        }

        return explode('.', $routeName)[0] ?: 'admin';
    })();
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Task Chat</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $task->title }}</div>
            <div class="text-sm text-slate-500">Project: {{ $project->name }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
    </div>

    <style>
        .wa-chat-canvas {
            background-color: #efeae2;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.34) 0, rgba(255, 255, 255, 0.34) 1.2px, transparent 1.2px),
                radial-gradient(circle at 60% 80%, rgba(255, 255, 255, 0.2) 0, rgba(255, 255, 255, 0.2) 1px, transparent 1px);
            background-size: 28px 28px, 24px 24px;
        }

        #task-chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        #task-chat-messages::-webkit-scrollbar-thumb {
            background: #b2b8c2;
            border-radius: 999px;
        }

        .wa-message-enter {
            animation: wa-pop-in 0.16s ease-out;
        }

        .wa-message-row {
            display: flex;
            width: 100%;
        }

        .wa-bubble {
            position: relative;
            max-width: min(680px, 85%);
            border-radius: 12px;
            padding: 8px 10px;
            box-shadow: 0 1px 1px rgba(15, 23, 42, 0.08);
            color: #1f2937;
        }

        .wa-bubble-own {
            background: #d9fdd3;
            border-top-right-radius: 2px;
        }

        .wa-bubble-other {
            background: #ffffff;
            border-top-left-radius: 2px;
        }

        .wa-meta-line {
            margin-top: 6px;
            text-align: right;
            font-size: 11px;
            color: #6b7280;
        }

        .wa-reaction-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 999px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
            padding: 1px 7px;
            font-size: 11px;
            color: #374151;
        }

        .wa-reaction-pill-active {
            border-color: #86efac;
            background: #dcfce7;
            color: #166534;
        }

        .wa-file-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            padding: 4px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }

        .wa-file-link:hover {
            border-color: #2dd4bf;
            color: #0f766e;
        }

        .wa-composer-icon {
            display: inline-flex;
            height: 34px;
            width: 34px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #64748b;
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .wa-composer-icon:hover {
            background: #e2e8f0;
            color: #0f766e;
        }

        .wa-send-btn {
            display: inline-flex;
            height: 46px;
            width: 46px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #16a34a;
            color: #fff;
            transition: background-color 0.15s ease, opacity 0.15s ease;
        }

        .wa-send-btn:hover {
            background: #15803d;
        }

        .chat-composer-input {
            max-height: 112px;
            min-height: 40px;
            line-height: 1.5;
            overflow-y: auto;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            resize: none;
        }

        @keyframes wa-pop-in {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>

    <div class="card space-y-6 bg-[#f7f8fa] p-4 sm:p-6">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Task Details</div>
            <div class="mt-2 font-semibold text-slate-900">{{ $task->title }}</div>
            @if($task->description)
                <div class="mt-1 text-xs text-slate-600 whitespace-pre-wrap">{{ $task->description }}</div>
            @endif
            <div class="mt-2 text-xs text-slate-500">
                Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }} |
                Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }} |
                Status: {{ ucfirst(str_replace('_', ' ', $task->status)) }}
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-300 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversation</div>
                    <div class="text-xs text-slate-500">Near real-time updates with seamless refresh.</div>
                </div>
            </div>
            @php
                $lastMessageId = $messages->last()?->id ?? 0;
                $oldestMessageId = $messages->first()?->id ?? 0;
            @endphp
            <div id="task-chat-messages"
                 data-messages-url="{{ $messagesUrl }}"
                 data-read-url="{{ $readUrl }}"
                 data-last-id="{{ $lastMessageId }}"
                 data-oldest-id="{{ $oldestMessageId }}"
                 class="wa-chat-canvas mt-4 max-h-[65vh] space-y-2 overflow-y-auto px-3 py-4 sm:px-5">
                @include('projects.partials.task-chat-messages', [
                    'messages' => $messages,
                    'project' => $project,
                    'task' => $task,
                    'attachmentRouteName' => $attachmentRouteName,
                    'currentAuthorType' => $currentAuthorType,
                    'currentAuthorId' => $currentAuthorId,
                    'updateRouteName' => $messageUpdateRouteName ?? null,
                    'deleteRouteName' => $messageDeleteRouteName ?? null,
                    'pinRouteName' => $messagePinRouteName ?? null,
                    'reactionRouteName' => $messageReactionRouteName ?? null,
                    'editableWindowSeconds' => $editableWindowSeconds ?? 30,
                ])
            </div>
        </div>

        @if($canPost)
            <div class="rounded-2xl border border-slate-200 bg-[#f0f2f5] p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                <form method="POST" action="{{ $postRoute }}" data-post-url="{{ $postMessagesUrl }}" enctype="multipart/form-data" class="mt-4 space-y-2" id="chatMessageForm">
                    @csrf
                    <input type="hidden" name="reply_to_message_id" id="taskChatReplyInput" value="">
                    <input id="taskChatAttachmentInput" name="attachment" type="file" accept="image/*,.pdf" class="hidden" />

                    <div id="taskChatPinnedMeta" class="hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"></div>
                    <div id="taskChatReplyMeta" class="hidden items-center justify-between gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-700">
                        <span id="taskChatReplyMetaText"></span>
                        <button type="button" id="taskChatReplyCancelButton" class="font-semibold text-sky-800 hover:text-sky-900">Cancel</button>
                    </div>

                    <div class="flex items-end">
                        <div class="flex min-h-[50px] flex-1 items-center gap-1 rounded-3xl border border-slate-300 bg-white px-2 py-1.5 shadow-sm">
                            <button type="button" id="taskChatAttachButton" class="wa-composer-icon text-3xl font-light leading-none" title="Attach file" aria-label="Attach file">+</button>
                            <button type="button" id="taskChatEmojiButton" class="wa-composer-icon text-lg" title="Emoji">🙂</button>
                            <div class="flex-1">
                                <textarea id="taskChatMessageInput" name="message" rows="1" class="chat-composer-input w-full border-0 bg-transparent px-2 py-2 leading-6 text-sm text-slate-700 focus:outline-none focus:ring-0" placeholder="Message">{{ old('message') }}</textarea>
                            </div>
                            <button type="submit" id="taskChatSendButton" class="wa-send-btn" aria-label="Send message">
                                <span id="taskChatSendIcon" class="text-base">➤</span>
                            </button>
                        </div>
                    </div>

                    <div id="taskChatEmojiPanel" class="hidden flex flex-wrap gap-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                        @foreach(['😀','😂','😍','😎','🤝','👍','🙏','🔥','✅','🎉','📌','🚀','💡','😅','🙂','😮','😢','❤️','👏','🤔'] as $emoji)
                            <button type="button" class="wa-composer-icon h-9 w-9 text-lg" data-emoji="{{ $emoji }}">{{ $emoji }}</button>
                        @endforeach
                    </div>

                    <div id="taskChatAttachmentMeta" class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"></div>
                    <button type="button" id="taskChatAttachmentClearButton" class="hidden text-xs font-semibold text-rose-600 hover:text-rose-700">
                        Remove selected file
                    </button>
                    <div id="taskChatEditMeta" class="hidden items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        <span>Editing message</span>
                        <button type="button" id="taskChatEditCancelButton" class="font-semibold text-amber-800 hover:text-amber-900">Cancel</button>
                    </div>
                    <div id="taskChatAttachmentPreview" class="hidden rounded-xl border border-slate-200 bg-white p-2">
                        <img id="taskChatAttachmentPreviewImg" src="" alt="Selected image preview" class="max-h-48 rounded-lg border border-slate-200 object-contain">
                    </div>

                    <div class="space-y-1">
                        @error('message')
                            <div class="text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                        @error('attachment')
                            <div class="text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script data-script-key="{{ $routePrefix }}-task-chat">
        (() => {
        const pageKey = @json($routePrefix . '.projects.tasks.chat');
        window.PageInit = window.PageInit || {};
        window.PageInit[pageKey] = () => {
        if (typeof window.__taskChatCleanup === 'function') {
            window.__taskChatCleanup();
        }
        const container = document.getElementById('task-chat-messages');
        const form = document.getElementById('chatMessageForm');
        const textarea = document.getElementById('taskChatMessageInput');
        const attachmentInput = document.getElementById('taskChatAttachmentInput');
        const attachmentMeta = document.getElementById('taskChatAttachmentMeta');
        const attachmentClearButton = document.getElementById('taskChatAttachmentClearButton');
        const editMeta = document.getElementById('taskChatEditMeta');
        const editCancelButton = document.getElementById('taskChatEditCancelButton');
        const replyInput = document.getElementById('taskChatReplyInput');
        const replyMeta = document.getElementById('taskChatReplyMeta');
        const replyMetaText = document.getElementById('taskChatReplyMetaText');
        const replyCancelButton = document.getElementById('taskChatReplyCancelButton');
        const pinnedMeta = document.getElementById('taskChatPinnedMeta');
        const attachmentPreview = document.getElementById('taskChatAttachmentPreview');
        const attachmentPreviewImg = document.getElementById('taskChatAttachmentPreviewImg');
        const emojiButton = document.getElementById('taskChatEmojiButton');
        const emojiPanel = document.getElementById('taskChatEmojiPanel');
        const attachButton = document.getElementById('taskChatAttachButton');
        const cameraButton = document.getElementById('taskChatCameraButton');
        const sendButton = document.getElementById('taskChatSendButton');
        const sendIcon = document.getElementById('taskChatSendIcon');
        const messagesUrl = @json($messagesUrl);
        const readUrl = container?.dataset?.readUrl || @json($readUrl ?? '');
        let attachmentPreviewUrl = null;
        if (!container) {
            return;
        }
        if (container.dataset.chatBound === '1') {
            return;
        }
        container.dataset.chatBound = '1';

            let lastId = Number(container.dataset.lastId || {{ $lastMessageId }} || 0);
            let oldestId = Number(container.dataset.oldestId || {{ $oldestMessageId }} || 0);
            let isLoadingOlder = false;
            let reachedStart = false;
            let editingMessageId = 0;
            let editingMessageUrl = '';
            let replyingToMessageId = 0;
            const seenMessageIds = new Set();
            const defaultPlaceholder = textarea?.getAttribute('placeholder') || 'Message';

            container.querySelectorAll('[data-message-id]').forEach((node) => {
                const id = Number(node.dataset.messageId || 0);
                if (id) {
                    seenMessageIds.add(id);
                }
            });

            const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('[name="_token"]')?.value
                || '';

            const parseEditableDeadline = (row) => {
                const raw = row?.dataset?.editableUntil || '';
                const value = Date.parse(raw);
                return Number.isFinite(value) ? value : 0;
            };

            const isEditWindowExpired = (row) => {
                const deadline = parseEditableDeadline(row);
                return !deadline || Date.now() >= deadline;
            };

            const refreshMessageActionAvailability = () => {
                container.querySelectorAll('[data-message-id]').forEach((row) => {
                    const actions = row.querySelector('[data-chat-actions]');
                    if (!actions) {
                        return;
                    }
                    if (!row.dataset.editUrl || !row.dataset.deleteUrl || isEditWindowExpired(row)) {
                        actions.remove();
                        row.dataset.editUrl = '';
                        row.dataset.deleteUrl = '';
                    }
                });
            };

            const parseReactions = (row) => {
                if (!row) {
                    return [];
                }
                const raw = row.dataset.reactions || '[]';
                try {
                    const parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            };

            const renderReactions = (row, summary) => {
                if (!row) {
                    return;
                }
                const wrapper = row.querySelector('[data-chat-reactions]');
                const items = Array.isArray(summary) ? summary : [];
                row.dataset.reactions = JSON.stringify(items);
                if (!wrapper) {
                    return;
                }
                wrapper.innerHTML = items.map((reaction) => {
                    const emoji = reaction?.emoji || '';
                    const count = Number(reaction?.count || 0);
                    if (!emoji || !count) {
                        return '';
                    }
                    const activeClass = reaction?.reacted ? ' wa-reaction-pill-active' : '';
                    return `<span class="wa-reaction-pill${activeClass}">${emoji} ${count}</span>`;
                }).join('');
            };

            const refreshPinnedBanner = () => {
                if (!pinnedMeta) {
                    return;
                }
                const pinnedRow = container.querySelector('[data-message-id][data-is-pinned="1"]');
                if (!pinnedRow) {
                    pinnedMeta.classList.add('hidden');
                    pinnedMeta.textContent = '';
                    return;
                }
                const id = Number(pinnedRow.dataset.messageId || 0);
                const text = (pinnedRow.dataset.messagePlain || '').trim() || 'Attachment';
                pinnedMeta.textContent = `📌 Pinned #${id}: ${text}`;
                pinnedMeta.classList.remove('hidden');
            };

            const clearReplyTarget = () => {
                replyingToMessageId = 0;
                if (replyInput) {
                    replyInput.value = '';
                }
                if (replyMeta) {
                    replyMeta.classList.add('hidden');
                    replyMeta.classList.remove('flex');
                }
                if (replyMetaText) {
                    replyMetaText.textContent = '';
                }
            };

            const setReplyTarget = (row) => {
                const id = Number(row?.dataset?.messageId || 0);
                if (!id) {
                    return;
                }
                replyingToMessageId = id;
                if (replyInput) {
                    replyInput.value = String(id);
                }
                const author = row.dataset.messageAuthor || 'User';
                const text = (row.dataset.messagePlain || '').trim() || 'Attachment';
                if (replyMetaText) {
                    replyMetaText.textContent = `Replying to #${id} (${author}): ${text}`;
                }
                if (replyMeta) {
                    replyMeta.classList.remove('hidden');
                    replyMeta.classList.add('flex');
                }
            };

            const recalculateMessageBounds = () => {
                const ids = Array.from(container.querySelectorAll('[data-message-id]'))
                    .map((node) => Number(node.dataset.messageId || 0))
                    .filter((id) => id > 0);
                lastId = ids.length ? Math.max(...ids) : 0;
                oldestId = ids.length ? Math.min(...ids) : 0;
            };

            const replaceMessageItem = (item) => {
                if (!item?.id || !item?.html) {
                    return;
                }
                const existing = container.querySelector(`[data-message-id="${item.id}"]`);
                if (!existing) {
                    return;
                }
                const wrapper = document.createElement('div');
                wrapper.innerHTML = item.html.trim();
                const next = wrapper.firstElementChild;
                if (!next) {
                    return;
                }
                existing.replaceWith(next);
                seenMessageIds.add(Number(item.id));
                refreshMessageActionAvailability();
                refreshPinnedBanner();
            };

            const removeMessageItem = (id) => {
                if (!id) {
                    return;
                }
                const row = container.querySelector(`[data-message-id="${id}"]`);
                if (row) {
                    row.remove();
                }
                if (replyingToMessageId === Number(id)) {
                    clearReplyTarget();
                }
                seenMessageIds.delete(Number(id));
                recalculateMessageBounds();
                refreshMessageActionAvailability();
                refreshPinnedBanner();
            };

            const scrollToBottom = () => {
                container.scrollTop = container.scrollHeight;
            };

            const isNearBottom = () => {
                const threshold = 120;
                return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
            };

            const resizeComposerInput = () => {
                if (!textarea) {
                    return;
                }
                textarea.style.height = 'auto';
                const nextHeight = Math.max(40, Math.min(textarea.scrollHeight, 112));
                textarea.style.height = `${nextHeight}px`;
            };

            const hasAttachment = () => !!(attachmentInput && attachmentInput.files && attachmentInput.files.length);
            const hasText = () => !!(textarea && textarea.value.trim().length);
            const composerHasContent = () => hasAttachment() || hasText();

            const updateAttachmentMeta = () => {
                if (!attachmentMeta || !attachmentInput) {
                    return;
                }
                const file = attachmentInput.files?.[0];
                if (attachmentPreviewUrl) {
                    URL.revokeObjectURL(attachmentPreviewUrl);
                    attachmentPreviewUrl = null;
                }
                if (!file) {
                    attachmentMeta.classList.add('hidden');
                    attachmentMeta.textContent = '';
                    if (attachmentClearButton) {
                        attachmentClearButton.classList.add('hidden');
                    }
                    if (attachmentPreview) {
                        attachmentPreview.classList.add('hidden');
                    }
                    if (attachmentPreviewImg) {
                        attachmentPreviewImg.removeAttribute('src');
                    }
                    return;
                }
                attachmentMeta.classList.remove('hidden');
                attachmentMeta.textContent = `Selected: ${file.name}`;
                if (attachmentClearButton) {
                    attachmentClearButton.classList.remove('hidden');
                }

                const isImage = file.type.startsWith('image/');
                if (isImage && attachmentPreview && attachmentPreviewImg) {
                    attachmentPreviewUrl = URL.createObjectURL(file);
                    attachmentPreviewImg.src = attachmentPreviewUrl;
                    attachmentPreview.classList.remove('hidden');
                } else if (attachmentPreview) {
                    attachmentPreview.classList.add('hidden');
                }
            };

            const clearSelectedAttachment = () => {
                if (!attachmentInput) {
                    return;
                }
                attachmentInput.value = '';
                updateAttachmentMeta();
                updateComposerState();
            };

            const isEditingMessage = () => editingMessageId > 0 && editingMessageUrl !== '';

            const stopEditingMessage = () => {
                editingMessageId = 0;
                editingMessageUrl = '';
                if (textarea) {
                    textarea.setAttribute('placeholder', defaultPlaceholder);
                }
                if (editMeta) {
                    editMeta.classList.add('hidden');
                    editMeta.classList.remove('flex');
                }
                updateComposerState();
            };

            const startEditingMessage = (row) => {
                if (!textarea) {
                    return;
                }
                const id = Number(row?.dataset?.messageId || 0);
                const url = row?.dataset?.editUrl || '';
                if (!id || !url) {
                    return;
                }
                const messageNode = row.querySelector('[data-chat-message-text]');
                const currentText = (messageNode?.textContent || '').trim();

                editingMessageId = id;
                editingMessageUrl = url;
                textarea.value = currentText;
                textarea.setAttribute('placeholder', 'Edit message');
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                if (editMeta) {
                    editMeta.classList.remove('hidden');
                    editMeta.classList.add('flex');
                }
                closeEmojiPanel();
                clearSelectedAttachment();
                resizeComposerInput();
                updateComposerState();
            };

            const updateComposerState = () => {
                const canSend = composerHasContent();
                if (sendButton) {
                    sendButton.disabled = !canSend;
                    sendButton.classList.toggle('opacity-70', !canSend);
                }
                if (sendIcon) {
                    sendIcon.classList.remove('hidden');
                }
            };

            const closeEmojiPanel = () => {
                if (emojiPanel) {
                    emojiPanel.classList.add('hidden');
                }
            };

            const insertEmojiAtCursor = (emoji) => {
                if (!textarea || !emoji) {
                    return;
                }
                const start = textarea.selectionStart || 0;
                const end = textarea.selectionEnd || start;
                const value = textarea.value;
                textarea.value = `${value.slice(0, start)}${emoji}${value.slice(end)}`;
                const cursor = start + emoji.length;
                textarea.setSelectionRange(cursor, cursor);
                textarea.focus();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            };

            if (emojiButton && emojiPanel) {
                emojiButton.addEventListener('click', () => {
                    emojiPanel.classList.toggle('hidden');
                });
                emojiPanel.querySelectorAll('[data-emoji]').forEach((button) => {
                    button.addEventListener('click', () => {
                        insertEmojiAtCursor(button.dataset.emoji || '');
                        closeEmojiPanel();
                    });
                });
            }

            const openAttachmentPicker = (useCamera) => {
                if (!attachmentInput) {
                    return;
                }
                if (useCamera) {
                    attachmentInput.setAttribute('capture', 'environment');
                    attachmentInput.setAttribute('accept', 'image/*');
                } else {
                    attachmentInput.removeAttribute('capture');
                    attachmentInput.setAttribute('accept', 'image/*,.pdf');
                }
                attachmentInput.click();
            };

            if (attachButton) {
                attachButton.addEventListener('click', () => openAttachmentPicker(false));
            }
            if (cameraButton) {
                cameraButton.addEventListener('click', () => openAttachmentPicker(true));
            }
            if (attachmentInput) {
                attachmentInput.addEventListener('change', () => {
                    attachmentInput.removeAttribute('capture');
                    attachmentInput.setAttribute('accept', 'image/*,.pdf');
                    updateAttachmentMeta();
                    updateComposerState();
                });
            }
            if (attachmentClearButton) {
                attachmentClearButton.addEventListener('click', clearSelectedAttachment);
            }
            if (editCancelButton) {
                editCancelButton.addEventListener('click', () => {
                    stopEditingMessage();
                    if (textarea) {
                        textarea.value = '';
                        resizeComposerInput();
                    }
                    updateComposerState();
                });
            }
            if (replyCancelButton) {
                replyCancelButton.addEventListener('click', clearReplyTarget);
            }

            if (textarea) {
                resizeComposerInput();
                textarea.addEventListener('input', () => {
                    resizeComposerInput();
                    updateComposerState();
                });
                textarea.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && event.shiftKey) {
                        event.preventDefault();
                        if (form?.dataset?.submitting === '1') {
                            return;
                        }
                        if (!composerHasContent()) {
                            return;
                        }
                        form?.requestSubmit(sendButton || undefined);
                    }
                });
            }

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (emojiPanel && !emojiPanel.classList.contains('hidden') && emojiButton
                    && !emojiPanel.contains(target) && !emojiButton.contains(target)) {
                    closeEmojiPanel();
                }
            });

            const parseErrorMessage = async (response, fallback) => {
                try {
                    const payload = await response.json();
                    return payload?.message
                        || payload?.error
                        || Object.values(payload?.errors || {})?.[0]?.[0]
                        || fallback;
                } catch (e) {
                    return fallback;
                }
            };

            const closeInlineEditor = (row, restore = true) => {
                if (!row) {
                    return;
                }
                const editor = row.querySelector('[data-chat-inline-editor]');
                if (editor) {
                    editor.remove();
                }

                const messageNode = row.querySelector('[data-chat-message-text]');
                const createdTextNode = row.dataset.inlineCreatedTextNode === '1';
                if (messageNode) {
                    if (restore && row.dataset.inlineOriginalHtml !== undefined) {
                        messageNode.innerHTML = row.dataset.inlineOriginalHtml;
                    }
                    messageNode.classList.remove('hidden');
                    if (restore && createdTextNode && (messageNode.innerHTML || '').trim() === '') {
                        messageNode.remove();
                    }
                }

                delete row.dataset.inlineOriginalHtml;
                delete row.dataset.inlineCreatedTextNode;
            };

            const closeOtherInlineEditors = (exceptRowId) => {
                container.querySelectorAll('[data-message-id]').forEach((candidate) => {
                    const id = Number(candidate.dataset.messageId || 0);
                    if (id && id === exceptRowId) {
                        return;
                    }
                    closeInlineEditor(candidate, true);
                });
            };

            const beginInlineEdit = (row) => {
                if (!row || !row.dataset.editUrl) {
                    return;
                }
                if (isEditWindowExpired(row)) {
                    refreshMessageActionAvailability();
                    return;
                }

                const rowId = Number(row.dataset.messageId || 0);
                closeOtherInlineEditors(rowId);
                if (row.querySelector('[data-chat-inline-editor]')) {
                    return;
                }

                const bubble = row.querySelector('.wa-bubble');
                if (!bubble) {
                    return;
                }

                let messageNode = row.querySelector('[data-chat-message-text]');
                let createdTextNode = false;
                if (!messageNode) {
                    messageNode = document.createElement('div');
                    messageNode.className = 'text-sm whitespace-pre-wrap text-slate-800';
                    messageNode.dataset.chatMessageText = '1';
                    const actions = row.querySelector('[data-chat-actions]');
                    const meta = row.querySelector('.wa-meta-line');
                    bubble.insertBefore(messageNode, actions || meta || null);
                    createdTextNode = true;
                }

                row.dataset.inlineOriginalHtml = messageNode.innerHTML || '';
                if (createdTextNode) {
                    row.dataset.inlineCreatedTextNode = '1';
                }
                const currentText = messageNode.textContent || '';
                messageNode.classList.add('hidden');

                const editor = document.createElement('div');
                editor.dataset.chatInlineEditor = '1';
                editor.className = 'mt-2 rounded-xl border border-amber-200 bg-white px-2 py-2';
                editor.innerHTML = `
                    <textarea rows="2" class="w-full resize-none rounded-lg border border-amber-200 px-2 py-1 text-sm text-slate-700 focus:border-amber-400 focus:outline-none" data-chat-inline-input></textarea>
                    <div class="mt-2 flex justify-end gap-2 text-xs font-semibold">
                        <button type="button" class="rounded-md px-2 py-1 text-slate-600 hover:bg-slate-100" data-chat-inline-cancel>Cancel</button>
                        <button type="button" class="rounded-md bg-emerald-600 px-2 py-1 text-white hover:bg-emerald-700" data-chat-inline-save>Save</button>
                    </div>
                `;
                messageNode.insertAdjacentElement('afterend', editor);

                const input = editor.querySelector('[data-chat-inline-input]');
                if (input) {
                    input.value = currentText.trim();
                    input.focus();
                    input.setSelectionRange(input.value.length, input.value.length);
                }
            };

            const submitInlineEdit = async (row) => {
                if (!row) {
                    return;
                }
                if (row.dataset.mutating === '1') {
                    return;
                }

                const editUrl = row.dataset.editUrl || '';
                if (!editUrl) {
                    refreshMessageActionAvailability();
                    return;
                }
                if (isEditWindowExpired(row)) {
                    refreshMessageActionAvailability();
                    closeInlineEditor(row, true);
                    return;
                }

                const editor = row.querySelector('[data-chat-inline-editor]');
                const input = editor?.querySelector('[data-chat-inline-input]');
                const nextText = (input?.value || '').trim();
                const hasAttachment = row.dataset.hasAttachment === '1';
                if (!nextText && !hasAttachment) {
                    window.notify('Message cannot be empty.', 'warning');
                    input?.focus();
                    return;
                }

                row.dataset.mutating = '1';
                try {
                    const body = new FormData();
                    body.append('_method', 'PATCH');
                    body.append('_token', getCsrfToken());
                    body.append('message', nextText);

                    const response = await fetch(editUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });

                    if (!response.ok) {
                        window.notify(await parseErrorMessage(response, 'Message update failed.'), 'error');
                        return;
                    }

                    const payload = await response.json();
                    const item = payload?.data?.item;
                    if (item?.html) {
                        replaceMessageItem(item);
                    }
                } finally {
                    delete row.dataset.mutating;
                }
            };

            container.addEventListener('click', async (event) => {
                const inlineSaveButton = event.target.closest('[data-chat-inline-save]');
                const inlineCancelButton = event.target.closest('[data-chat-inline-cancel]');
                const editButton = event.target.closest('[data-chat-edit]');
                const deleteButton = event.target.closest('[data-chat-delete]');
                const replyButton = event.target.closest('[data-chat-reply]');
                const pinButton = event.target.closest('[data-chat-pin]');
                const reactButton = event.target.closest('[data-chat-react]');
                if (!inlineSaveButton && !inlineCancelButton && !editButton && !deleteButton && !replyButton && !pinButton && !reactButton) {
                    return;
                }

                const row = event.target.closest('[data-message-id]');
                if (!row) {
                    return;
                }

                if (inlineCancelButton) {
                    closeInlineEditor(row, true);
                    return;
                }

                if (inlineSaveButton) {
                    await submitInlineEdit(row);
                    return;
                }

                if (replyButton) {
                    setReplyTarget(row);
                    textarea?.focus();
                    return;
                }

                if (row.dataset.mutating === '1') {
                    return;
                }

                row.dataset.mutating = '1';
                try {
                    if (pinButton) {
                        const pinUrl = row.dataset.pinUrl || '';
                        if (!pinUrl) {
                            return;
                        }

                        const response = await fetch(pinUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': getCsrfToken(),
                            },
                        });

                        if (!response.ok) {
                            window.notify(await parseErrorMessage(response, 'Pin action failed.'), 'error');
                            return;
                        }

                        const payload = await response.json();
                        const pinnedId = Number(payload?.data?.pinned_message_id || 0);
                        container.querySelectorAll('[data-message-id]').forEach((candidate) => {
                            const candidateId = Number(candidate.dataset.messageId || 0);
                            const isPinned = pinnedId > 0 && candidateId === pinnedId;
                            candidate.dataset.isPinned = isPinned ? '1' : '0';
                            const badge = candidate.querySelector('[data-chat-pin-badge]');
                            if (badge) {
                                badge.classList.toggle('hidden', !isPinned);
                            }
                        });
                        refreshPinnedBanner();
                        return;
                    }

                    if (reactButton) {
                        const reactUrl = row.dataset.reactUrl || '';
                        const emoji = reactButton.dataset.emoji || '';
                        if (!reactUrl || !emoji) {
                            return;
                        }

                        const response = await fetch(reactUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': getCsrfToken(),
                            },
                            body: JSON.stringify({ emoji }),
                        });

                        if (!response.ok) {
                            window.notify(await parseErrorMessage(response, 'Reaction failed.'), 'error');
                            return;
                        }

                        const payload = await response.json();
                        renderReactions(row, payload?.data?.reaction_summary || parseReactions(row));
                        return;
                    }

                    if (editButton) {
                        if (isEditWindowExpired(row)) {
                            refreshMessageActionAvailability();
                            return;
                        }
                        beginInlineEdit(row);
                        return;
                    }

                    if (deleteButton) {
                        if (isEditWindowExpired(row)) {
                            refreshMessageActionAvailability();
                            return;
                        }
                        const deleteUrl = row.dataset.deleteUrl || '';
                        if (!deleteUrl) {
                            refreshMessageActionAvailability();
                            return;
                        }

                        if (!window.confirm('Delete this message?')) {
                            return;
                        }

                        const body = new FormData();
                        body.append('_method', 'DELETE');
                        body.append('_token', getCsrfToken());

                        const response = await fetch(deleteUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                        });

                        if (!response.ok) {
                            window.notify(await parseErrorMessage(response, 'Message delete failed.'), 'error');
                            return;
                        }

                        const payload = await response.json();
                        const deletedId = Number(payload?.data?.id || row.dataset.messageId || 0);
                        removeMessageItem(deletedId);
                        if (lastId) {
                            updateReadStatus(lastId);
                        }
                    }
                } finally {
                    delete row.dataset.mutating;
                }
            });

            const appendItems = (items) => {
                if (!items || !items.length) {
                    return;
                }
                items.forEach((item) => {
                    if (!item?.html || !item?.id) return;
                    const existing = container.querySelector(`[data-message-id="${item.id}"]`);
                    if (existing) {
                        seenMessageIds.add(item.id);
                        return;
                    }
                    container.insertAdjacentHTML('beforeend', item.html);
                    const inserted = container.querySelector(`[data-message-id="${item.id}"]`);
                    if (inserted) {
                        inserted.classList.add('wa-message-enter');
                        window.setTimeout(() => inserted.classList.remove('wa-message-enter'), 280);
                    }
                    seenMessageIds.add(item.id);
                    lastId = Math.max(lastId, item.id);
                    if (!oldestId) {
                        oldestId = item.id;
                    }
                });
                refreshMessageActionAvailability();
                refreshPinnedBanner();
            };

            const prependItems = (items) => {
                if (!items || !items.length) {
                    return;
                }
                const previousHeight = container.scrollHeight;
                const previousTop = container.scrollTop;
                for (let i = items.length - 1; i >= 0; i -= 1) {
                    const item = items[i];
                    if (!item?.html || !item?.id) continue;
                    const existing = container.querySelector(`[data-message-id="${item.id}"]`);
                    if (existing) {
                        seenMessageIds.add(item.id);
                        continue;
                    }
                    container.insertAdjacentHTML('afterbegin', item.html);
                    seenMessageIds.add(item.id);
                }
                const oldestItemId = items[0]?.id;
                if (oldestItemId) {
                    oldestId = oldestId ? Math.min(oldestId, oldestItemId) : oldestItemId;
                }
                const heightDiff = container.scrollHeight - previousHeight;
                container.scrollTop = previousTop + heightDiff;
                refreshMessageActionAvailability();
                refreshPinnedBanner();
            };

            const fetchMessages = async (params) => {
                if (!messagesUrl) {
                    return [];
                }
                const url = new URL(messagesUrl, window.location.origin);
                Object.entries(params).forEach(([key, value]) => {
                    if (value !== null && value !== undefined) {
                        url.searchParams.set(key, value);
                    }
                });

                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    return [];
                }
                const payload = await response.json();
                return payload?.data?.items || [];
            };

            const loadOlder = async () => {
                if (!oldestId || isLoadingOlder || reachedStart) {
                    return;
                }
                isLoadingOlder = true;
                const items = await fetchMessages({ before_id: oldestId, limit: 30 });
                if (items.length === 0) {
                    reachedStart = true;
                } else {
                    prependItems(items);
                }
                isLoadingOlder = false;
            };

            const updateReadStatus = async (readId) => {
                if (!readUrl || !readId) {
                    return;
                }
                const token = document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('[name="_token"]')?.value
                    || '';
                const formData = new FormData();
                formData.append('last_read_id', readId);

                try {
                    await fetch(readUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                } catch (e) {
                    // Ignore read status failures.
                }
            };

            if (form && form.dataset.bound !== '1') {
                form.dataset.bound = '1';
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (form.dataset.submitting === '1') {
                        return;
                    }
                    form.dataset.submitting = '1';
                    const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                }

                if (!composerHasContent()) {
                    form.dataset.submitting = '0';
                    updateComposerState();
                    return;
                }

                try {
                        const isEditing = isEditingMessage();
                        const requestUrl = isEditing ? editingMessageUrl : (form.dataset.postUrl || form.action);
                        const formData = new FormData(form);

                        let response;
                        if (isEditing) {
                            const editingRow = container.querySelector(`[data-message-id="${editingMessageId}"]`);
                            const hasAttachment = editingRow?.dataset?.hasAttachment === '1';
                            const nextText = (textarea?.value || '').trim();
                            if (!nextText && !hasAttachment) {
                                window.notify('Message cannot be empty.', 'warning');
                                return;
                            }
                            formData.delete('attachment');
                            formData.delete('reply_to_message_id');
                            formData.append('_method', 'PATCH');
                            formData.append('_token', getCsrfToken());
                            formData.set('message', nextText);
                            response = await fetch(requestUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            });
                        } else {
                            if (replyingToMessageId > 0) {
                                formData.set('reply_to_message_id', String(replyingToMessageId));
                            } else {
                                formData.delete('reply_to_message_id');
                            }
                            response = await fetch(requestUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: formData
                            });
                        }

                        if (!response.ok) {
                            let errorMessage = 'Message send failed.';
                            try {
                                const payload = await response.json();
                                errorMessage = payload?.message
                                    || payload?.error
                                    || Object.values(payload?.errors || {})?.[0]?.[0]
                                    || errorMessage;
                            } catch (e) {
                                // Ignore JSON parse failures.
                            }
                            window.notify(errorMessage, 'error');
                            return;
                        }

                        const payload = await response.json();
                        const item = payload?.data?.item;
                        if (item?.html) {
                            if (isEditing) {
                                replaceMessageItem(item);
                            } else {
                                appendItems([item]);
                                scrollToBottom();
                                updateReadStatus(lastId);
                                clearReplyTarget();
                            }
                        } else if (!isEditing) {
                            const latestItems = await fetchMessages({ after_id: lastId, limit: 30 });
                            if (latestItems.length) {
                                appendItems(latestItems);
                                scrollToBottom();
                                updateReadStatus(lastId);
                                clearReplyTarget();
                            }
                        }
                    form.reset();
                    stopEditingMessage();
                    resizeComposerInput();
                    closeEmojiPanel();
                    updateAttachmentMeta();
                    updateComposerState();
                } finally {
                    form.dataset.submitting = '0';
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                    updateComposerState();
                }
            });
        }

            updateAttachmentMeta();
            updateComposerState();
            refreshMessageActionAvailability();
            refreshPinnedBanner();
            clearReplyTarget();
            scrollToBottom();
            updateReadStatus(lastId);

            container.addEventListener('scroll', async () => {
                if (container.scrollTop <= 80) {
                    loadOlder();
                }
                if (isNearBottom()) {
                    updateReadStatus(lastId);
                }
            });

            let pollTimer = null;
            if (messagesUrl) {
                pollTimer = setInterval(async () => {
                    const keepAtBottom = isNearBottom();
                    const items = await fetchMessages({ after_id: lastId, limit: 30 });
                    if (items.length) {
                        appendItems(items);
                        if (keepAtBottom) {
                            scrollToBottom();
                            updateReadStatus(lastId);
                        }
                    }
                }, 2000);
            }

            let editWindowTimer = setInterval(() => {
                refreshMessageActionAvailability();
            }, 1000);

            const cleanup = () => {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
                if (editWindowTimer) {
                    clearInterval(editWindowTimer);
                    editWindowTimer = null;
                }
                if (attachmentPreviewUrl) {
                    URL.revokeObjectURL(attachmentPreviewUrl);
                    attachmentPreviewUrl = null;
                }
            };

            window.__taskChatCleanup = cleanup;
            window.addEventListener('beforeunload', cleanup);
        };

        const tryInitTaskChat = () => {
            const activePageKey = document.querySelector('#appContent')?.dataset?.pageKey || '';
            if (activePageKey === pageKey || document.getElementById('task-chat-messages') || document.getElementById('chatMessageForm')) {
                window.PageInit[pageKey]();
            }
        };

        tryInitTaskChat();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tryInitTaskChat, { once: true });
        } else {
            window.setTimeout(tryInitTaskChat, 0);
        }
        })();
    </script>
@endsection
