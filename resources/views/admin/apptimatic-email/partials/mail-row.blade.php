@php
    $isSelected = isset($selectedMessage['id']) && (string) $selectedMessage['id'] === (string) ($message['id'] ?? '');
    $isUnread = (bool) ($message['unread'] ?? false);
@endphp

<a
    href="{{ route('admin.apptimatic-email.show', ['message' => $message['id']]) }}"
    class="{{ $isSelected ? 'bg-teal-50/70' : 'bg-white hover:bg-slate-50' }} group flex items-start gap-3 border-b border-slate-100 px-4 py-3 transition"
>
    <span class="{{ $isUnread ? 'bg-teal-500' : 'border border-slate-300 bg-transparent' }} mt-1 h-2.5 w-2.5 shrink-0 rounded-full"></span>

    <div class="min-w-0 flex-1">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm {{ $isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700' }}">
                    {{ $message['sender_name'] ?? 'Unknown sender' }}
                </div>
                <div class="mt-0.5 truncate text-sm text-slate-700">
                    <span class="{{ $isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700' }}">{{ $message['subject'] ?? '(No subject)' }}</span>
                    <span class="text-slate-500"> - {{ $message['snippet'] ?? '' }}</span>
                </div>
            </div>

            <div class="shrink-0 text-xs text-slate-500">
                {{ ($message['received_at'] ?? null)?->format('M d, H:i') ?? '' }}
            </div>
        </div>

        <div class="mt-2 hidden items-center gap-2 text-slate-400 group-hover:flex">
            <span title="Star coming soon">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.48 3.5 2.1 4.26 4.7.68-3.4 3.3.8 4.66-4.2-2.2-4.2 2.2.8-4.66-3.4-3.3 4.7-.68 2.1-4.26Z" />
                </svg>
            </span>
            <span title="Archive coming soon">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h18M5.5 7.5v11A1.5 1.5 0 0 0 7 20h10a1.5 1.5 0 0 0 1.5-1.5v-11M9 12h6" />
                </svg>
            </span>
            <span title="Delete coming soon">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-9 0V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-7 0 1 12h6l1-12" />
                </svg>
            </span>
        </div>
    </div>
</a>
