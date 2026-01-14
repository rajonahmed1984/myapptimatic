@forelse($activities as $activity)
    @include('projects.partials.task-activity-item', [
        'activity' => $activity,
        'project' => $project,
        'task' => $task,
        'attachmentRouteName' => $attachmentRouteName,
        'currentActorType' => $currentActorType,
        'currentActorId' => $currentActorId,
    ])
@empty
    <div class="text-sm text-slate-500">No activity yet. Start the conversation.</div>
@endforelse
