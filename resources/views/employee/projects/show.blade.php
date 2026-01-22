@extends('layouts.admin')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    @php
        $chatMessages = $chatMessages ?? collect();
        $chatMeta = $chatMeta ?? null;
        $chatLastMessageId = $chatMessages->last()?->id ?? 0;
        $chatOldestMessageId = $chatMessages->first()?->id ?? 0;
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Delivery</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
    </div>

    @php
        $stats = $taskStats ?? ['total' => 0, 'in_progress' => 0, 'completed' => 0, 'unread' => 0];
    @endphp
    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['total'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Total Tasks</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['in_progress'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">In Progress</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['completed'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Completed</div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
            <div class="text-2xl font-semibold text-slate-900">{{ $stats['unread'] }}</div>
            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Unread</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div class="card p-6">
        <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Customer</div>
                <div class="mt-2 font-semibold text-slate-900">{{ $project->customer?->name ?? '--' }}</div>
                <div class="text-xs text-slate-500">Project ID: {{ $project->id }}</div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Dates</div>
                <div class="mt-2 text-sm text-slate-700">
                    Start: {{ $project->start_date?->format($globalDateFormat) ?? '--' }}<br>
                    Expected end: {{ $project->expected_end_date?->format($globalDateFormat) ?? '--' }}<br>
                    Due: {{ $project->due_date?->format($globalDateFormat) ?? '--' }}
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Status</div>
                <div class="mt-2 text-sm text-slate-700">
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span>
                </div>
            </div>
        </div>

        @can('createTask', $project)
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                        <div class="text-xs text-slate-500">Task start and due dates are locked after creation.</div>
                    </div>
                </div>
            <form method="POST" action="{{ route('employee.projects.tasks.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-6" enctype="multipart/form-data">
                @csrf
                <div class="md:col-span-4">
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Task type</label>
                    <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        @foreach($taskTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-6">
                    <label class="text-xs text-slate-500">Description</label>
                    <input name="description" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Start date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Due date</label>
                    <input type="date" name="due_date" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Priority</label>
                    <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                    <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="customer_visible" value="0">
                    <input type="checkbox" name="customer_visible" value="1">
                    <span class="text-xs text-slate-600">Customer visible</span>
                </div>
                <div class="md:col-span-6 flex justify-end">
                        <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                    </div>
                </form>
            </div>
        @endcan

        @if($tasks && $tasks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2">Progress</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tasks as $task)
                            @php
                                $currentStatus = $task->status ?? 'pending';
                                $statusLabels = [
                                    'pending' => 'Open',
                                    'todo' => 'Open',
                                    'in_progress' => 'In Progress',
                                    'blocked' => 'Blocked',
                                    'completed' => 'Completed',
                                    'done' => 'Completed',
                                ];
                                $statusClasses = [
                                    'pending' => 'bg-slate-100 text-slate-600',
                                    'todo' => 'bg-slate-100 text-slate-600',
                                    'in_progress' => 'bg-amber-100 text-amber-700',
                                    'blocked' => 'bg-rose-100 text-rose-700',
                                    'completed' => 'bg-emerald-100 text-emerald-700',
                                    'done' => 'bg-emerald-100 text-emerald-700',
                                ];
                                $statusLabel = $statusLabels[$currentStatus] ?? ucfirst(str_replace('_', ' ', $currentStatus));
                                $statusClass = $statusClasses[$currentStatus] ?? 'bg-slate-100 text-slate-600';
                                $currentUser = auth()->user();
                                $employeeId = $currentUser?->employee?->id;
                                $hasSubtasks = (int) ($task->subtasks_count ?? 0) > 0;
                                $isAssigned = $employeeId && (
                                    ($task->assigned_type === 'employee' && (int) $task->assigned_id === (int) $employeeId)
                                    || ($task->assignee_id && (int) $task->assignee_id === (int) ($currentUser?->id))
                                    || $task->assignments
                                        ->where('assignee_type', 'employee')
                                        ->pluck('assignee_id')
                                        ->map(fn ($id) => (int) $id)
                                        ->contains((int) $employeeId)
                                );
                                $canChangeStatus = $currentUser?->isMasterAdmin()
                                    || $isAssigned
                                    || ($task->created_by
                                        && $currentUser
                                        && $task->created_by === $currentUser->id
                                        && ! $task->creatorEditWindowExpired($currentUser->id));
                                $canStartTask = ! $hasSubtasks
                                    && in_array($currentStatus, ['pending', 'todo'], true)
                                    && $canChangeStatus;
                                $canCompleteTask = $canChangeStatus
                                    && ! $hasSubtasks
                                    && ! in_array($task->status, ['completed', 'done'], true);
                            @endphp
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                        {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                                    </div>
                                    @if($task->description)
                                        <div class="text-xs text-slate-500">{{ $task->description }}</div>
                                    @endif
                                    @if($task->customer_visible)
                                        <div class="text-[11px] text-emerald-600 font-semibold">Customer visible</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Dates locked</div>
                                    Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                    Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-500 text-right align-top">
                                    <div class="flex justify-end">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold {{ $statusClass }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                    <div class="mt-2">Progress: {{ $task->progress ?? 0 }}%</div>
                                    @if($task->completed_at)
                                        <div>Completed at {{ $task->completed_at->format($globalDateFormat) }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right align-top">
                                    <a href="{{ route('employee.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
                                    @can('update', $task)
                                        @if($canStartTask)
                                            <form method="POST" action="{{ route('employee.projects.tasks.start', [$project, $task]) }}" class="mt-2 inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="rounded-full border border-amber-200 px-3 py-1 text-xs font-semibold text-amber-700 hover:border-amber-300">
                                                    Inprogress
                                                </button>
                                            </form>
                                        @endif
                                        @if($canCompleteTask)
                                            <form method="POST" action="{{ route('employee.projects.tasks.update', [$project, $task]) }}" class="mt-2 inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="rounded-full border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700 hover:border-emerald-300">
                                                    Complete
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        </div>

        <div class="card p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project Chat</div>
                    <div class="text-sm text-slate-500">Messages refresh every few seconds.</div>
                </div>
                @if($chatMeta)
                    <a href="{{ route('employee.projects.chat', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open full chat</a>
                @endif
            </div>

            @if($chatMeta)
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4">
                    <div id="project-chat-messages"
                         data-messages-url="{{ $chatMeta['messagesUrl'] }}"
                         data-read-url="{{ $chatMeta['readUrl'] }}"
                         data-last-id="{{ $chatLastMessageId }}"
                         data-oldest-id="{{ $chatOldestMessageId }}"
                         class="max-h-[50vh] space-y-4 overflow-y-auto pr-1 text-sm text-slate-700">
                        @include('projects.partials.project-chat-messages', [
                            'messages' => $chatMessages,
                            'project' => $project,
                            'attachmentRouteName' => $chatMeta['attachmentRouteName'],
                            'currentAuthorType' => $chatMeta['currentAuthorType'],
                            'currentAuthorId' => $chatMeta['currentAuthorId'],
                        ])
                    </div>
                </div>

                @if($chatMeta['canPost'])
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-white/80 p-4">
                        <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Post a message</div>
                        <form method="POST" action="{{ $chatMeta['postRoute'] }}" data-post-url="{{ $chatMeta['postMessagesUrl'] }}" enctype="multipart/form-data" class="mt-4 space-y-3" id="chatMessageForm">
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
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('project-chat-messages');
            const form = document.getElementById('chatMessageForm');
            const messagesUrl = @json($chatMeta ? $chatMeta['messagesUrl'] : '');
            const readUrl = container?.dataset?.readUrl || @json($chatMeta ? $chatMeta['readUrl'] : '');

            if (!container || !messagesUrl) {
                return;
            }

            let lastId = Number(container.dataset.lastId || {{ $chatLastMessageId }} || 0);
            let oldestId = Number(container.dataset.oldestId || {{ $chatOldestMessageId }} || 0);
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
