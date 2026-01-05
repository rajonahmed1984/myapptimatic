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
            <textarea name="message" rows="5" required class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">{{ old('message') }}</textarea>
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
@endsection


