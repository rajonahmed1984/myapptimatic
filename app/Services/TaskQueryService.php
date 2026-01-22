<?php

namespace App\Services;

use App\Models\ProjectTask;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Support\TaskCompletionManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskQueryService
{
    public function canViewTasks(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isClient()
            || $user->isClientProject()
            || $user->isEmployee()
            || $user->isSales();
    }

    public function visibleTasksForUser(User $user): Builder
    {
        $query = ProjectTask::query()->whereHas('project');

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isClientProject()) {
            if (! $user->project_id) {
                return $query->whereRaw('0 = 1');
            }

            return $query
                ->where('project_id', $user->project_id)
                ->whereHas('project', fn (Builder $q) => $q->where('customer_id', $user->customer_id));
        }

        if ($user->isClient()) {
            return $query
                ->where('customer_visible', true)
                ->whereHas('project', fn (Builder $q) => $q->where('customer_id', $user->customer_id));
        }

        if ($user->isEmployee()) {
            $employeeId = $user->employee?->id;
            if (! $employeeId) {
                return $query->whereRaw('0 = 1');
            }

            return $query->where(function (Builder $q) use ($employeeId) {
                $q->whereHas('project.employees', fn (Builder $projectQuery) => $projectQuery->whereKey($employeeId))
                    ->orWhere(function (Builder $taskQuery) use ($employeeId) {
                        $taskQuery->where('assigned_type', 'employee')
                            ->where('assigned_id', $employeeId);
                    });
            });
        }

        if ($user->isSales()) {
            $repId = SalesRepresentative::where('user_id', $user->id)->value('id');
            if (! $repId) {
                return $query->whereRaw('0 = 1');
            }

            return $query->where(function (Builder $q) use ($repId) {
                $q->whereHas('project.salesRepresentatives', fn (Builder $projectQuery) => $projectQuery->whereKey($repId))
                    ->orWhere(function (Builder $taskQuery) use ($repId) {
                        $taskQuery->where('assigned_type', 'sales_rep')
                            ->where('assigned_id', $repId);
                    });
            });
        }

        return $query->whereRaw('0 = 1');
    }

    public function actionableTasksForUser(User $user, Collection $tasks): Collection
    {
        $gate = Gate::forUser($user);

        return $tasks->map(function (ProjectTask $task) use ($user, $gate) {
            $canUpdate = $gate->allows('update', $task);
            if ($canUpdate && ! $user->isMasterAdmin() && $task->creatorEditWindowExpired($user->id)) {
                $canUpdate = false;
            }

            $status = $task->status ?? 'pending';
            $subtasksCount = $task->subtasks_count;
            $hasSubtasks = $subtasksCount !== null
                ? (int) $subtasksCount > 0
                : TaskCompletionManager::hasSubtasks($task);

            $task->setAttribute('can_edit', $canUpdate);
            $task->setAttribute('can_delete', $gate->allows('delete', $task));
            $task->setAttribute('can_start', $canUpdate && in_array($status, ['pending', 'todo'], true));
            $task->setAttribute('can_complete', $canUpdate && ! $hasSubtasks && ! in_array($status, ['completed', 'done'], true));
            $task->setAttribute('has_subtasks', $hasSubtasks);

            return $task;
        });
    }

    public function tasksSummaryForUser(User $user): array
    {
        $statusCounts = $this->visibleTasksForUser($user)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $openCount = (int) (($statusCounts['pending'] ?? 0)
            + ($statusCounts['todo'] ?? 0)
            + ($statusCounts['blocked'] ?? 0));

        $inProgressCount = (int) ($statusCounts['in_progress'] ?? 0);
        $completedCount = (int) (($statusCounts['completed'] ?? 0) + ($statusCounts['done'] ?? 0));

        return [
            'total' => (int) $statusCounts->sum(),
            'open' => $openCount,
            'in_progress' => $inProgressCount,
            'completed' => $completedCount,
        ];
    }

    public function dashboardTasksForUser(User $user, int $openLimit = 5, int $inProgressLimit = 5): array
    {
        $openTasks = $this->visibleTasksForUser($user)
            ->whereIn('status', ['pending', 'todo', 'blocked'])
            ->with('project')
            ->withCount('subtasks')
            ->orderByDesc('created_at')
            ->limit($openLimit)
            ->get();

        $inProgressTasks = $this->visibleTasksForUser($user)
            ->where('status', 'in_progress')
            ->with('project')
            ->withCount('subtasks')
            ->orderByDesc('created_at')
            ->limit($inProgressLimit)
            ->get();

        return [
            'summary' => $this->tasksSummaryForUser($user),
            'openTasks' => $this->actionableTasksForUser($user, $openTasks),
            'inProgressTasks' => $this->actionableTasksForUser($user, $inProgressTasks),
        ];
    }
}
