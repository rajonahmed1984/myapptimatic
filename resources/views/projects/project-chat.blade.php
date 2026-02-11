@extends($layout)

@section('title', 'Project Chat')
@section('page-title', 'Project Chat')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project Chat</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to project</a>
    </div>

    <div class="card p-6 space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversation</div>
                    <div class="text-xs text-slate-500">Messages update live when teammates post.</div>
                </div>
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
                <div class="mt-3 flex flex-wrap gap-2 text-xs" id="chatParticipants">
                    @foreach($participants as $participant)
                        @php
                            $key = $participant['key'] ?? '';
                            $status = $participantStatuses[$key] ?? 'offline';
                        @endphp
                        <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600" data-presence-key="{{ $key }}">
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
                 class="mt-4 max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                @include('projects.partials.project-chat-messages', [
                    'messages' => $messages,
                    'project' => $project,
                    'attachmentRouteName' => $attachmentRouteName,
                    'currentAuthorType' => $currentAuthorType,
                    'currentAuthorId' => $currentAuthorId,
                    'readReceipts' => $readReceipts ?? [],
                    'authorStatuses' => $authorStatuses ?? [],
                    'messageMentions' => $messageMentions ?? [],
                ])
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">AI Assistant</div>
                    <div class="text-xs text-slate-500">Auto summary, reply draft, sentiment & priority.</div>
                </div>
                <div class="flex items-center gap-3">
                    <span id="chat-ai-status" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                    <button type="button" id="chat-ai-generate" class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" @disabled(! $aiReady)>
                        Generate AI
                    </button>
                </div>
            </div>

            @if(! $aiReady)
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                    GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI suggestions.
                </div>
            @endif

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm md:col-span-2">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                    <div id="chat-ai-summary" class="mt-2 text-slate-700">
                        {{ $pinnedSummary['summary'] ?? 'Click Generate AI to analyze recent chat.' }}
                    </div>
                    <div id="chat-ai-generated-at" class="mt-2 text-[11px] text-slate-400">
                        @if(!empty($pinnedSummary['generated_at']))
                            Pinned · {{ $pinnedSummary['generated_at'] }}
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Signals</div>
                    <div class="mt-2 text-slate-600">Sentiment: <span id="chat-ai-sentiment">{{ $pinnedSummary['sentiment'] ?? '--' }}</span></div>
                    <div class="mt-1 text-slate-600">Priority: <span id="chat-ai-priority">{{ $pinnedSummary['priority'] ?? '--' }}</span></div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Action items</div>
                    <ul id="chat-ai-actions" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                        @if(!empty($pinnedSummary['action_items']) && is_array($pinnedSummary['action_items']))
                            @foreach($pinnedSummary['action_items'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        @else
                            <li>--</li>
                        @endif
                    </ul>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm md:col-span-2">
                    <div class="flex items-center justify-between">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Reply draft</div>
                        <button type="button" id="chat-ai-insert" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Insert into reply
                        </button>
                    </div>
                    <textarea id="chat-ai-reply" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" readonly>{{ $pinnedSummary['reply_draft'] ?? '' }}</textarea>
                </div>
            </div>
        </div>

        @if($canPost)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                <form method="POST" action="{{ $postRoute }}" data-post-url="{{ $postMessagesUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3" id="chatMessageForm">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-500">Message</label>
                        <div class="relative">
                            <textarea id="chatMessageInput" name="message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Share an update...">{{ old('message') }}</textarea>
                            <div id="chatMentionDropdown" class="absolute z-20 mt-2 hidden w-72 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div>
                        </div>
                        <input type="hidden" name="mentions" id="chatMentionsField" value="" />
                        @error('message')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Attachment (optional)</label>
                        <input name="attachment" type="file" accept="image/*,.pdf" class="mt-1 block w-full text-sm text-slate-600" />
                        <p class="mt-1 text-xs text-slate-500">Images or PDF up to 5MB.</p>
                        @error('attachment')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Send message</button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('project-chat-messages');
        const form = document.getElementById('chatMessageForm');
        const textarea = document.getElementById('chatMessageInput');
        const mentionsField = document.getElementById('chatMentionsField');
        const dropdown = document.getElementById('chatMentionDropdown');
        const participants = @json($participants ?? []);
        const participantsUrl = @json($participantsUrl ?? '');
        const presenceUrl = @json($presenceUrl ?? '');
        const streamUrl = container?.dataset?.streamUrl || @json($streamUrl ?? '');
        const messagesUrl = @json($messagesUrl);
        const readUrl = container?.dataset?.readUrl || @json($readUrl ?? '');
        const currentAuthorType = @json($currentAuthorType);
        const currentAuthorId = @json($currentAuthorId);
        const projectName = @json($project->name ?? 'Project');
        const aiSummaryRoute = @json($aiSummaryRoute ?? '');

        if (!container || !messagesUrl) {
            return;
        }

        let lastId = Number(container.dataset.lastId || {{ $lastMessageId }} || 0);
        let oldestId = Number(container.dataset.oldestId || {{ $oldestMessageId }} || 0);
        let lastReadId = lastId;
        let isLoadingOlder = false;
        let reachedStart = false;
        let pollTimer = null;
        let eventSource = null;
        const seenMessageIds = new Set();

        container.querySelectorAll('[data-message-id]').forEach((node) => {
            const id = Number(node.dataset.messageId || 0);
            if (id) {
                seenMessageIds.add(id);
            }
        });

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

        let notificationRequested = false;
        const canNotify = () => 'Notification' in window && Notification.permission === 'granted';
        const maybeRequestNotificationPermission = () => {
            if (!('Notification' in window)) {
                return;
            }
            if (Notification.permission !== 'default' || notificationRequested) {
                return;
            }
            notificationRequested = true;
            Notification.requestPermission().catch(() => {});
        };

        const shouldNotify = () => document.hidden || !document.hasFocus() || !isNearBottom();
        const showNotification = (item) => {
            if (!canNotify() || !item?.meta) {
                return;
            }
            if (String(item.meta.author_id) === String(currentAuthorId)
                && item.meta.author_type === currentAuthorType) {
                return;
            }
            if (!shouldNotify()) {
                return;
            }
            const title = item.meta.author ? `${item.meta.author} - ${projectName}` : projectName;
            const body = item.meta.snippet || 'New message';
            const notification = new Notification(title, { body });
            notification.onclick = () => {
                window.focus();
            };
        };

        const appendItems = (items, notify) => {
            if (!items || !items.length) {
                return;
            }
            container.querySelectorAll('.chat-seen-by').forEach((item) => item.remove());
            container.querySelectorAll('.chat-read-up-to').forEach((item) => item.remove());

            items.forEach((item) => {
                if (!item?.html || !item?.id) return;
                if (seenMessageIds.has(item.id)) return;
                container.insertAdjacentHTML('beforeend', item.html);
                seenMessageIds.add(item.id);
                lastId = Math.max(lastId, item.id);
                if (!oldestId) {
                    oldestId = item.id;
                }
                if (notify) {
                    showNotification(item);
                }
            });
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
                if (seenMessageIds.has(item.id)) continue;
                container.insertAdjacentHTML('afterbegin', item.html);
                seenMessageIds.add(item.id);
            }
            const oldestItemId = items[0]?.id;
            if (oldestItemId) {
                oldestId = oldestId ? Math.min(oldestId, oldestItemId) : oldestItemId;
            }
            const heightDiff = container.scrollHeight - previousHeight;
            container.scrollTop = previousTop + heightDiff;
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
                    appendItems(items, true);
                    if (keepAtBottom) {
                        scrollToBottom();
                        updateReadStatus(lastId);
                    }
                }
            }, 5000);
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
                    const payload = JSON.parse(event.data || '{}');
                    const items = payload?.items || [];
                    appendItems(items, true);
                    if (items.length && isNearBottom()) {
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

        setInterval(() => {
            sendPresence(presenceState);
        }, 15000);

        const aiButton = document.getElementById('chat-ai-generate');
        const aiStatus = document.getElementById('chat-ai-status');
        const aiSummary = document.getElementById('chat-ai-summary');
        const aiSentiment = document.getElementById('chat-ai-sentiment');
        const aiPriority = document.getElementById('chat-ai-priority');
        const aiActions = document.getElementById('chat-ai-actions');
        const aiReply = document.getElementById('chat-ai-reply');
        const aiInsert = document.getElementById('chat-ai-insert');
        const aiGeneratedAt = document.getElementById('chat-ai-generated-at');

        const setAiStatus = (label, cls) => {
            if (!aiStatus) return;
            aiStatus.textContent = label;
            aiStatus.className = `rounded-full px-3 py-1 text-xs font-semibold ${cls}`;
        };

        const renderActions = (items) => {
            if (!aiActions) return;
            aiActions.innerHTML = '';
            if (!items || !items.length) {
                const li = document.createElement('li');
                li.textContent = '--';
                aiActions.appendChild(li);
                return;
            }
            items.forEach((item) => {
                const li = document.createElement('li');
                li.textContent = item;
                aiActions.appendChild(li);
            });
        };

        if (aiInsert) {
            aiInsert.addEventListener('click', () => {
                if (!textarea || !aiReply) return;
                if (!aiReply.value.trim()) return;
                textarea.value = aiReply.value;
                textarea.focus();
            });
        }

        if (aiButton && aiSummaryRoute) {
            aiButton.addEventListener('click', async () => {
                setAiStatus('Generating...', 'bg-amber-100 text-amber-700');
                if (aiSummary) aiSummary.textContent = 'Working on the AI summary...';
                if (aiReply) aiReply.value = '';
                if (aiSentiment) aiSentiment.textContent = '--';
                if (aiPriority) aiPriority.textContent = '--';
                renderActions([]);

                try {
                    const response = await fetch(aiSummaryRoute, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                    });

                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.error || 'Failed to generate AI summary.');
                    }

                    if (payload.data) {
                        if (aiSummary) aiSummary.textContent = payload.data.summary || payload.raw || '--';
                        if (aiSentiment) aiSentiment.textContent = payload.data.sentiment || '--';
                        if (aiPriority) aiPriority.textContent = payload.data.priority || '--';
                        if (aiReply) aiReply.value = payload.data.reply_draft || '';
                        if (aiGeneratedAt) {
                            aiGeneratedAt.textContent = payload.data.generated_at
                                ? `Pinned · ${payload.data.generated_at}`
                                : '';
                        }
                        renderActions(Array.isArray(payload.data.action_items) ? payload.data.action_items : []);
                    } else if (aiSummary) {
                        aiSummary.textContent = payload.raw || '--';
                    }

                    setAiStatus('Updated', 'bg-emerald-100 text-emerald-700');
                } catch (error) {
                    if (aiSummary) aiSummary.textContent = error.message;
                    setAiStatus('Error', 'bg-rose-100 text-rose-700');
                }
            });
        }

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

        const getCaretCoordinates = (element, position) => {
            const div = document.createElement('div');
            const style = window.getComputedStyle(element);
            const properties = [
                'boxSizing',
                'width',
                'height',
                'overflowX',
                'overflowY',
                'borderTopWidth',
                'borderRightWidth',
                'borderBottomWidth',
                'borderLeftWidth',
                'paddingTop',
                'paddingRight',
                'paddingBottom',
                'paddingLeft',
                'fontStyle',
                'fontVariant',
                'fontWeight',
                'fontStretch',
                'fontSize',
                'fontSizeAdjust',
                'lineHeight',
                'fontFamily',
                'textAlign',
                'textTransform',
                'textIndent',
                'letterSpacing',
                'wordSpacing',
            ];
            properties.forEach((prop) => {
                div.style[prop] = style[prop];
            });
            div.style.position = 'absolute';
            div.style.visibility = 'hidden';
            div.style.whiteSpace = 'pre-wrap';
            div.style.wordWrap = 'break-word';
            div.style.top = '0';
            div.style.left = '-9999px';
            div.textContent = element.value.substring(0, position);
            const span = document.createElement('span');
            span.textContent = element.value.substring(position) || '.';
            div.appendChild(span);
            document.body.appendChild(div);
            const top = span.offsetTop + parseInt(style.borderTopWidth, 10) - element.scrollTop;
            const left = span.offsetLeft + parseInt(style.borderLeftWidth, 10) - element.scrollLeft;
            document.body.removeChild(div);
            return { top, left };
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
            if (!dropdown || !textarea || !mentionState) {
                return;
            }
            const caret = getCaretCoordinates(textarea, mentionState.end);
            const textareaRect = textarea.getBoundingClientRect();
            const containerRect = textarea.parentElement.getBoundingClientRect();
            dropdown.style.left = `${textareaRect.left - containerRect.left + caret.left}px`;
            dropdown.style.top = `${textareaRect.top - containerRect.top + caret.top + 24}px`;
        };

        const renderMentionDropdown = (items) => {
            if (!dropdown) {
                return;
            }
            dropdown.innerHTML = '';
            items.forEach((item, index) => {
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
        };

        const fetchMentionResults = async (query) => {
            if (!participantsUrl) {
                return null;
            }
            const url = new URL(participantsUrl, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('limit', '8');
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
                    })
                    .slice(0, 8);

                if (!filtered.length) {
                    closeMentionDropdown();
                    return;
                }
                mentionResults = filtered;
                mentionActiveIndex = Math.min(mentionActiveIndex, mentionResults.length - 1);
                renderMentionDropdown(filtered);
            };

            if (mentionFetchTimer) {
                clearTimeout(mentionFetchTimer);
            }
            mentionFetchTimer = setTimeout(async () => {
                const fetched = query ? await fetchMentionResults(query) : null;
                handleResults(fetched || participants);
            }, 150);
        };

        if (textarea) {
            textarea.addEventListener('input', () => {
                updateMentionDropdown();
                syncMentionsField();
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
            textarea.addEventListener('focus', () => {
                maybeRequestNotificationPermission();
            });
        }

        document.addEventListener('click', (event) => {
            if (!dropdown || dropdown.classList.contains('hidden')) {
                return;
            }
            if (dropdown.contains(event.target) || textarea === event.target) {
                return;
            }
            closeMentionDropdown();
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

                maybeRequestNotificationPermission();
                syncMentionsField();

                try {
                    const postUrl = form.dataset.postUrl || form.action;
                    const formData = new FormData(form);

                    const response = await fetch(postUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });

                    if (!response.ok) {
                        alert('Message send failed.');
                        return;
                    }

                    const payload = await response.json();
                    const item = payload?.data?.item;
                    if (item?.html) {
                        appendItems([item], false);
                        scrollToBottom();
                        updateReadStatus(lastId);
                    }
                    form.reset();
                    selectedMentions = [];
                    syncMentionsField();
                    closeMentionDropdown();
                } finally {
                    form.dataset.submitting = '0';
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

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

        window.addEventListener('beforeunload', () => {
            if (eventSource) {
                eventSource.close();
            }
        });
    });
</script>
@endsection

