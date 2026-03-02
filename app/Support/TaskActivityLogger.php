<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\SalesRepresentative;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TaskActivityLogger
{
    public static function resolveActorIdentity(Request $request): array
    {
        $employee = $request->attributes->get('employee');
        if ($employee instanceof Employee) {
            return ['type' => 'employee', 'id' => $employee->id];
        }

        $salesRep = $request->attributes->get('salesRep');
        if ($salesRep instanceof SalesRepresentative) {
            return ['type' => 'sales_rep', 'id' => $salesRep->id];
        }

        $user = $request->user();
        if ($user instanceof User) {
            if ($user->isAdmin()) {
                return ['type' => 'admin', 'id' => $user->id];
            }
            if ($user->isClient()) {
                return ['type' => 'client', 'id' => $user->id];
            }
            if ($user->isSales()) {
                $repId = SalesRepresentative::where('user_id', $user->id)->value('id');
                return ['type' => 'sales_rep', 'id' => $repId ?? $user->id];
            }
        }

        return ['type' => 'client', 'id' => $user?->id ?? 0];
    }

    public static function record(
        ProjectTask $task,
        Request $request,
        string $type,
        ?string $message = null,
        array $metadata = [],
        ?string $attachmentPath = null
    ): ProjectTaskActivity {
        $actor = self::resolveActorIdentity($request);
        $attributes = [
            'project_task_id' => $task->id,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'] ?? 0,
            'type' => $type,
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'metadata' => $metadata ?: null,
        ];

        if (! Schema::hasTable('project_task_activities')) {
            return new ProjectTaskActivity($attributes);
        }

        try {
            return ProjectTaskActivity::create($attributes);
        } catch (\Throwable) {
            return new ProjectTaskActivity($attributes);
        }
    }
}
