<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('recurrence_type', 20);
            $table->unsignedInteger('recurrence_interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_run_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('next_run_date');
            $table->index(['status', 'next_run_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
