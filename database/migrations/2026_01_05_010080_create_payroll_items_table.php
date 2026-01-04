<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft'); // draft, approved, paid
            $table->string('pay_type', 20)->default('monthly'); // monthly, hourly
            $table->string('currency', 10)->default('BDT');
            $table->decimal('base_pay', 12, 2)->default(0);
            $table->decimal('timesheet_hours', 10, 2)->default(0);
            $table->decimal('overtime_hours', 10, 2)->default(0);
            $table->decimal('overtime_rate', 12, 2)->nullable();
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('penalties', 12, 2)->default(0);
            $table->decimal('advances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_period_id', 'employee_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
