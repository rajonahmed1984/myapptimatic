<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\User;

class ProjectTaskSubtaskPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny($actor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view($actor, ProjectTaskSubtask $projectTaskSubtask): bool
    {
        $task = $projectTaskSubtask->task;
        if (! $task) {
            return false;
        }

        return $this->canViewTask($actor, $task);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create($actor, ProjectTask $task): bool
    {
        return $this->canViewTask($actor, $task);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update($actor, ProjectTaskSubtask $projectTaskSubtask): bool
    {
        $task = $projectTaskSubtask->task;
        if (! $task) {
            return false;
        }

        if (! $this->canViewTask($actor, $task)) {
            return false;
        }

        if ($this->isMasterAdmin($actor)) {
            return true;
        }

        $actorId = $this->actorUserId($actor);
        return $actorId !== null
            && $projectTaskSubtask->created_by !== null
            && $projectTaskSubtask->created_by === $actorId;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete($actor, ProjectTaskSubtask $projectTaskSubtask): bool
    {
        $task = $projectTaskSubtask->task;
        if (! $task) {
            return false;
        }

        if (! $this->canViewTask($actor, $task)) {
            return false;
        }

        if ($this->isMasterAdmin($actor)) {
            return true;
        }

        if ($actor instanceof User && $actor->isSales()) {
            return false;
        }

        if ($actor instanceof Employee) {
            return true;
        }

        if ($actor instanceof User) {
            return $actor->isAdmin() || $actor->isEmployee();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore($actor, ProjectTaskSubtask $projectTaskSubtask): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete($actor, ProjectTaskSubtask $projectTaskSubtask): bool
    {
        return false;
    }

    private function canViewTask($actor, ProjectTask $task): bool
    {
        return app(ProjectTaskPolicy::class)->view($actor, $task);
    }

    private function canEditTask($actor, ProjectTask $task): bool
    {
        if (! $this->canViewTask($actor, $task)) {
            return false;
        }

        if ($actor instanceof Employee) {
            return true;
        }

        if ($actor instanceof User) {
            return $actor->isAdmin() || $actor->isSales() || $actor->isEmployee();
        }

        return false;
    }

    private function actorUserId($actor): ?int
    {
        if ($actor instanceof User) {
            return $actor->id;
        }

        if ($actor instanceof Employee) {
            return $actor->user_id;
        }

        return null;
    }

    private function isMasterAdmin($actor): bool
    {
        return $actor instanceof User && $actor->isMasterAdmin();
    }
}
