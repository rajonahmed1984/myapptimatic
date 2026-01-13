<?php

namespace App\Policies;

use App\Models\ProjectTask;
use App\Models\User;
use App\Models\Employee;
use App\Models\SalesRepresentative;

class ProjectTaskPolicy
{
    public function view($actor, ProjectTask $task): bool
    {
        $project = $task->project;

        if ($actor instanceof User) {
            if ($actor->isAdmin()) {
                return true;
            }

            if ($actor->isClient()) {
                return $actor->customer_id === $project->customer_id && $task->customer_visible;
            }

            if ($actor->isEmployee()) {
                $employeeId = $actor->employee?->id;
                return $employeeId && (
                    $project->employees()->whereKey($employeeId)->exists() ||
                    ($task->assigned_type === 'employee' && $task->assigned_id === $employeeId)
                );
            }

            if ($actor->isSales()) {
                $repId = SalesRepresentative::where('user_id', $actor->id)->value('id');
                return $repId && (
                    $project->salesRepresentatives()->whereKey($repId)->exists() ||
                    ($task->assigned_type === 'sales_rep' && $task->assigned_id === $repId)
                );
            }
        }

        if ($actor instanceof Employee) {
            return $project->employees()->whereKey($actor->id)->exists() ||
                ($task->assigned_type === 'employee' && $task->assigned_id === $actor->id) ||
                $task->customer_visible;
        }

        return false;
    }

    public function create($actor, ProjectTask $task): bool
    {
        return $this->view($actor, $task);
    }

    public function update($actor, ProjectTask $task): bool
    {
        return $this->view($actor, $task);
    }

    public function delete($actor, ProjectTask $task): bool
    {
        if (in_array($task->status, ['completed', 'done'], true)) {
            return false;
        }

        return $this->view($actor, $task);
    }

    public function comment($actor, ProjectTask $task): bool
    {
        return $this->view($actor, $task);
    }

    public function upload($actor, ProjectTask $task): bool
    {
        return $this->comment($actor, $task);
    }
}
