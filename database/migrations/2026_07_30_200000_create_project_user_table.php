<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE users ENGINE = InnoDB');
            DB::statement('ALTER TABLE projects ENGINE = InnoDB');
        }

        if (! Schema::hasTable('project_user')) {
            Schema::create('project_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['project_id', 'user_id']);
            });
        }

        // Backfill existing project_id assignments for project-specific client users
        if (Schema::hasColumn('users', 'project_id')) {
            $existingUsers = DB::table('users')
                ->where('role', 'client_project')
                ->whereNotNull('project_id')
                ->get(['id', 'project_id']);

            foreach ($existingUsers as $user) {
                DB::table('project_user')->updateOrInsert(
                    [
                        'project_id' => $user->project_id,
                        'user_id' => $user->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');
    }
};
