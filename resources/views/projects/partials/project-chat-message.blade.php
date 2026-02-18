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
            $type = (string) ($mention['type'] ?? '');
            $mentionId = (int) ($mention['id'] ?? 0);
            $label = trim((string) ($mention['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $display = trim((string) ($mention['display'] ?? $label));
            $escapedDisplay = e($display);
            $pattern = '/(^|\\s)@' . preg_quote($label, '/') . '(?=\\s|$|[[:punct:]])/iu';
            $mentionClass = 'rounded bg-amber-100 px-1 text-amber-700 font-semibold chat-mention';
            if ($type === 'project_task' && $mentionId > 0 && ! empty($taskShowRouteName ?? null)) {
                $taskUrl = route($taskShowRouteName, [$project, $mentionId], false);
                $replacement = '$1<a href="' . e($taskUrl) . '" class="' . $mentionClass . ' hover:underline">@' . $escapedDisplay . '</a>';
            } else {
                $replacement = '$1<span class="' . $mentionClass . '">@' . $escapedDisplay . '</span>';
            }
            $formattedMessage = preg_replace($pattern, $replacement, $formattedMessage);
        }
    }

    $linkedMessage = $formattedMessage !== ''
        ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-sky-700 hover:text-sky-600 underline" target="_blank" rel="noopener">$1</a>', $formattedMessage)
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
    $timestamp = $message->created_at?->format('M d, Y H:i') ?? '';
    $fullTimestamp = $timestamp;
    $editableWindowSeconds = (int) ($editableWindowSeconds ?? 30);
    $editableUntil = $message->created_at?->copy()->addSeconds($editableWindowSeconds);
    $canMutate = $isOwn && $editableUntil && now()->lessThan($editableUntil);
    $updateUrl = isset($updateRouteName) ? route($updateRouteName, [$project, $message], false) : null;
    $deleteUrl = isset($deleteRouteName) ? route($deleteRouteName, [$project, $message], false) : null;
    $hasAttachment = ! empty($message->attachment_path);
    $isEdited = $message->updated_at && $message->created_at && $message->updated_at->gt($message->created_at);
@endphp
@php $attachmentUrl = route($attachmentRouteName, [$project, $message], false); @endphp
@php $inlineAttachmentUrl = \Illuminate\Support\Facades\URL::signedRoute('chat.project-messages.inline', ['message' => $message->id], false); @endphp
<div class="wa-message-row {{ $isOwn ? 'justify-end' : 'justify-start' }}"
     data-message-id="{{ $message->id }}"
     data-editable-until="{{ $editableUntil?->toIso8601String() ?? '' }}"
     data-edit-url="{{ $canMutate ? ($updateUrl ?? '') : '' }}"
     data-delete-url="{{ $canMutate ? ($deleteUrl ?? '') : '' }}"
     data-has-attachment="{{ $hasAttachment ? '1' : '0' }}">
    <div class="wa-bubble {{ $isOwn ? 'wa-bubble-own' : 'wa-bubble-other' }}">
        @if(! $isOwn)
            <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span class="flex items-center gap-2 font-semibold text-slate-700">
                    <span class="h-2 w-2 rounded-full {{ $statusDotClass }}" title="{{ $statusLabel }}" data-presence-dot data-presence-key="{{ $message->author_type }}:{{ $message->author_id }}"></span>
                    {{ $message->authorName() }}
                </span>
                <span>{{ $message->authorTypeLabel() }}</span>
            </div>
        @endif

        @if($linkedMessage !== '')
            <div class="text-sm whitespace-pre-wrap text-slate-800" data-chat-message-text>{!! $linkedMessage !!}</div>
        @endif

        @if($message->attachment_path)
            <div class="mt-2">
                @if($message->isImageAttachment())
                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener">
                        <img src="{{ $inlineAttachmentUrl }}" alt="Attachment" class="max-h-64 rounded-xl border border-slate-200">
                    </a>
                @else
                    <a href="{{ $attachmentUrl }}" class="wa-file-link">
                        Download {{ $message->attachmentName() ?? 'attachment' }}
                    </a>
                @endif
            </div>
        @endif

        @if($canMutate && $updateUrl && $deleteUrl)
            <div class="mt-1 flex justify-end gap-3 text-[11px] font-semibold text-slate-500" data-chat-actions>
                <button type="button" class="hover:text-teal-700" data-chat-edit>Edit</button>
                <button type="button" class="hover:text-rose-700" data-chat-delete>Delete</button>
            </div>
        @endif

        <div class="wa-meta-line" title="{{ $fullTimestamp }}">
            {{ $timestamp }}@if($isEdited) <span class="ml-1">edited</span>@endif
        </div>

        @if($showLatestMeta && !empty($seenBy))
            <div class="mt-1 text-[11px] text-slate-500 chat-seen-by">
                Seen by {{ implode(', ', $seenBy) }}
            </div>
        @endif
        @if($showLatestMeta && !empty($allParticipantsReadUpTo))
            <div class="mt-1 text-[11px] text-slate-500 chat-read-up-to">
                All participants read up to {{ $allParticipantsReadUpTo['label'] ?? '' }}
            </div>
        @endif
    </div>
</div>
