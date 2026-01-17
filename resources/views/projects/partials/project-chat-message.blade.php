@php
    $isOwn = $message->author_type === $currentAuthorType
        && (string) $message->author_id === (string) $currentAuthorId;
    $safeMessage = $message->message !== null ? e($message->message) : '';
    $linkedMessage = $safeMessage !== ''
        ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-teal-600 hover:text-teal-500 underline" target="_blank" rel="noopener">$1</a>', $safeMessage)
        : '';
    $seenBy = $seenBy ?? [];
    $authorStatus = $authorStatus ?? 'offline';
    $statusDotClass = $authorStatus === 'online'
        ? 'bg-emerald-500'
        : ($authorStatus === 'away' ? 'bg-amber-400' : 'bg-rose-500');
    $statusLabel = $authorStatus === 'online'
        ? 'Online'
        : ($authorStatus === 'away' ? 'Away' : 'Offline');
    $latestMessageId = $latestMessageId ?? 0;
    $allParticipantsReadUpTo = $allParticipantsReadUpTo ?? null;
    $showLatestMeta = $message->id === $latestMessageId;
@endphp
<div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}">
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white/90 p-3 text-sm text-slate-700">
        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="flex items-center gap-2 font-semibold text-slate-700">
                <span class="h-2 w-2 rounded-full {{ $statusDotClass }}" title="{{ $statusLabel }}"></span>
                {{ $message->authorName() }}
            </span>
            <span>{{ $message->authorTypeLabel() }}</span>
            <span>{{ $message->created_at?->format('M d, Y H:i') ?? '' }}</span>
        </div>
        @if($linkedMessage !== '')
            <div class="mt-2 text-sm text-slate-700 whitespace-pre-wrap">{!! $linkedMessage !!}</div>
        @endif
        @if($message->attachment_path)
            <div class="mt-2">
                @if($message->isImageAttachment())
                    <a href="{{ route($attachmentRouteName, [$project, $message]) }}" target="_blank" rel="noopener">
                        <img src="{{ route($attachmentRouteName, [$project, $message]) }}" alt="Attachment" class="max-h-64 rounded-xl border border-slate-200">
                    </a>
                @else
                    <a href="{{ route($attachmentRouteName, [$project, $message]) }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                        Download {{ $message->attachmentName() ?? 'attachment' }}
                    </a>
                @endif
            </div>
        @endif
        @if($showLatestMeta && !empty($seenBy))
            <div class="mt-2 text-[11px] text-slate-400 chat-seen-by">
                Seen by {{ implode(', ', $seenBy) }}
            </div>
        @endif
        @if($showLatestMeta && !empty($allParticipantsReadUpTo))
            <div class="mt-1 text-[11px] text-slate-400 chat-read-up-to">
                All participants have read up to {{ $allParticipantsReadUpTo['label'] ?? '' }}
            </div>
        @endif
    </div>
</div>
