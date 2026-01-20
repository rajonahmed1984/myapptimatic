<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_work_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date')->index();
            $table->unsignedBigInteger('active_seconds')->default(0);
            $table->unsignedInteger('required_seconds')->default(0);
            $table->decimal('generated_salary_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('generated');
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_summaries');
    }
};
