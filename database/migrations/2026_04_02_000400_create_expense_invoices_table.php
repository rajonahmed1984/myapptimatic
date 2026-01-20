<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->string('expense_type', 50);
            $table->string('invoice_no')->unique();
            $table->string('status', 20)->default('issued');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('BDT');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']);
            $table->index(['expense_type', 'invoice_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_invoices');
    }
};
