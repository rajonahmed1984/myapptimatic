@php
    $isOwn = $message->author_type === $currentAuthorType
        && (string) $message->author_id === (string) $currentAuthorId;
    $safeMessage = $message->message !== null ? e($message->message) : '';
    $linkedMessage = $safeMessage !== ''
        ? preg_replace('~(https?://[^\\s<]+)~', '<a href="$1" class="text-sky-700 hover:text-sky-600 underline" target="_blank" rel="noopener">$1</a>', $safeMessage)
        : '';
    $timestamp = $message->created_at?->format('M d, Y H:i') ?? '';
    $fullTimestamp = $timestamp;
    $editableWindowSeconds = (int) ($editableWindowSeconds ?? 30);
    $editableUntil = $message->created_at?->copy()->addSeconds($editableWindowSeconds);
    $canMutate = $isOwn && $editableUntil && now()->lessThan($editableUntil);
    $updateUrl = isset($updateRouteName) ? route($updateRouteName, [$project, $task, $message], false) : null;
    $deleteUrl = isset($deleteRouteName) ? route($deleteRouteName, [$project, $task, $message], false) : null;
    $hasAttachment = ! empty($message->attachment_path);
    $isEdited = $message->updated_at && $message->created_at && $message->updated_at->gt($message->created_at);
@endphp
@php $attachmentUrl = route($attachmentRouteName, [$project, $task, $message], false); @endphp
@php $inlineAttachmentUrl = \Illuminate\Support\Facades\URL::signedRoute('chat.task-messages.inline', ['message' => $message->id], false); @endphp
<div class="wa-message-row {{ $isOwn ? 'justify-end' : 'justify-start' }}"
     data-message-id="{{ $message->id }}"
     data-editable-until="{{ $editableUntil?->toIso8601String() ?? '' }}"
     data-edit-url="{{ $canMutate ? ($updateUrl ?? '') : '' }}"
     data-delete-url="{{ $canMutate ? ($deleteUrl ?? '') : '' }}"
     data-has-attachment="{{ $hasAttachment ? '1' : '0' }}">
    <div class="wa-bubble {{ $isOwn ? 'wa-bubble-own' : 'wa-bubble-other' }}">
        @if(! $isOwn)
            <div class="mb-1 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                <span class="font-semibold text-slate-700">{{ $message->authorName() }}</span>
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
    </div>
</div>
