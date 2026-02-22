<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expense_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained('recurring_expenses')->cascadeOnDelete();
            $table->string('payment_method', 60);
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('paid_at');
            $table->string('payment_reference', 120)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recurring_expense_id', 'paid_at']);
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expense_advances');
    }
};
