@extends('layouts.admin')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket')

@section('content')
    @php
        $statusLabel = ucfirst(str_replace('_', ' ', $ticket->status));
        $statusClasses = match ($ticket->status) {
            'open' => 'bg-amber-100 text-amber-700',
            'answered' => 'bg-emerald-100 text-emerald-700',
            'customer_reply' => 'bg-blue-100 text-blue-700',
            'closed' => 'bg-slate-100 text-slate-600',
            default => 'bg-slate-100 text-slate-600',
        };
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="section-label">Ticket</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $ticket->subject }}</h1>
            <div class="mt-2 text-sm text-slate-500">
                TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }} - {{ $ticket->customer->name }} - Priority {{ ucfirst($ticket->priority) }}
            </div>
        </div>
        <div class="flex flex-col items-end gap-3 text-sm">
            <span class="rounded-full px-4 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
            <div class="text-slate-500">Opened {{ $ticket->created_at->format($globalDateFormat . ' H:i') }}</div>
            <form method="POST" action="{{ route('admin.support-tickets.status', $ticket) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $ticket->isClosed() ? 'open' : 'closed' }}" />
                <button type="submit" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                    {{ $ticket->isClosed() ? 'Reopen ticket' : 'Close ticket' }}
                </button>
            </form>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <div class="section-label">Ticket details</div>
        <form method="POST" action="{{ route('admin.support-tickets.update', $ticket) }}" class="mt-4 grid gap-4 md:grid-cols-2">
            @csrf
            @method('PATCH')
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Subject</label>
                <input name="subject" value="{{ old('subject', $ticket->subject) }}" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Priority</label>
                <select name="priority" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="low" @selected(old('priority', $ticket->priority) === 'low')>Low</option>
                    <option value="medium" @selected(old('priority', $ticket->priority) === 'medium')>Medium</option>
                    <option value="high" @selected(old('priority', $ticket->priority) === 'high')>High</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Status</label>
                <select name="status" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                    <option value="open" @selected(old('status', $ticket->status) === 'open')>Open</option>
                    <option value="answered" @selected(old('status', $ticket->status) === 'answered')>Answered</option>
                    <option value="customer_reply" @selected(old('status', $ticket->status) === 'customer_reply')>Customer Reply</option>
                    <option value="closed" @selected(old('status', $ticket->status) === 'closed')>Closed</option>
                </select>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Save changes</button>
            </div>
        </form>
        <div class="mt-4 flex justify-end">
            <form method="POST" action="{{ route('admin.support-tickets.destroy', $ticket) }}" onsubmit="return confirm('Delete this ticket and all replies?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-5 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:text-rose-500">Delete ticket</button>
            </form>
        </div>
    </div>

    <div class="card mt-6 p-6" id="ai-ticket-helper">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="section-label">AI Assistant</div>
                <div class="mt-1 text-sm text-slate-500">Get a quick summary and a suggested reply.</div>
            </div>
            <div class="flex items-center gap-3">
                <span id="ai-status-badge" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Ready</span>
                <button type="button" id="ai-generate-btn" class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800" @disabled(! $aiReady)>
                    Generate AI
                </button>
            </div>
        </div>

        @if(! $aiReady)
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                GOOGLE_AI_API_KEY is missing. Add it to .env to enable AI suggestions.
            </div>
        @endif

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</div>
                <div id="ai-summary-text" class="mt-2 text-slate-700">Click Generate AI to analyze this ticket.</div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Signals</div>
                <div class="mt-2 text-slate-600">Category: <span id="ai-category">--</span></div>
                <div class="mt-1 text-slate-600">Urgency: <span id="ai-urgency">--</span></div>
                <div class="mt-1 text-slate-600">Sentiment: <span id="ai-sentiment">--</span></div>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Next steps</div>
                <ul id="ai-next-steps" class="mt-2 list-disc space-y-1 pl-4 text-slate-700">
                    <li>--</li>
                </ul>
            </div>
            <div class="rounded-2xl border border-slate-100 bg-white p-4 text-sm">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Suggested reply</div>
                <textarea id="ai-suggested-reply" rows="6" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700" readonly></textarea>
                <div class="mt-3 flex justify-end">
                    <button type="button" id="ai-insert-reply" class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Insert into reply</button>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-8 space-y-4">
        @forelse($ticket->replies as $reply)
            <div class="flex {{ $reply->is_admin ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm shadow-sm">
                    <div class="flex items-center justify-between text-xs text-slate-500">
                        <span>{{ $reply->user?->name ?? ($reply->is_admin ? 'Admin' : 'Client') }}</span>
                        <span>{{ $reply->created_at->format($globalDateFormat . ' H:i') }}</span>
                    </div>
                    <div class="mt-3 whitespace-pre-line text-slate-700">{{ $reply->message }}</div>
                    @if($reply->attachment_path)
                        <div class="mt-3 text-xs text-slate-500">
                            Attachment:
                            <a href="{{ $reply->attachmentUrl() }}" target="_blank" class="font-semibold text-teal-600 hover:text-teal-500">
                                {{ $reply->attachmentName() }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card-muted p-4 text-sm text-slate-500">No replies yet.</div>
        @endforelse
    </div>

    <div class="card mt-8 p-6">
        <div class="section-label">Post reply</div>
        <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket) }}" class="mt-4 space-y-4" enctype="multipart/form-data">
            @csrf
            <textarea id="ticket-reply-message" name="message" rows="5" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">{{ old('message') }}</textarea>
            <div>
                <label class="text-sm text-slate-600">Attachment (image/PDF)</label>
                <input name="attachment" type="file" accept="image/*,.pdf" class="mt-2 block w-full text-sm text-slate-600" />
                @error('attachment')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="rounded-full bg-teal-500 px-5 py-2 text-sm font-semibold text-white">Send reply</button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const button = document.getElementById('ai-generate-btn');
                const status = document.getElementById('ai-status-badge');
                const summary = document.getElementById('ai-summary-text');
                const category = document.getElementById('ai-category');
                const urgency = document.getElementById('ai-urgency');
                const sentiment = document.getElementById('ai-sentiment');
                const nextSteps = document.getElementById('ai-next-steps');
                const suggestedReply = document.getElementById('ai-suggested-reply');
                const insertBtn = document.getElementById('ai-insert-reply');
                const replyBox = document.getElementById('ticket-reply-message');

                const setStatus = (label, cls) => {
                    if (!status) return;
                    status.textContent = label;
                    status.className = `rounded-full px-3 py-1 text-xs font-semibold ${cls}`;
                };

                const setText = (el, value, fallback = '--') => {
                    if (!el) return;
                    el.textContent = value || fallback;
                };

                const renderSteps = (items) => {
                    if (!nextSteps) return;
                    nextSteps.innerHTML = '';
                    if (!items || !items.length) {
                        const li = document.createElement('li');
                        li.textContent = '--';
                        nextSteps.appendChild(li);
                        return;
                    }
                    items.forEach((item) => {
                        const li = document.createElement('li');
                        li.textContent = item;
                        nextSteps.appendChild(li);
                    });
                };

                if (insertBtn) {
                    insertBtn.addEventListener('click', () => {
                        if (!replyBox || !suggestedReply) return;
                        if (suggestedReply.value.trim() === '') return;
                        replyBox.value = suggestedReply.value;
                        replyBox.focus();
                    });
                }

                if (!button) return;

                button.addEventListener('click', async () => {
                    setStatus('Generating...', 'bg-amber-100 text-amber-700');
                    if (summary) summary.textContent = 'Working on the AI summary...';
                    if (suggestedReply) suggestedReply.value = '';

                    try {
                        const response = await fetch("{{ route('admin.support-tickets.ai', $ticket) }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                                'Accept': 'application/json',
                            },
                        });

                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.error || 'Failed to generate AI summary.');
                        }

                        if (payload.data) {
                            setText(summary, payload.data.summary, payload.raw || '--');
                            setText(category, payload.data.category);
                            setText(urgency, payload.data.urgency);
                            setText(sentiment, payload.data.sentiment);
                            renderSteps(Array.isArray(payload.data.next_steps) ? payload.data.next_steps : []);
                            if (suggestedReply) {
                                suggestedReply.value = payload.data.suggested_reply || '';
                            }
                        } else {
                            setText(summary, payload.raw || '--');
                            renderSteps([]);
                        }

                        setStatus('Updated', 'bg-emerald-100 text-emerald-700');
                    } catch (error) {
                        if (summary) summary.textContent = error.message;
                        setStatus('Error', 'bg-rose-100 text-rose-700');
                    }
                });
            });
        </script>
    @endpush
@endsection


