@php
    $readReceipts = $readReceipts ?? [];
    $authorStatuses = $authorStatuses ?? [];
    $messageMentions = $messageMentions ?? [];
    $latestMessageId = $latestMessageId ?? ($messages->last()?->id ?? 0);
    $allParticipantsReadUpTo = $allParticipantsReadUpTo ?? null;
    $pinRouteName = $pinRouteName ?? null;
    $reactionRouteName = $reactionRouteName ?? null;
    $currentAuthorType = $currentAuthorType ?? 'user';
    $currentAuthorId = (int) ($currentAuthorId ?? 0);
@endphp
@forelse($messages as $message)
    @php
        $summary = collect((array) ($message->reactions ?? []))
            ->filter(fn ($reaction) => is_array($reaction) && is_string($reaction['emoji'] ?? null) && ($reaction['emoji'] ?? '') !== '')
            ->groupBy(fn ($reaction) => (string) ($reaction['emoji'] ?? ''))
            ->map(function ($items, $emoji) use ($currentAuthorType, $currentAuthorId) {
                return [
                    'emoji' => (string) $emoji,
                    'count' => $items->count(),
                    'reacted' => $items->contains(function ($reaction) use ($currentAuthorType, $currentAuthorId) {
                        return (string) ($reaction['author_type'] ?? '') === (string) $currentAuthorType
                            && (int) ($reaction['author_id'] ?? 0) === (int) $currentAuthorId;
                    }),
                ];
            })
            ->values()
            ->all();
    @endphp
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
        'pinRouteName' => $pinRouteName,
        'reactionRouteName' => $reactionRouteName,
        'reactionSummary' => $summary,
        'editableWindowSeconds' => $editableWindowSeconds ?? 30,
    ])
@empty
    <div class="rounded-xl bg-white/85 px-4 py-3 text-sm text-slate-500 shadow-sm">No messages yet. Start the conversation.</div>
@endforelse
