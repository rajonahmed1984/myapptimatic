@extends($layout)

@section('title', 'Task Chat')
@section('page-title', 'Task Chat')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Task Chat</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $task->title }}</div>
            <div class="text-sm text-slate-500">Project: {{ $project->name }}</div>
        </div>
        <a href="{{ $backRoute }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to project</a>
    </div>

    <div class="card p-6 space-y-6">
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

        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Conversation</div>
                    <div class="text-xs text-slate-500">Messages refresh every few seconds.</div>
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
                 class="mt-4 max-h-[60vh] space-y-4 overflow-y-auto pr-1">
                @include('projects.partials.task-chat-messages', [
                    'messages' => $messages,
                    'project' => $project,
                    'task' => $task,
                    'attachmentRouteName' => $attachmentRouteName,
                    'currentAuthorType' => $currentAuthorType,
                    'currentAuthorId' => $currentAuthorId,
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
                    <span id="task-chat-ai-status" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                    <button type="button" id="task-chat-ai-generate" class="rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800" @disabled(! $aiReady)>
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
                    <div id="task-chat-ai-summary" class="mt-2 text-slate-700">
                        {{ $pinnedSummary['summary'] ?? 'Click Generate AI to analyze recent chat.' }}
                    </div>
                    <div id="task-chat-ai-generated-at" class="mt-2 text-[11px] text-slate-400">
                        @if(!empty($pinnedSummary['generated_at']))
                            Pinned · {{ $pinnedSummary['generated_at'] }}
                        @endif
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Signals</div>
                    <div class="mt-2 text-slate-600">Sentiment: <span id="task-chat-ai-sentiment">{{ $pinnedSummary['sentiment'] ?? '--' }}</span></div>
                    <div class="mt-1 text-slate-600">Priority: <span id="task-chat-ai-priority">{{ $pinnedSummary['priority'] ?? '--' }}</span></div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Action items</div>
                    <ul id="task-chat-ai-actions" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
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
                        <button type="button" id="task-chat-ai-insert" class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Insert into reply
                        </button>
                    </div>
                    <textarea id="task-chat-ai-reply" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" readonly>{{ $pinnedSummary['reply_draft'] ?? '' }}</textarea>
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
                        <textarea id="taskChatMessageInput" name="message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Share an update...">{{ old('message') }}</textarea>
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
        const container = document.getElementById('task-chat-messages');
        const form = document.getElementById('chatMessageForm');
        const messagesUrl = @json($messagesUrl);
        const readUrl = container?.dataset?.readUrl || @json($readUrl ?? '');
        const aiSummaryRoute = @json($aiSummaryRoute ?? '');
        const aiButton = document.getElementById('task-chat-ai-generate');
        const aiStatus = document.getElementById('task-chat-ai-status');
        const aiSummary = document.getElementById('task-chat-ai-summary');
        const aiSentiment = document.getElementById('task-chat-ai-sentiment');
        const aiPriority = document.getElementById('task-chat-ai-priority');
        const aiActions = document.getElementById('task-chat-ai-actions');
        const aiReply = document.getElementById('task-chat-ai-reply');
        const aiInsert = document.getElementById('task-chat-ai-insert');
        const replyInput = document.getElementById('taskChatMessageInput');
        const aiGeneratedAt = document.getElementById('task-chat-ai-generated-at');

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
                if (!replyInput || !aiReply) return;
                if (!aiReply.value.trim()) return;
                replyInput.value = aiReply.value;
                replyInput.focus();
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

        if (!container || !messagesUrl) {
            return;
        }

            let lastId = Number(container.dataset.lastId || {{ $lastMessageId }} || 0);
            let oldestId = Number(container.dataset.oldestId || {{ $oldestMessageId }} || 0);
            let isLoadingOlder = false;
            let reachedStart = false;

            const scrollToBottom = () => {
                container.scrollTop = container.scrollHeight;
            };

            const isNearBottom = () => {
                const threshold = 120;
                return (container.scrollHeight - container.scrollTop - container.clientHeight) < threshold;
            };

            const appendItems = (items) => {
                if (!items || !items.length) {
                    return;
                }
                items.forEach((item) => {
                    if (!item?.html) return;
                    container.insertAdjacentHTML('beforeend', item.html);
                    if (item.id) {
                        lastId = Math.max(lastId, item.id);
                        if (!oldestId) {
                            oldestId = item.id;
                        }
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
                    if (!item?.html) continue;
                    container.insertAdjacentHTML('afterbegin', item.html);
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
                            appendItems([item]);
                            scrollToBottom();
                            updateReadStatus(lastId);
                        }
                        form.reset();
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

            container.addEventListener('scroll', async () => {
                if (container.scrollTop <= 80) {
                    loadOlder();
                }
            });

            setInterval(async () => {
                const keepAtBottom = isNearBottom();
                const items = await fetchMessages({ after_id: lastId, limit: 30 });
                if (items.length) {
                    appendItems(items);
                    if (keepAtBottom) {
                        scrollToBottom();
                        updateReadStatus(lastId);
                    }
                }
            }, 5000);
        });
    </script>
@endsection
