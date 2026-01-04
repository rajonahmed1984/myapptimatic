<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status', 50)->default('todo'); // todo, in_progress, blocked, done
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
