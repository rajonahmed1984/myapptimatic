<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectChatEmailDigestState;
use App\Models\ProjectMessage;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Notifications\ProjectChatSummaryNotification;
use App\Support\UrlResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class SendProjectChatSummaryNotifications extends Command
{
    protected $signature = 'projects:chat-summary-notify {--limit= : Max projects to process in one run}';

    protected $description = 'Send project chat summary emails only when new chat messages exist.';

    public function handle(): int
    {
        if (! (bool) config('project-chat-notifications.enabled', true)) {
            $this->line('Project chat email notifications are disabled.');
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('project-chat-notifications.project_limit_per_run', 100));
        $limit = max(1, $limit);
        $summaryLinesLimit = max(1, (int) config('project-chat-notifications.summary_lines', 5));

        $projectRows = ProjectMessage::query()
            ->selectRaw('project_id, MAX(id) as latest_message_id')
            ->groupBy('project_id')
            ->orderByDesc(DB::raw('MAX(id)'))
            ->limit($limit)
            ->get();

        if ($projectRows->isEmpty()) {
            $this->line('No project chat messages found.');
            return self::SUCCESS;
        }

        $projects = Project::query()
            ->whereIn('id', $projectRows->pluck('project_id'))
            ->with([
                'customer.users',
                'projectClients',
                'employees',
                'salesRepresentatives.user',
            ])
            ->get()
            ->keyBy('id');

        $sentCount = 0;

        foreach ($projectRows as $row) {
            $project = $projects->get((int) $row->project_id);
            if (! $project) {
                continue;
            }

            $latestMessageId = (int) ($row->latest_message_id ?? 0);
            if ($latestMessageId <= 0) {
                continue;
            }

            $recipients = $this->recipientsForProject($project);
            if ($recipients->isEmpty()) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $email = strtolower(trim((string) ($recipient['email'] ?? '')));
                if ($email === '') {
                    continue;
                }

                $state = ProjectChatEmailDigestState::query()
                    ->firstOrNew([
                        'project_id' => $project->id,
                        'recipient_email' => $email,
                    ]);

                $lastNotifiedId = (int) ($state->last_notified_message_id ?? 0);
                if ($latestMessageId <= $lastNotifiedId) {
                    continue;
                }

                $newMessagesQuery = ProjectMessage::query()
                    ->where('project_id', $project->id)
                    ->where('id', '>', $lastNotifiedId);

                $newCount = (int) $newMessagesQuery->count();
                if ($newCount <= 0) {
                    continue;
                }

                $summaryMessages = (clone $newMessagesQuery)
                    ->with(['userAuthor', 'employeeAuthor', 'salesRepAuthor'])
                    ->latest('id')
                    ->limit($summaryLinesLimit)
                    ->get()
                    ->reverse()
                    ->values();

                $summaryLines = $summaryMessages
                    ->map(fn (ProjectMessage $message) => $message->authorName() . ': ' . $this->snippet($message))
                    ->all();

                try {
                    Notification::route('mail', $email)->notify(new ProjectChatSummaryNotification(
                        $project,
                        $newCount,
                        $summaryLines,
                        $recipient['chat_url'] ?? null,
                        $recipient['portal_login_url'] ?? null,
                        $recipient['portal_login_label'] ?? null,
                    ));

                    $state->last_notified_message_id = $latestMessageId;
                    $state->notified_at = now();
                    $state->save();
                    $sentCount++;
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        $this->info("Project chat summary notifications sent: {$sentCount}");

        return self::SUCCESS;
    }

    private function recipientsForProject(Project $project): Collection
    {
        $recipients = collect();

        foreach ($project->customer?->users ?? [] as $user) {
            if (! $user instanceof User) {
                continue;
            }
            if (! in_array($user->role, [Role::CLIENT, Role::CLIENT_PROJECT], true)) {
                continue;
            }
            $recipients->push($this->fromUser($project, $user));
        }

        foreach ($project->projectClients ?? [] as $user) {
            if ($user instanceof User) {
                $recipients->push($this->fromUser($project, $user));
            }
        }

        foreach ($project->employees ?? [] as $employee) {
            if ($employee instanceof Employee) {
                $recipients->push($this->fromEmployee($project, $employee));
            }
        }

        foreach ($project->salesRepresentatives ?? [] as $salesRep) {
            if ($salesRep instanceof SalesRepresentative) {
                $recipients->push($this->fromSalesRep($project, $salesRep));
            }
        }

        return $recipients
            ->filter(fn ($item) => ! empty($item['email']))
            ->keyBy(fn ($item) => strtolower((string) $item['email']))
            ->values();
    }

    private function fromUser(Project $project, User $user): array
    {
        $portalUrl = UrlResolver::portalUrl();

        if ($user->isClient() || $user->isClientProject()) {
            $prefix = 'client';
            $loginPath = '/login';
            $loginLabel = 'log in to the client area';
        } elseif ($user->isSales()) {
            $prefix = 'rep';
            $loginPath = '/sales/login';
            $loginLabel = 'log in to the sales area';
        } else {
            $prefix = 'admin';
            $loginPath = '/admin/login';
            $loginLabel = 'log in to the admin area';
        }

        return [
            'email' => (string) $user->email,
            'chat_url' => $this->chatUrl($prefix, $project),
            'portal_login_url' => rtrim($portalUrl, '/') . $loginPath,
            'portal_login_label' => $loginLabel,
        ];
    }

    private function fromEmployee(Project $project, Employee $employee): array
    {
        $portalUrl = UrlResolver::portalUrl();

        return [
            'email' => (string) $employee->email,
            'chat_url' => $this->chatUrl('employee', $project),
            'portal_login_url' => rtrim($portalUrl, '/') . '/employee/login',
            'portal_login_label' => 'log in to the employee area',
        ];
    }

    private function fromSalesRep(Project $project, SalesRepresentative $salesRep): array
    {
        $portalUrl = UrlResolver::portalUrl();

        return [
            'email' => (string) ($salesRep->user?->email ?: $salesRep->email),
            'chat_url' => $this->chatUrl('rep', $project),
            'portal_login_url' => rtrim($portalUrl, '/') . '/sales/login',
            'portal_login_label' => 'log in to the sales area',
        ];
    }

    private function chatUrl(string $prefix, Project $project): ?string
    {
        $routeName = $prefix . '.projects.chat';
        if (! Route::has($routeName)) {
            return UrlResolver::portalUrl();
        }

        try {
            return route($routeName, $project);
        } catch (\Throwable) {
            return UrlResolver::portalUrl();
        }
    }

    private function snippet(ProjectMessage $message): string
    {
        $text = trim((string) ($message->message ?? ''));
        if ($text === '') {
            return $message->attachment_path ? 'Attachment shared' : 'New message';
        }

        return Str::limit($text, 120);
    }
}
