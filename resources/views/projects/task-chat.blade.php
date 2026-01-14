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

        @if($canPost)
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                <form method="POST" action="{{ $postRoute }}" data-post-url="{{ $postMessagesUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3" id="chatMessageForm">
                    @csrf
                    <div>
                        <label class="text-xs text-slate-500">Message</label>
                        <textarea name="message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" placeholder="Share an update...">{{ old('message') }}</textarea>
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
                const token = document.querySelector('[name="_token"]')?.value || '';
                const formData = new FormData();
                formData.append('_token', token);
                formData.append('last_read_id', readId);

                try {
                    await fetch(readUrl, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                } catch (e) {
                    // Ignore read status failures.
                }
            };

            if (form) {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
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
