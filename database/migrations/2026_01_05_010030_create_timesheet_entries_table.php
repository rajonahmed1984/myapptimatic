<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('hours', 8, 2)->default(0);
            $table->string('task_note')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamps();

            $table->index(['work_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_entries');
    }
};
