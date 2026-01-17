@extends('layouts.client')

@section('title', 'Project #'.$project->id)
@section('page-title', 'Project')

@section('content')
    @php
        $chatMessages = $chatMessages ?? collect();
        $chatMeta = $chatMeta ?? null;
        $chatLastMessageId = $chatMessages->last()?->id ?? 0;
        $chatOldestMessageId = $chatMessages->first()?->id ?? 0;
        $softwareOverhead = (float) ($project->software_overhead ?? 0);
        $websiteOverhead = (float) ($project->website_overhead ?? 0);
        $overheadTotal = $softwareOverhead + $websiteOverhead;
        $budgetWithOverhead = (float) ($project->budget_amount ?? 0) + $overheadTotal;
    @endphp
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Project</div>
            <div class="text-2xl font-semibold text-slate-900">{{ $project->name }}</div>
            <div class="text-sm text-slate-500">Status: {{ ucfirst(str_replace('_', ' ', $project->status)) }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <div class="card p-6">
            <div class="grid gap-4 md:grid-cols-3 text-sm text-slate-700">
            <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Project ID</div>
                <div class="mt-2 font-semibold text-slate-900">#{{ $project->id }}</div>
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
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Financials</div>
                    <div class="mt-2 text-sm text-slate-700">
                        Budget: {{ $project->total_budget ? $project->currency.' '.number_format($project->total_budget, 2) : '--' }}<br>
                        Initial payment: {{ $project->initial_payment_amount ? $project->currency.' '.number_format($project->initial_payment_amount, 2) : '--' }}<br>
                        Total overhead: {{ $project->currency ?? '' }}{{ number_format($overheadTotal, 2) }}<br>
                        Budget with overhead: {{ $project->currency ?? '' }}{{ number_format($budgetWithOverhead, 2) }}
                    </div>
                @if(!empty($initialInvoice))
                    <div class="mt-2 text-xs text-slate-500">
                        Initial invoice: #{{ $initialInvoice->number ?? $initialInvoice->id }} ({{ ucfirst($initialInvoice->status) }})
                        <a href="{{ route('client.invoices.show', $initialInvoice) }}" class="text-teal-700 hover:text-teal-600">View invoice</a>
                    </div>
                @endif
            </div>
        </div>

        @if(!empty($maintenances) && $maintenances->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Maintenance</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Cycle</th>
                            <th class="px-3 py-2">Next Billing</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-right">Invoices</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($maintenances as $maintenance)
                            @php $latestInvoice = $maintenance->invoices->first(); @endphp
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2">{{ $maintenance->title }}</td>
                                <td class="px-3 py-2">{{ ucfirst($maintenance->billing_cycle) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ $maintenance->next_billing_date?->format($globalDateFormat) ?? '--' }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full border px-2 py-0.5 text-xs font-semibold {{ $maintenance->status === 'active' ? 'border-emerald-200 text-emerald-700 bg-emerald-50' : ($maintenance->status === 'paused' ? 'border-amber-200 text-amber-700 bg-amber-50' : 'border-slate-200 text-slate-600 bg-slate-50') }}">
                                        {{ ucfirst($maintenance->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">{{ $maintenance->currency }} {{ number_format((float) $maintenance->amount, 2) }}</td>
                                <td class="px-3 py-2 text-right text-xs text-slate-600">
                                    {{ $maintenance->invoices?->count() ?? 0 }}
                                    @if($latestInvoice)
                                        <div>
                                            <a href="{{ route('client.invoices.show', $latestInvoice) }}" class="text-teal-700 hover:text-teal-600">Latest #{{ $latestInvoice->number ?? $latestInvoice->id }}</a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Add Task</div>
                    <div class="text-xs text-slate-500">Dates and assignment are managed internally and locked after creation.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('client.projects.tasks.store', $project) }}" class="mt-4 grid gap-3 md:grid-cols-3" enctype="multipart/form-data">
                @csrf
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Title</label>
                    <input name="title" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs text-slate-500">Description</label>
                    <input name="description" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Task type</label>
                    <select name="task_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm" required>
                        @foreach($taskTypeOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Priority</label>
                    <select name="priority" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
                        @foreach($priorityOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="text-xs text-slate-500">Attachment (required for Upload type)</label>
                    <input type="file" name="attachment" accept=".png,.jpg,.jpeg,.webp,.pdf,.docx,.xlsx" class="mt-1 w-full text-xs text-slate-600">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Add task</button>
                </div>
            </form>
        </div>

        @if($tasks && $tasks->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-slate-200 bg-white/80 p-4 text-sm text-slate-700">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400 mb-2">Tasks (customer-visible)</div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                        <tr class="text-xs uppercase tracking-[0.2em] text-slate-500">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Dates</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($tasks as $task)
                            <tr class="border-t border-slate-100 align-top">
                                <td class="px-3 py-2">
                                    <div class="font-semibold text-slate-900">{{ $task->title }}</div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">
                                        {{ $taskTypeOptions[$task->task_type] ?? ucfirst($task->task_type ?? 'Task') }}
                                    </div>
                                    @if($task->description)
                                        <div class="text-xs text-slate-500">{{ $task->description }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-600">
                                    Start: {{ $task->start_date?->format($globalDateFormat) ?? '--' }}<br>
                                    Due: {{ $task->due_date?->format($globalDateFormat) ?? '--' }}
                                </td>
                                <td class="px-3 py-2 text-right align-top">
                                    <a href="{{ route('client.projects.tasks.show', [$project, $task]) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open Task</a>
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
                    <a href="{{ route('client.projects.chat', $project) }}" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Open full chat</a>
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
