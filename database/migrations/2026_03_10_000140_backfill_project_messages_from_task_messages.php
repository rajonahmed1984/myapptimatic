<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_messages')
            || ! Schema::hasTable('project_task_messages')
            || ! Schema::hasTable('project_tasks')
        ) {
            return;
        }

        if (DB::table('project_messages')->limit(1)->exists()) {
            return;
        }

        DB::table('project_task_messages')
            ->join('project_tasks', 'project_tasks.id', '=', 'project_task_messages.project_task_id')
            ->select([
                'project_task_messages.id as source_id',
                'project_tasks.project_id as project_id',
                'project_task_messages.author_type',
                'project_task_messages.author_id',
                'project_task_messages.message',
                'project_task_messages.attachment_path',
                'project_task_messages.created_at',
                'project_task_messages.updated_at',
            ])
            ->orderBy('project_task_messages.id')
            ->chunkById(500, function ($rows) {
                $payload = [];
                foreach ($rows as $row) {
                    $payload[] = [
                        'project_id' => $row->project_id,
                        'author_type' => $row->author_type,
                        'author_id' => $row->author_id,
                        'message' => $row->message,
                        'attachment_path' => $row->attachment_path,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? $row->created_at ?? now(),
                    ];
                }

                if ($payload) {
                    DB::table('project_messages')->insert($payload);
                }
            }, 'project_task_messages.id', 'source_id');
    }

    public function down(): void
    {
        // No-op: data backfill cannot be safely reversed.
    }
};
