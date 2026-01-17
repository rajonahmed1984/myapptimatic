@php
    $readReceipts = $readReceipts ?? [];
    $authorStatuses = $authorStatuses ?? [];
    $latestMessageId = $latestMessageId ?? ($messages->last()?->id ?? 0);
    $allParticipantsReadUpTo = $allParticipantsReadUpTo ?? null;
@endphp
@forelse($messages as $message)
    @include('projects.partials.project-chat-message', [
        'message' => $message,
        'project' => $project,
        'attachmentRouteName' => $attachmentRouteName,
        'currentAuthorType' => $currentAuthorType,
        'currentAuthorId' => $currentAuthorId,
        'seenBy' => $readReceipts[$message->id] ?? [],
        'authorStatus' => $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
        'latestMessageId' => $latestMessageId,
        'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
    ])
@empty
    <div class="text-sm text-slate-500">No messages yet. Start the conversation.</div>
@endforelse
