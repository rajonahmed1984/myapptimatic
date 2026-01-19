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
        
        if (!$project) {
            return false;
        }

        if ($actor instanceof User) {
            if ($actor->isAdmin()) {
                return true;
            }

            // Check both regular clients and project-specific clients
            if ($actor->isClient() || $actor->isClientProject()) {
                // Check if user belongs to the project's customer
                if ($actor->customer_id !== $project->customer_id) {
                    return false;
                }
                
                // Project-specific users can view all tasks in their assigned project
                if ($actor->isClientProject() && $actor->project_id === $project->id) {
                    return true;
                }
                
                // Regular clients can only view tasks marked as customer_visible
                return $task->customer_visible;
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
        if ($actor instanceof User && $actor->isAdmin()) {
            return $actor->isMasterAdmin() && $this->view($actor, $task);
        }

        return $this->view($actor, $task);
    }

    public function delete($actor, ProjectTask $task): bool
    {
        if (! ($actor instanceof User)) {
            return false;
        }

        if ($actor->isSales()) {
            return false;
        }

        if (in_array($task->status, ['completed', 'done'], true)) {
            return false;
        }

        return $actor->isMasterAdmin() && $this->view($actor, $task);
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
