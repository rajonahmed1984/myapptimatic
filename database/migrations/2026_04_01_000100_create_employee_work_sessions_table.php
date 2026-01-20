<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->dateTime('last_activity_at')->index();
            $table->unsignedBigInteger('active_seconds')->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_sessions');
    }
};
