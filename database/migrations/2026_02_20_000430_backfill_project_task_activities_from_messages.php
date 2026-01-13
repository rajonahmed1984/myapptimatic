<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_task_messages') || ! Schema::hasTable('project_task_activities')) {
            return;
        }

        $messages = DB::table('project_task_messages')->get();

        foreach ($messages as $message) {
            $metadata = ['legacy_message_id' => $message->id];
            $type = $message->attachment_path && ! $message->message ? 'upload' : 'comment';

            $exists = DB::table('project_task_activities')
                ->where('project_task_id', $message->project_task_id)
                ->where('type', $type)
                ->where('metadata->legacy_message_id', $message->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $actorType = match ($message->author_type) {
                'salesrep' => 'salesrep',
                'employee' => 'employee',
                default => null,
            };

            if (! $actorType && $message->author_id) {
                $role = DB::table('users')->where('id', $message->author_id)->value('role');
                $actorType = in_array($role, ['admin', 'master_admin', 'sub_admin'], true) ? 'admin' : 'client';
            }

            DB::table('project_task_activities')->insert([
                'project_task_id' => $message->project_task_id,
                'actor_type' => $actorType ?? 'client',
                'actor_id' => $message->author_id ?? 0,
                'type' => $type,
                'message' => $message->message,
                'attachment_path' => $message->attachment_path,
                'metadata' => json_encode($metadata),
                'created_at' => $message->created_at ?? now(),
                'updated_at' => $message->created_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_task_activities')) {
            return;
        }

        DB::table('project_task_activities')
            ->whereNotNull('metadata')
            ->whereRaw("JSON_EXTRACT(metadata, '$.legacy_message_id') IS NOT NULL")
            ->delete();
    }
};
