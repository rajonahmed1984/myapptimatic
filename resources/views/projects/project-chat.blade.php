@extends($layout ?? 'layouts.admin')

@section('title', 'Project Chat')
@section('page-title', 'Project Chat')

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
            <div class="text-sm section-label">{{ $project->name }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back</a>
    </div>

    <style>
        .wa-chat-canvas {
            background-color: #efeae2;
            background-image:
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.34) 0, rgba(255, 255, 255, 0.34) 1.2px, transparent 1.2px),
                radial-gradient(circle at 60% 80%, rgba(255, 255, 255, 0.2) 0, rgba(255, 255, 255, 0.2) 1px, transparent 1px);
            background-size: 28px 28px, 24px 24px;
        }

        #project-chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        #project-chat-messages::-webkit-scrollbar-thumb {
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
            min-height: 36px;
            resize: none;
        }

        .chat-mention {
            border-radius: 6px;
            padding: 0 6px;
            background: #fee2b3;
            color: #92400e;
            font-weight: 600;
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

    <div class="card">
        <div class="overflow-hidden p-6 rounded-2xl">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversation</div>
                    <div class="text-xs text-slate-500">Live stream mode without browser permission prompts.</div>
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ count($participants ?? []) }} participants</div>
            </div>
            @php
                $participants = $participants ?? [];
                $participantStatuses = $participantStatuses ?? [];
                $presenceDotClass = function ($status) {
                    return match ($status) {
                        'active' => 'bg-emerald-500',
                        'idle' => 'bg-amber-400',
                        default => 'bg-slate-400',
                    };
                };
                $presenceLabel = function ($status) {
                    return match ($status) {
                        'active' => 'Active',
                        'idle' => 'Idle',
                        default => 'Offline',
                    };
                };
            @endphp
            @if(!empty($participants))
                <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-200 pt-3 text-xs" id="chatParticipants">
                    @foreach($participants as $participant)
                        @php
                            $key = $participant['key'] ?? '';
                            $status = $participantStatuses[$key] ?? 'offline';
                        @endphp
                        <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600 shadow-sm" data-presence-key="{{ $key }}">
                            <span class="h-2 w-2 rounded-full {{ $presenceDotClass($status) }}" title="{{ $presenceLabel($status) }}" data-presence-dot></span>
                            <span class="font-semibold text-slate-700">{{ $participant['label'] ?? 'User' }}</span>
                            <span class="text-slate-400">{{ $participant['role'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
            @php
                $lastMessageId = $messages->last()?->id ?? 0;
                $oldestMessageId = $messages->first()?->id ?? 0;
            @endphp
            <div id="project-chat-messages"
                 data-messages-url="{{ $messagesUrl }}"
                 data-stream-url="{{ $streamUrl ?? '' }}"
                 data-read-url="{{ $readUrl }}"
                 data-last-id="{{ $lastMessageId }}"
                 data-oldest-id="{{ $oldestMessageId }}"
                 class="wa-chat-canvas mt-4 max-h-[65vh] space-y-2 overflow-y-auto px-3 py-4 sm:px-5">
                @include('projects.partials.project-chat-messages', [
                    'messages' => $messages,
                    'project' => $project,
                    'attachmentRouteName' => $attachmentRouteName,
                    'taskShowRouteName' => $taskShowRouteName ?? null,
                    'currentAuthorType' => $currentAuthorType,
                    'currentAuthorId' => $currentAuthorId,
                    'readReceipts' => $readReceipts ?? [],
                    'authorStatuses' => $authorStatuses ?? [],
                    'messageMentions' => $messageMentions ?? [],
                    'updateRouteName' => $messageUpdateRouteName ?? null,
                    'deleteRouteName' => $messageDeleteRouteName ?? null,
                    'editableWindowSeconds' => $editableWindowSeconds ?? 30,
                ])
            </div>
        </div>

        @if($canPost)
            <div class="rounded-2xl p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                <form method="POST" action="{{ $postRoute }}" data-post-url="{{ $postMessagesUrl }}" enctype="multipart/form-data" class="mt-4 space-y-2" id="chatMessageForm">
                    @csrf
                    <input type="hidden" name="mentions" id="chatMentionsField" value="" />
                    <input id="chatAttachmentInput" name="attachment" type="file" accept="image/*,.pdf" class="hidden" />

                    <div class="flex gap-2">
                        <div class="flex min-h-[50px] flex-1 items-end gap-1 rounded-3xl border border-slate-300 bg-white px-2 py-1.5 shadow-sm">
                            <button type="button" id="chatEmojiButton" class="wa-composer-icon text-lg" title="Emoji">🙂</button>
                            <div class="relative flex-1">
                                <textarea id="chatMessageInput" name="message" rows="1" class="chat-composer-input w-full border-0 bg-transparent px-2 py-1 text-sm text-slate-700 focus:outline-none focus:ring-0" placeholder="Message">{{ old('message') }}</textarea>
                                <div id="chatMentionDropdown" class="absolute bottom-full left-0 z-20 mb-2 hidden w-72 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div>
                            </div>
                            <button type="button" id="chatAttachButton" class="wa-composer-icon" title="Attach file" aria-label="Attach file">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 01-7.78-7.78l9.2-9.19a3.5 3.5 0 114.95 4.95l-9.19 9.2a1.5 1.5 0 11-2.12-2.12l8.49-8.48"/>
                                </svg>
                            </button>
                        </div>
                        <button type="submit" id="chatSendButton" class="wa-send-btn" aria-label="Send message">
                            <span id="chatSendIcon" class="hidden text-base">➤</span>
                        </button>
                    </div>

                    <div id="chatEmojiPanel" class="hidden flex flex-wrap gap-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                        @foreach(['😀','😂','😍','😎','🤝','👍','🙏','🔥','✅','🎉','📌','🚀','💡','😅','🙂','😮','😢','❤️','👏','🤔'] as $emoji)
                            <button type="button" class="wa-composer-icon h-9 w-9 text-lg" data-emoji="{{ $emoji }}">{{ $emoji }}</button>
                        @endforeach
                    </div>

                    <div id="chatAttachmentMeta" class="hidden rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600"></div>
                    <button type="button" id="chatAttachmentClearButton" class="hidden text-xs font-semibold text-rose-600 hover:text-rose-700">
                        Remove selected file
                    </button>
                    <div id="chatEditMeta" class="hidden items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        <span>Editing message</span>
                        <button type="button" id="chatEditCancelButton" class="font-semibold text-amber-800 hover:text-amber-900">Cancel</button>
                    </div>
                    <div id="chatAttachmentPreview" class="hidden rounded-xl border border-slate-200 bg-white p-2">
                        <img id="chatAttachmentPreviewImg" src="" alt="Selected image preview" class="max-h-48 rounded-lg border border-slate-200 object-contain">
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

    <script data-script-key="{{ $routePrefix }}-project-chat">
    (() => {
    const pageKey = @json($routePrefix . '.projects.chat');
    window.PageInit = window.PageInit || {};
    window.PageInit[pageKey] = () => {
        if (typeof window.__projectChatCleanup === 'function') {
            window.__projectChatCleanup();
        }

        const container = document.getElementById('project-chat-messages');
        const form = document.getElementById('chatMessageForm');
        const textarea = document.getElementById('chatMessageInput');
        const mentionsField = document.getElementById('chatMentionsField');
        const dropdown = document.getElementById('chatMentionDropdown');
        const attachmentInput = document.getElementById('chatAttachmentInput');
        const attachmentMeta = document.getElementById('chatAttachmentMeta');
        const attachmentClearButton = document.getElementById('chatAttachmentClearButton');
        const editMeta = document.getElementById('chatEditMeta');
        const editCancelButton = document.getElementById('chatEditCancelButton');
        const attachmentPreview = document.getElementById('chatAttachmentPreview');
        const attachmentPreviewImg = document.getElementById('chatAttachmentPreviewImg');
        const emojiButton = document.getElementById('chatEmojiButton');
        const emojiPanel = document.getElementById('chatEmojiPanel');
        const attachButton = document.getElementById('chatAttachButton');
        const sendButton = document.getElementById('chatSendButton');
        const sendIcon = document.getElementById('chatSendIcon');
        const participants = @json($participants ?? []);
        const mentionables = @json($mentionables ?? $participants ?? []);
        const participantsUrl = @json($participantsUrl ?? '');
        const presenceUrl = @json($presenceUrl ?? '');
        const streamUrl = container?.dataset?.streamUrl || @json($streamUrl ?? '');
        const messagesUrl = @json($messagesUrl);
        const readUrl = container?.dataset?.readUrl || @json($readUrl ?? '');
        let attachmentPreviewUrl = null;

        if (!container || !messagesUrl) {
            return;
        }
        if (container.dataset.chatBound === '1') {
            return;
        }
        container.dataset.chatBound = '1';

        let lastId = Number(container.dataset.lastId || {{ $lastMessageId }} || 0);
        let oldestId = Number(container.dataset.oldestId || {{ $oldestMessageId }} || 0);
        let lastReadId = lastId;
        let isLoadingOlder = false;
        let reachedStart = false;
        let pollTimer = null;
        let eventSource = null;
        let editingMessageId = 0;
        let editingMessageUrl = '';
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
        };

        const removeMessageItem = (id) => {
            if (!id) {
                return;
            }
            const row = container.querySelector(`[data-message-id="${id}"]`);
            if (row) {
                row.remove();
            }
            seenMessageIds.delete(Number(id));
            recalculateMessageBounds();
            refreshMessageActionAvailability();
        };

        const presenceClasses = {
            active: 'bg-emerald-500',
            idle: 'bg-amber-400',
            offline: 'bg-slate-400',
        };
        const presenceLabels = {
            active: 'Active',
            idle: 'Idle',
            offline: 'Offline',
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
            const nextHeight = Math.max(36, Math.min(textarea.scrollHeight, 112));
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
            closeMentionDropdown();
            clearSelectedAttachment();
            resizeComposerInput();
            syncMentionsField();
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

        const openAttachmentPicker = () => {
            if (!attachmentInput) {
                return;
            }
            attachmentInput.removeAttribute('capture');
            attachmentInput.setAttribute('accept', 'image/*,.pdf');
            attachmentInput.click();
        };

        if (attachButton) {
            attachButton.addEventListener('click', () => openAttachmentPicker());
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
                syncMentionsField();
                updateComposerState();
            });
        }

        const escapePresenceKey = (value) => {
            if (window.CSS && CSS.escape) {
                return CSS.escape(value);
            }
            return value.replace(/"/g, '\\"');
        };

        const updatePresenceDot = (dot, status) => {
            const normalized = ['active', 'idle'].includes(status) ? status : 'offline';
            dot.classList.remove('bg-emerald-500', 'bg-amber-400', 'bg-slate-400');
            dot.classList.add(presenceClasses[normalized]);
            dot.title = presenceLabels[normalized];
        };

        const applyPresence = (statuses) => {
            if (!statuses) {
                return;
            }
            Object.entries(statuses).forEach(([key, status]) => {
                const selectorKey = escapePresenceKey(key);
                document.querySelectorAll(`[data-presence-key="${selectorKey}"]`).forEach((node) => {
                    const dot = node.dataset.presenceDot !== undefined ? node : node.querySelector('[data-presence-dot]');
                    if (dot) {
                        updatePresenceDot(dot, status);
                    }
                });
            });
        };

        const appendItems = (items) => {
            if (!items || !items.length) {
                return;
            }
            container.querySelectorAll('.chat-seen-by').forEach((item) => item.remove());
            container.querySelectorAll('.chat-read-up-to').forEach((item) => item.remove());

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
        };

        const fetchMessages = async (params) => {
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
                const response = await fetch(readUrl, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
                if (response.ok) {
                    const payload = await response.json();
                    lastReadId = Number(payload?.data?.last_read_id || readId);
                }
            } catch (e) {
                // Ignore read status failures.
            }
        };

        const startPolling = () => {
            if (pollTimer) {
                return;
            }
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
        };

        const connectStream = () => {
            if (!streamUrl || !window.EventSource) {
                startPolling();
                return;
            }
            if (eventSource) {
                return;
            }
            const url = new URL(streamUrl, window.location.origin);
            if (lastId) {
                url.searchParams.set('after_id', lastId);
            }
            eventSource = new EventSource(url.toString());

            eventSource.addEventListener('messages', (event) => {
                try {
                    const keepAtBottom = isNearBottom();
                    const payload = JSON.parse(event.data || '{}');
                    const items = payload?.items || [];
                    appendItems(items);
                    if (items.length && keepAtBottom) {
                        scrollToBottom();
                        updateReadStatus(lastId);
                    }
                } catch (e) {
                    // Ignore malformed SSE events.
                }
            });

            eventSource.addEventListener('presence', (event) => {
                try {
                    const payload = JSON.parse(event.data || '{}');
                    applyPresence(payload?.statuses || {});
                } catch (e) {
                    // Ignore malformed presence payloads.
                }
            });

            eventSource.onerror = () => {
                if (eventSource) {
                    eventSource.close();
                    eventSource = null;
                }
                startPolling();
            };
        };

        const sendPresence = async (status) => {
            if (!presenceUrl) {
                return;
            }
            const token = document.querySelector('[name="_token"]')?.value || '';
            const formData = new FormData();
            formData.append('_token', token);
            formData.append('status', status);

            try {
                await fetch(presenceUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
            } catch (e) {
                // Ignore presence failures.
            }
        };

        let presenceState = 'active';
        let idleTimer = null;

        const scheduleIdle = () => {
            if (idleTimer) {
                clearTimeout(idleTimer);
            }
            idleTimer = setTimeout(() => {
                if (presenceState !== 'idle') {
                    presenceState = 'idle';
                    sendPresence('idle');
                }
            }, 120000);
        };

        const markActive = () => {
            if (presenceState !== 'active') {
                presenceState = 'active';
                sendPresence('active');
            }
            scheduleIdle();
        };

        ['mousemove', 'keydown', 'click', 'scroll'].forEach((eventName) => {
            document.addEventListener(eventName, markActive, { passive: true });
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                presenceState = 'idle';
                sendPresence('idle');
            } else {
                markActive();
                if (isNearBottom()) {
                    updateReadStatus(lastId);
                }
            }
        });

        let presenceHeartbeatTimer = setInterval(() => {
            sendPresence(presenceState);
        }, 15000);
        let editWindowTimer = setInterval(() => {
            refreshMessageActionAvailability();
        }, 1000);

        const syncMentionsField = () => {
            if (!mentionsField || !textarea) {
                return;
            }
            const text = textarea.value;
            selectedMentions = selectedMentions.filter((mention) => {
                return mention?.label && text.includes(`@${mention.label}`);
            });
            if (selectedMentions.length) {
                mentionsField.value = JSON.stringify(selectedMentions.map((mention) => ({
                    type: mention.type,
                    id: mention.id,
                    label: mention.label,
                })));
            } else {
                mentionsField.value = '';
            }
        };

        const getMentionState = () => {
            if (!textarea) {
                return null;
            }
            const cursor = textarea.selectionStart || 0;
            const textBefore = textarea.value.slice(0, cursor);
            const match = textBefore.match(/(^|\s)@([\w\-\. ]{0,40})$/);
            if (!match) {
                return null;
            }
            const query = match[2] || '';
            const start = cursor - query.length - 1;
            return { start, end: cursor, query };
        };

        let mentionState = null;
        let mentionResults = [];
        let mentionActiveIndex = 0;
        let selectedMentions = [];
        let mentionFetchTimer = null;

        const closeMentionDropdown = () => {
            if (!dropdown) {
                return;
            }
            dropdown.classList.add('hidden');
            dropdown.innerHTML = '';
            mentionState = null;
            mentionResults = [];
            mentionActiveIndex = 0;
        };

        const positionDropdown = () => {
            if (!dropdown) {
                return;
            }
            dropdown.style.left = '0';
            dropdown.style.right = 'auto';
            dropdown.style.top = 'auto';
            dropdown.style.bottom = '100%';
            dropdown.style.marginBottom = '8px';
        };

        const renderMentionDropdown = (items) => {
            if (!dropdown) {
                return;
            }
            dropdown.innerHTML = '';

            const grouped = {
                people: [],
                tasks: [],
            };

            items.forEach((item, index) => {
                const type = String(item?.type || '').toLowerCase();
                if (type === 'project_task') {
                    grouped.tasks.push({ item, index });
                    return;
                }

                grouped.people.push({ item, index });
            });

            const renderGroup = (title, rows) => {
                if (!rows.length) {
                    return;
                }

                const header = document.createElement('div');
                header.className = 'sticky top-0 z-[1] border-b border-slate-100 bg-slate-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500';
                header.textContent = title;
                dropdown.appendChild(header);

                rows.forEach(({ item, index }) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flex w-full items-center justify-between px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50';
                    if (index === mentionActiveIndex) {
                        button.classList.add('bg-slate-100');
                    }

                    const label = document.createElement('span');
                    label.textContent = item.label;
                    const meta = document.createElement('span');
                    meta.textContent = item.role || '';
                    meta.className = 'text-xs text-slate-400';
                    button.appendChild(label);
                    button.appendChild(meta);
                    button.addEventListener('click', () => {
                        applyMention(item);
                    });
                    dropdown.appendChild(button);
                });
            };

            renderGroup('People', grouped.people);
            renderGroup('Tasks', grouped.tasks);

            dropdown.classList.remove('hidden');
            positionDropdown();
        };

        const applyMention = (item) => {
            if (!textarea || !mentionState) {
                return;
            }
            const value = textarea.value;
            const before = value.slice(0, mentionState.start);
            const after = value.slice(mentionState.end);
            const insertion = `@${item.label}`;
            const nextValue = `${before}${insertion} ${after}`;
            const cursor = before.length + insertion.length + 1;
            textarea.value = nextValue;
            textarea.setSelectionRange(cursor, cursor);
            const key = item.key || `${item.type}:${item.id}`;
            selectedMentions = selectedMentions.filter((mention) => (mention.key || `${mention.type}:${mention.id}`) !== key);
            selectedMentions.push({
                key,
                type: item.type,
                id: item.id,
                label: item.label,
            });
            syncMentionsField();
            closeMentionDropdown();
            textarea.focus();
            resizeComposerInput();
            updateComposerState();
        };

        const fetchMentionResults = async (query) => {
            if (!participantsUrl) {
                return null;
            }
            const url = new URL(participantsUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('limit', '50');
            try {
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) {
                    return null;
                }
                const payload = await response.json();
                return payload?.data?.items || null;
            } catch (e) {
                return null;
            }
        };

        const updateMentionDropdown = () => {
            if (!textarea || !dropdown) {
                return;
            }
            const state = getMentionState();
            if (!state) {
                closeMentionDropdown();
                return;
            }
            mentionState = state;
            const query = state.query.trim().toLowerCase();

            const handleResults = (results) => {
                const filtered = results
                    .filter((participant) => {
                        if (!query) {
                            return true;
                        }
                        return (participant.label || '').toLowerCase().includes(query);
                    });

                const people = filtered.filter((participant) => String(participant?.type || '').toLowerCase() !== 'project_task');
                const tasks = filtered.filter((participant) => String(participant?.type || '').toLowerCase() === 'project_task');

                // Keep people visible even when many tasks match.
                const visible = [
                    ...people.slice(0, 20),
                    ...tasks.slice(0, 20),
                ];

                if (!visible.length) {
                    closeMentionDropdown();
                    return;
                }
                mentionResults = visible;
                mentionActiveIndex = Math.min(mentionActiveIndex, mentionResults.length - 1);
                renderMentionDropdown(visible);
            };

            if (mentionFetchTimer) {
                clearTimeout(mentionFetchTimer);
            }
            mentionFetchTimer = setTimeout(async () => {
                const fetched = query ? await fetchMentionResults(query) : null;
                handleResults(fetched || mentionables);
            }, 150);
        };

        if (textarea) {
            resizeComposerInput();
            textarea.addEventListener('input', () => {
                resizeComposerInput();
                updateMentionDropdown();
                syncMentionsField();
                updateComposerState();
            });
            textarea.addEventListener('keydown', (event) => {
                if (!dropdown || dropdown.classList.contains('hidden')) {
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    mentionActiveIndex = (mentionActiveIndex + 1) % mentionResults.length;
                    renderMentionDropdown(mentionResults);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    mentionActiveIndex = (mentionActiveIndex - 1 + mentionResults.length) % mentionResults.length;
                    renderMentionDropdown(mentionResults);
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    if (mentionResults[mentionActiveIndex]) {
                        applyMention(mentionResults[mentionActiveIndex]);
                    }
                } else if (event.key === 'Escape') {
                    closeMentionDropdown();
                }
            });
        }

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (emojiPanel && !emojiPanel.classList.contains('hidden') && emojiButton
                && !emojiPanel.contains(target) && !emojiButton.contains(target)) {
                closeEmojiPanel();
            }
            if (!dropdown || dropdown.classList.contains('hidden')) {
                return;
            }
            if (dropdown.contains(target) || textarea === target) {
                return;
            }
            closeMentionDropdown();
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
                alert('Message cannot be empty.');
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
                    alert(await parseErrorMessage(response, 'Message update failed.'));
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
            if (!inlineSaveButton && !inlineCancelButton && !editButton && !deleteButton) {
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

            if (row.dataset.mutating === '1') {
                return;
            }

            if (isEditWindowExpired(row)) {
                refreshMessageActionAvailability();
                return;
            }

            row.dataset.mutating = '1';
            try {
                if (editButton) {
                    beginInlineEdit(row);
                    return;
                }

                if (deleteButton) {
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
                        alert(await parseErrorMessage(response, 'Message delete failed.'));
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

                syncMentionsField();
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
                            alert('Message cannot be empty.');
                            return;
                        }
                        formData.delete('attachment');
                        formData.delete('mentions');
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
                        alert(errorMessage);
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
                        }
                    } else if (!isEditing) {
                        const latestItems = await fetchMessages({ after_id: lastId, limit: 30 });
                        if (latestItems.length) {
                            appendItems(latestItems);
                            scrollToBottom();
                            updateReadStatus(lastId);
                        }
                    }
                    form.reset();
                    stopEditingMessage();
                    resizeComposerInput();
                    selectedMentions = [];
                    syncMentionsField();
                    closeMentionDropdown();
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
        scrollToBottom();
        updateReadStatus(lastId);
        sendPresence('active');
        scheduleIdle();
        connectStream();

        container.addEventListener('scroll', async () => {
            if (container.scrollTop <= 80) {
                loadOlder();
            }
            if (isNearBottom()) {
                updateReadStatus(lastId);
            }
        });

        const cleanup = () => {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
            if (presenceHeartbeatTimer) {
                clearInterval(presenceHeartbeatTimer);
                presenceHeartbeatTimer = null;
            }
            if (editWindowTimer) {
                clearInterval(editWindowTimer);
                editWindowTimer = null;
            }
            if (idleTimer) {
                clearTimeout(idleTimer);
                idleTimer = null;
            }
            if (attachmentPreviewUrl) {
                URL.revokeObjectURL(attachmentPreviewUrl);
                attachmentPreviewUrl = null;
            }
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
        };

        window.__projectChatCleanup = cleanup;
        window.addEventListener('beforeunload', cleanup);
    };

    if (document.querySelector('#appContent')?.dataset?.pageKey === pageKey) {
        window.PageInit[pageKey]();
    }
    })();
</script>
@endsection
