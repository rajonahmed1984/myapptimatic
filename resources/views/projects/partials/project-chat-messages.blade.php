@php
    $readReceipts = $readReceipts ?? [];
    $authorStatuses = $authorStatuses ?? [];
    $messageMentions = $messageMentions ?? [];
    $latestMessageId = $latestMessageId ?? ($messages->last()?->id ?? 0);
    $allParticipantsReadUpTo = $allParticipantsReadUpTo ?? null;
@endphp
@forelse($messages as $message)
    @include('projects.partials.project-chat-message', [
        'message' => $message,
        'project' => $project,
        'attachmentRouteName' => $attachmentRouteName,
        'taskShowRouteName' => $taskShowRouteName ?? null,
        'currentAuthorType' => $currentAuthorType,
        'currentAuthorId' => $currentAuthorId,
        'seenBy' => $readReceipts[$message->id] ?? [],
        'authorStatus' => $authorStatuses[$message->author_type . ':' . $message->author_id] ?? 'offline',
        'mentionMatches' => $messageMentions[$message->id] ?? [],
        'latestMessageId' => $latestMessageId,
        'allParticipantsReadUpTo' => $allParticipantsReadUpTo,
        'updateRouteName' => $updateRouteName ?? null,
        'deleteRouteName' => $deleteRouteName ?? null,
        'editableWindowSeconds' => $editableWindowSeconds ?? 30,
    ])
@empty
    <div class="rounded-xl bg-white/85 px-4 py-3 text-sm text-slate-500 shadow-sm">No messages yet. Start the conversation.</div>
@endforelse
