<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('recurring_expense_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('expense_date')->index();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('type', 20)->default('one_time');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'expense_date']);
            $table->index('recurring_expense_id');
            $table->unique(['recurring_expense_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
