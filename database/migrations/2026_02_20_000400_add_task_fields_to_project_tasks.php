<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->enum('task_type', ['bug', 'feature', 'support', 'design', 'upload', 'custom'])
                ->default('feature')
                ->after('description');
            $table->enum('priority', ['low', 'medium', 'high'])
                ->default('medium')
                ->after('status');
            $table->unsignedInteger('time_estimate_minutes')
                ->nullable()
                ->after('progress');
            $table->json('tags')->nullable()->after('time_estimate_minutes');
            $table->json('relationship_ids')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'task_type',
                'priority',
                'time_estimate_minutes',
                'tags',
                'relationship_ids',
            ]);
        });
    }
};
