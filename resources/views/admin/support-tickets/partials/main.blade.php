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

<div id="ticketMainWrap">
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
            <form method="POST" action="{{ route('admin.support-tickets.status', $ticket) }}" data-ajax-form="true">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $ticket->isClosed() ? 'open' : 'closed' }}" />
                <button type="submit" class="rounded-full border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                    {{ $ticket->isClosed() ? 'Reopen ticket' : 'Close ticket' }}
                </button>
            </form>
        </div>
    </div>

    <div class="card mt-6 p-6">
        <div class="section-label">Ticket details</div>
        <form method="POST" action="{{ route('admin.support-tickets.update', $ticket) }}" class="mt-4 grid gap-4 md:grid-cols-2" data-ajax-form="true">
            @csrf
            @method('PATCH')
            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Subject</label>
                <input name="subject" value="{{ old('subject', $ticket->subject) }}" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm" />
            </div>
            <div>
                <label class="text-sm text-slate-600">Priority</label>
                <select name="priority" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
                    <option value="low" @selected(old('priority', $ticket->priority) === 'low')>Low</option>
                    <option value="medium" @selected(old('priority', $ticket->priority) === 'medium')>Medium</option>
                    <option value="high" @selected(old('priority', $ticket->priority) === 'high')>High</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Status</label>
                <select name="status" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm">
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
            <form
                method="POST"
                action="{{ route('admin.support-tickets.destroy', $ticket) }}"
                data-ajax-form="true"
                data-delete-confirm
                data-confirm-name="TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}"
                data-confirm-title="Delete ticket TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}?"
                data-confirm-description="This will delete the ticket and all replies."
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-full border border-rose-200 px-5 py-2 text-sm font-semibold text-rose-600 hover:border-rose-300 hover:text-rose-500">Delete ticket</button>
            </form>
        </div>
    </div>

    <div id="replies" class="mt-8 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50/80 px-5 py-4">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Conversation</div>
                <div class="mt-1 text-sm text-slate-600">
                    {{ $ticket->replies->count() }} {{ \Illuminate\Support\Str::plural('message', $ticket->replies->count()) }}
                </div>
            </div>
            <a href="#reply-box" class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                Reply now
            </a>
        </div>

        <div class="space-y-4 bg-slate-100/50 p-5">
            @forelse($ticket->replies as $reply)
                @php
                    $isAdminReply = (bool) $reply->is_admin;
                    $authorName = $reply->user?->name ?? ($isAdminReply ? 'Admin Team' : 'Client');
                    $initial = \Illuminate\Support\Str::substr((string) $authorName, 0, 1);
                @endphp
                <div class="flex gap-3 {{ $isAdminReply ? 'justify-end' : 'justify-start' }}">
                    @if(! $isAdminReply)
                        <div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-600">
                            {{ strtoupper($initial) }}
                        </div>
                    @endif

                    <div class="max-w-3xl rounded-2xl border px-4 py-3 shadow-sm {{ $isAdminReply ? 'border-teal-200 bg-teal-50/70' : 'border-slate-200 bg-white' }}">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-semibold {{ $isAdminReply ? 'text-teal-700' : 'text-slate-700' }}">{{ $authorName }}</span>
                            <span class="text-slate-400">|</span>
                            <span class="text-slate-500">{{ $reply->created_at->format($globalDateFormat . ' H:i') }}</span>
                            <span class="rounded-full px-2 py-0.5 font-semibold {{ $isAdminReply ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' }}">
                                {{ $isAdminReply ? 'Staff' : 'Client' }}
                            </span>
                        </div>

                        <div class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $reply->message }}</div>

                        @if($reply->attachment_path)
                            <div class="mt-3 rounded-xl border border-slate-200 bg-white/80 px-3 py-2 text-xs text-slate-600">
                                Attachment:
                                <a href="{{ $reply->attachmentUrl() }}" target="_blank" class="ml-1 font-semibold text-teal-600 hover:text-teal-500">
                                    {{ $reply->attachmentName() }}
                                </a>
                            </div>
                        @endif
                    </div>

                    @if($isAdminReply)
                        <div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-teal-200 bg-teal-100 text-xs font-semibold text-teal-700">
                            {{ strtoupper($initial) }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                    No replies yet. Start the conversation with your first response.
                </div>
            @endforelse
        </div>
    </div>

    <div id="reply-box" class="card mt-6 p-6">
        <div class="section-label">Post reply</div>
        <div class="mt-1 text-xs text-slate-500">Write a clear response. Keep it short, then attach proof if needed.</div>
        <form method="POST" action="{{ route('admin.support-tickets.reply', $ticket) }}" class="mt-4 space-y-4" enctype="multipart/form-data" data-ajax-form="true">
            @csrf
            <textarea id="ticket-reply-message" name="message" rows="6" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700" placeholder="Type your reply...">{{ old('message') }}</textarea>
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
</div>
