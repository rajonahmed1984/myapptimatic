<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Employee;
use App\Models\SalesRepresentative;

class ProjectPolicy
{
    public function view($actor, Project $project): bool
    {
        if ($actor instanceof User) {
            if ($actor->isAdmin()) {
                return true;
            }

            if ($actor->isClient() && $actor->customer_id === $project->customer_id) {
                return true;
            }

            if ($actor->isClientProject() && $actor->project_id === $project->id) {
                return true;
            }

            if ($actor->isEmployee()) {
                $employeeId = $actor->employee?->id;
                return $employeeId && $project->employees()->whereKey($employeeId)->exists();
            }

            if ($actor->isSales()) {
                $repId = SalesRepresentative::where('user_id', $actor->id)->value('id');
                return $repId && $project->salesRepresentatives()->whereKey($repId)->exists();
            }
        }

        if ($actor instanceof Employee) {
            return $project->employees()->whereKey($actor->id)->exists();
        }

        return false;
    }

    public function createTask($actor, Project $project): bool
    {
        return $this->view($actor, $project);
    }
}
