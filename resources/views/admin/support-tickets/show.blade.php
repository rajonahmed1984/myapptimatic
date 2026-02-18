@extends('layouts.admin')

@section('title', 'Support Ticket')
@section('page-title', 'Support Ticket')

@section('content')
    @include('admin.support-tickets.partials.main', ['ticket' => $ticket])

    <div class="card mt-6 p-6" id="ai-ticket-helper" data-ai-url="{{ route('admin.support-tickets.ai', $ticket) }}">
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
                <textarea id="ai-suggested-reply" rows="6" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700" readonly></textarea>
                <div class="mt-3 flex justify-end">
                    <button type="button" id="ai-insert-reply" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">Insert into reply</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script data-script-key="admin-support-ticket-ai">
            (() => {
                const pageKey = 'admin.support-tickets.show';
                window.PageInit = window.PageInit || {};
                if (typeof window.PageInit[pageKey] === 'function') {
                    return;
                }

                window.PageInit[pageKey] = () => {
                    const root = document.getElementById('ai-ticket-helper');
                    if (!root || root.dataset.aiInit === '1') {
                        return;
                    }

                    const button = document.getElementById('ai-generate-btn');
                    const status = document.getElementById('ai-status-badge');
                    const summary = document.getElementById('ai-summary-text');
                    const category = document.getElementById('ai-category');
                    const urgency = document.getElementById('ai-urgency');
                    const sentiment = document.getElementById('ai-sentiment');
                    const nextSteps = document.getElementById('ai-next-steps');
                    const suggestedReply = document.getElementById('ai-suggested-reply');
                    const insertBtn = document.getElementById('ai-insert-reply');
                    const aiUrl = root.dataset.aiUrl || '';

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
                            const replyBox = document.getElementById('ticket-reply-message');
                            if (!replyBox || !suggestedReply) return;
                            if (suggestedReply.value.trim() === '') return;
                            replyBox.value = suggestedReply.value;
                            replyBox.focus();
                        });
                    }

                    if (button && aiUrl !== '') {
                        button.addEventListener('click', async () => {
                            setStatus('Generating...', 'bg-amber-100 text-amber-700');
                            if (summary) summary.textContent = 'Working on the AI summary...';
                            if (suggestedReply) suggestedReply.value = '';

                            try {
                                const response = await fetch(aiUrl, {
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
                    }

                    root.dataset.aiInit = '1';
                };
            })();
        </script>
    @endpush
@endsection
