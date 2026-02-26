<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Support\UrlResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class TaskNotificationRecipients
{
    public static function forTask(ProjectTask $task): array
    {
        return (new self())->resolveForTask($task);
    }

    public static function forSubtask(ProjectTaskSubtask $subtask, ProjectTask $task): array
    {
        return (new self())->resolveForTask($task, $subtask);
    }

    private function resolveForTask(ProjectTask $task, ?ProjectTaskSubtask $subtask = null): array
    {
        $task->loadMissing([
            'project.customer.users',
            'project.projectClients',
            'project.employees.user',
            'project.salesRepresentatives.user',
            'assignments.employee.user',
            'assignments.salesRep.user',
            'createdBy',
        ]);

        $project = $task->project;
        if (! $project) {
            return [];
        }

        $recipients = [];

        $masterAdmins = User::query()
            ->where('role', Role::MASTER_ADMIN)
            ->whereNotNull('email')
            ->get(['id', 'name', 'email', 'role']);

        foreach ($masterAdmins as $admin) {
            $this->addRecipient($recipients, $admin, $admin->email, $task, $subtask);
        }

        $creator = $this->resolveCreator($task->created_by);
        if ($creator) {
            $this->addRecipient($recipients, $creator, $this->actorEmail($creator), $task, $subtask);
        }

        if ($task->customer_visible) {
            $customerUsers = $project->customer?->users
                ? $project->customer->users->where('role', Role::CLIENT)
                : collect();
            foreach ($customerUsers as $user) {
                $this->addRecipient($recipients, $user, $user->email, $task, $subtask);
            }

            foreach ($project->projectClients ?? [] as $projectClient) {
                $this->addRecipient($recipients, $projectClient, $projectClient->email, $task, $subtask);
            }
        }

        foreach ($project->employees ?? [] as $employee) {
            $this->addRecipient($recipients, $employee, $employee->email, $task, $subtask);
        }

        foreach ($this->assignedEmployees($task) as $employee) {
            $this->addRecipient($recipients, $employee, $employee->email, $task, $subtask);
        }

        foreach ($project->salesRepresentatives ?? [] as $salesRep) {
            $user = $salesRep->user;
            if ($user) {
                $this->addRecipient($recipients, $user, $user->email, $task, $subtask);
            }
        }

        foreach ($this->assignedSalesReps($task) as $salesRep) {
            $user = $salesRep->user;
            if ($user) {
                $this->addRecipient($recipients, $user, $user->email, $task, $subtask);
            }
        }

        if ($task->assignee_id) {
            $assigneeUser = User::find($task->assignee_id);
            if ($assigneeUser) {
                $this->addRecipient($recipients, $assigneeUser, $assigneeUser->email, $task, $subtask);
            }
        }

        if ($subtask) {
            $subtaskCreator = $this->resolveCreator($subtask->created_by);
            if ($subtaskCreator) {
                $this->addRecipient($recipients, $subtaskCreator, $this->actorEmail($subtaskCreator), $task, $subtask);
            }
        }

        return array_values($recipients);
    }

    private function assignedEmployees(ProjectTask $task)
    {
        $employeeIds = $task->assignments
            ->where('assignee_type', 'employee')
            ->pluck('assignee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($task->assigned_type === 'employee' && $task->assigned_id) {
            $employeeIds[] = (int) $task->assigned_id;
        }

        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        if (empty($employeeIds)) {
            return collect();
        }

        return Employee::whereIn('id', $employeeIds)->get(['id', 'name', 'email', 'user_id']);
    }

    private function assignedSalesReps(ProjectTask $task)
    {
        $repIds = $task->assignments
            ->where('assignee_type', 'sales_rep')
            ->pluck('assignee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (in_array($task->assigned_type, ['sales_rep', 'salesrep'], true) && $task->assigned_id) {
            $repIds[] = (int) $task->assigned_id;
        }

        $repIds = array_values(array_unique(array_filter($repIds)));
        if (empty($repIds)) {
            return collect();
        }

        return SalesRepresentative::whereIn('id', $repIds)->with('user')->get();
    }

    private function resolveCreator(?int $creatorId)
    {
        if (! $creatorId) {
            return null;
        }

        $user = User::find($creatorId);
        if ($user) {
            return $user;
        }

        return Employee::find($creatorId);
    }

    private function addRecipient(
        array &$recipients,
        object $actor,
        ?string $email,
        ProjectTask $task,
        ?ProjectTaskSubtask $subtask = null
    ): void {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Skipping task notification recipient with invalid email.', [
                'email' => $email,
                'task_id' => $task->id,
                'subtask_id' => $subtask?->id,
            ]);

            return;
        }

        $suppressed = collect((array) config('system_mail.suppressed_recipients', []))
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->all();
        if (in_array($email, $suppressed, true)) {
            return;
        }

        if (! $this->canView($actor, $task, $subtask)) {
            return;
        }

        $priority = $this->priorityForActor($actor);
        $links = $this->portalLinksForActor($actor, $task->project, $task);

        if (! isset($recipients[$email]) || $priority < $recipients[$email]['priority']) {
            $recipients[$email] = array_merge([
                'email' => $email,
                'name' => $this->actorName($actor),
                'priority' => $priority,
            ], $links);
        }
    }

    private function canView(object $actor, ProjectTask $task, ?ProjectTaskSubtask $subtask = null): bool
    {
        try {
            $gate = Gate::forUser($actor);
            return $subtask
                ? $gate->allows('view', $subtask)
                : $gate->allows('view', $task);
        } catch (\Throwable) {
            return false;
        }
    }

    private function actorName(object $actor): string
    {
        return $actor->name ?? 'User';
    }

    private function actorEmail(object $actor): ?string
    {
        return $actor->email ?? null;
    }

    private function priorityForActor(object $actor): int
    {
        if ($actor instanceof User && $actor->isMasterAdmin()) {
            return 1;
        }

        if ($actor instanceof User && $actor->isAdmin()) {
            return 2;
        }

        if ($actor instanceof Employee) {
            return 3;
        }

        if ($actor instanceof User && $actor->isSales()) {
            return 4;
        }

        if ($actor instanceof User && ($actor->isClient() || $actor->isClientProject())) {
            return 5;
        }

        return 9;
    }

    private function portalLinksForActor(object $actor, Project $project, ProjectTask $task): array
    {
        $portalUrl = UrlResolver::portalUrl();
        $loginPath = null;
        $loginLabel = null;
        $taskRoute = null;
        $projectRoute = null;

        if ($actor instanceof Employee) {
            $loginPath = '/employee/login';
            $loginLabel = 'log in to the employee area';
            $taskRoute = 'employee.projects.tasks.show';
            $projectRoute = 'employee.projects.show';
        } elseif ($actor instanceof User) {
            if ($actor->isAdmin()) {
                $loginPath = '/admin';
                $loginLabel = 'log in to the admin area';
                $taskRoute = 'admin.projects.tasks.show';
                $projectRoute = 'admin.projects.show';
            } elseif ($actor->isSales()) {
                $loginPath = '/sales/login';
                $loginLabel = 'log in to the sales area';
                $taskRoute = 'rep.projects.tasks.show';
                $projectRoute = 'rep.projects.show';
            } elseif ($actor->isClient() || $actor->isClientProject()) {
                $loginPath = '/login';
                $loginLabel = 'log in to the client area';
                $taskRoute = 'client.projects.tasks.show';
                $projectRoute = 'client.projects.show';
            }
        }

        $viewUrl = $portalUrl;
        if ($taskRoute && Route::has($taskRoute)) {
            $viewUrl = route($taskRoute, [$project, $task]);
        }

        $projectUrl = $portalUrl;
        if ($projectRoute && Route::has($projectRoute)) {
            $projectUrl = route($projectRoute, $project);
        }

        return [
            'view_url' => $viewUrl,
            'project_url' => $projectUrl,
            'portal_login_url' => $loginPath ? $portalUrl . $loginPath : null,
            'portal_login_label' => $loginLabel,
        ];
    }
}
