@forelse($messages as $message)
    @include('projects.partials.task-chat-message', [
        'message' => $message,
        'project' => $project,
        'task' => $task,
        'attachmentRouteName' => $attachmentRouteName,
        'currentAuthorType' => $currentAuthorType,
        'currentAuthorId' => $currentAuthorId,
    ])
@empty
    <div class="text-sm text-slate-500">No messages yet. Start the conversation.</div>
@endforelse
