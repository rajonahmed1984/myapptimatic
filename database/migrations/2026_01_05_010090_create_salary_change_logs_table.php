<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('old_compensation')->nullable();
            $table->json('new_compensation')->nullable();
            $table->string('reason')->nullable();
            $table->dateTime('changed_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_change_logs');
    }
};
