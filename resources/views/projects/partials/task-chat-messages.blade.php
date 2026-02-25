@forelse($messages as $message)
    @include('projects.partials.task-chat-message', [
        'message' => $message,
        'project' => $project,
        'task' => $task,
        'attachmentRouteName' => $attachmentRouteName,
        'currentAuthorType' => $currentAuthorType,
        'currentAuthorId' => $currentAuthorId,
        'updateRouteName' => $updateRouteName ?? null,
        'deleteRouteName' => $deleteRouteName ?? null,
          'pinRouteName' => $pinRouteName ?? null,
          'reactionRouteName' => $reactionRouteName ?? null,
          'reactionSummary' => $message['reaction_summary'] ?? [],
        'editableWindowSeconds' => $editableWindowSeconds ?? 30,
    ])
@empty
    <div class="rounded-xl bg-white/85 px-4 py-3 text-sm text-slate-500 shadow-sm">No messages yet. Start the conversation.</div>
@endforelse
