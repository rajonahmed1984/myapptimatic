<?php

namespace App\Services;

use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\ProjectTaskSubtaskComment;
use App\Notifications\SubtaskCommentSummaryNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SubtaskCommentNotificationService
{
    public function notify(ProjectTask $task, ProjectTaskSubtask $subtask, ProjectTaskSubtaskComment $latestComment): void
    {
        $task->loadMissing(['project.customer.users', 'project.projectClients']);
        $subtask->loadMissing(['comments.userActor', 'comments.employeeActor', 'comments.salesRepActor']);

        $recipients = $this->recipients($task);
        if ($recipients->isEmpty()) {
            return;
        }

        $summaryLines = $subtask->comments
            ->sortByDesc('id')
            ->take(5)
            ->reverse()
            ->map(fn (ProjectTaskSubtaskComment $comment) => $comment->actorName() . ': ' . Str::limit((string) $comment->message, 140))
            ->values()
            ->all();

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            Notification::route('mail', $email)->notify(new SubtaskCommentSummaryNotification(
                $task,
                $subtask,
                $latestComment->actorName(),
                $summaryLines,
                $recipient['view_url'] ?? null,
                $recipient['portal_login_url'] ?? null,
                $recipient['portal_login_label'] ?? null,
            ));
        }
    }

    private function recipients(ProjectTask $task): Collection
    {
        $project = $task->project;
        if (! $project) {
            return collect();
        }

        $items = collect();

        $masterAdminEmail = $this->configuredMasterAdminNotificationEmail();
        if ($masterAdminEmail !== null) {
            $items->push([
                'email' => $masterAdminEmail,
                'view_url' => $this->taskUrl('admin', $project->id, $task->id),
                'portal_login_url' => url('/admin/login'),
                'portal_login_label' => 'log in to the admin area',
            ]);
        }

        if (! $task->customer_visible) {
            return $this->dedupe($items);
        }

        $customerEmail = trim((string) ($project->customer?->email ?? ''));
        if ($customerEmail !== '') {
            $items->push([
                'email' => $customerEmail,
                'view_url' => $this->taskUrl('client', $project->id, $task->id),
                'portal_login_url' => url('/login'),
                'portal_login_label' => 'log in to the client area',
            ]);
        }

        foreach ($project->customer?->users ?? [] as $user) {
            if (! in_array((string) $user->role, [Role::CLIENT, Role::CLIENT_PROJECT], true)) {
                continue;
            }

            $items->push([
                'email' => (string) $user->email,
                'view_url' => $this->taskUrl('client', $project->id, $task->id),
                'portal_login_url' => url('/login'),
                'portal_login_label' => 'log in to the client area',
            ]);
        }

        foreach ($project->projectClients ?? [] as $user) {
            $items->push([
                'email' => (string) $user->email,
                'view_url' => $this->taskUrl('client', $project->id, $task->id),
                'portal_login_url' => url('/login'),
                'portal_login_label' => 'log in to the client area',
            ]);
        }

        return $this->dedupe($items);
    }

    private function configuredMasterAdminNotificationEmail(): ?string
    {
        $email = strtolower(trim((string) config('system_mail.master_admin_notification_email', '')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function dedupe(Collection $items): Collection
    {
        return $items
            ->filter(fn (array $row) => ! empty($row['email']))
            ->keyBy(fn (array $row) => strtolower((string) $row['email']))
            ->values();
    }

    private function taskUrl(string $prefix, int $projectId, int $taskId): ?string
    {
        $routeName = $prefix . '.projects.tasks.show';
        if (! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName, [$projectId, $taskId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
