<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Employee;
use App\Models\SalesRepresentative;
use App\Enums\Role;

class ProjectPolicy
{
    public function view($actor, Project $project): bool
    {
        if ($actor instanceof User) {
            // Sales is scoped below (per-rep assignment); admin roles and Support keep blanket access.
            // NOTE: Support is intentionally left blanket here — there's no project<->ticket linkage
            // in the data model to scope it by by (SupportTicket only has customer_id), so narrowing
            // it would need a product decision, not a silent behavior change. See audit note.
            if (in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN, Role::SUPPORT], true)) {
                return true;
            }

            if ($actor->isClient() && $actor->customer_id === $project->customer_id) {
                return true;
            }

            if ($actor->isClientProject() && in_array((int) $project->id, $actor->assignedProjectIds(), true)) {
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

    public function update($actor, Project $project): bool
    {
        return $actor instanceof User
            && in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN], true);
    }

    public function delete($actor, Project $project): bool
    {
        return $actor instanceof User
            && in_array($actor->role, [Role::MASTER_ADMIN, Role::SUB_ADMIN], true);
    }
}
