<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $legacyEmail = 'admin'.'@'.'apptimatic'.'.local';

        $legacyUserIds = DB::table('users')
            ->whereRaw('LOWER(email) = ?', [$legacyEmail])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($legacyUserIds === []) {
            return;
        }

        DB::transaction(function () use ($legacyUserIds): void {
            if (Schema::hasTable('mail_account_assignments')) {
                DB::table('mail_account_assignments')
                    ->where('assignee_type', 'user')
                    ->whereIn('assignee_id', $legacyUserIds)
                    ->delete();
            }

            if (Schema::hasTable('mail_account_sessions')) {
                DB::table('mail_account_sessions')
                    ->where('assignee_type', 'user')
                    ->whereIn('assignee_id', $legacyUserIds)
                    ->delete();
            }

            if (Schema::hasTable('project_task_assignments')) {
                DB::table('project_task_assignments')
                    ->where('assignee_type', 'user')
                    ->whereIn('assignee_id', $legacyUserIds)
                    ->delete();
            }

            if (Schema::hasTable('user_sessions')) {
                DB::table('user_sessions')
                    ->whereIn('user_type', ['admin', 'user'])
                    ->whereIn('user_id', $legacyUserIds)
                    ->delete();
            }

            if (Schema::hasTable('user_activity_dailies')) {
                DB::table('user_activity_dailies')
                    ->whereIn('user_type', ['admin', 'user'])
                    ->whereIn('user_id', $legacyUserIds)
                    ->delete();
            }

            DB::table('users')
                ->whereIn('id', $legacyUserIds)
                ->delete();
        });
    }

    public function down(): void
    {
        // Irreversible cleanup migration.
    }
};
