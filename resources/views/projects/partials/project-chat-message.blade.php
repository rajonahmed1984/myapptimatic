@php
    $isOwn = $message->author_type === $currentAuthorType
        && (string) $message->author_id === (string) $currentAuthorId;
    $safeMessage = $message->message !== null ? e($message->message) : '';
    $mentionMatches = $mentionMatches ?? [];
    $formattedMessage = $safeMessage;
    if ($formattedMessage !== '' && ! empty($mentionMatches)) {
        usort($mentionMatches, function ($left, $right) {
            return mb_strlen((string) ($right['label'] ?? '')) <=> mb_strlen((string) ($left['label'] ?? ''));
        });

        foreach ($mentionMatches as $mention) {
            $label = trim((string) ($mention['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $display = trim((string) ($mention['display'] ?? $label));
            $escapedDisplay = e($display);
            $pattern = '/(^|\\s)@' . preg_quote($label, '/') . '(?=\\s|$|[[:punct:]])/iu';
            $replacement = '$1<span class="rounded bg-amber-100 px-1 text-amber-700 font-semibold chat-mention">@' . $escapedDisplay . '</span>';
            $formattedMessage = preg_replace($pattern, $replacement, $formattedMessage);
        }
    }

    $linkedMessage = $formattedMessage !== ''
        ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-teal-600 hover:text-teal-500 underline" target="_blank" rel="noopener">$1</a>', $formattedMessage)
        : '';
    $seenBy = $seenBy ?? [];
    $authorStatus = $authorStatus ?? 'offline';
    $statusDotClass = $authorStatus === 'active'
        ? 'bg-emerald-500'
        : ($authorStatus === 'idle' ? 'bg-amber-400' : 'bg-slate-400');
    $statusLabel = $authorStatus === 'active'
        ? 'Active'
        : ($authorStatus === 'idle' ? 'Idle' : 'Offline');
    $latestMessageId = $latestMessageId ?? 0;
    $allParticipantsReadUpTo = $allParticipantsReadUpTo ?? null;
    $showLatestMeta = $message->id === $latestMessageId;
@endphp
<div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
    <div class="max-w-2xl rounded-2xl border border-slate-200 bg-white/90 p-3 text-sm text-slate-700">
        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="flex items-center gap-2 font-semibold text-slate-700">
                <span class="h-2 w-2 rounded-full {{ $statusDotClass }}" title="{{ $statusLabel }}" data-presence-dot data-presence-key="{{ $message->author_type }}:{{ $message->author_id }}"></span>
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
