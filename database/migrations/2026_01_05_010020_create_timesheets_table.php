<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('draft'); // draft, submitted, approved, locked
            $table->decimal('total_hours', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'period_start', 'period_end']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
