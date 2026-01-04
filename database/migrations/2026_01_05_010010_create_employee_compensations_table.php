<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('salary_type', 20)->default('monthly'); // monthly, hourly
            $table->string('currency', 10)->default('BDT');
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->json('allowances')->nullable();
            $table->json('deductions')->nullable();
            $table->decimal('overtime_rate', 12, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
            $table->index(['salary_type', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensations');
    }
};
