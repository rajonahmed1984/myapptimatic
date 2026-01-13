<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('advance_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('final_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('name');
            $table->string('type', 50)->default('software'); // software, website, other
            $table->string('status', 50)->default('ongoing'); // ongoing, hold, complete, cancel
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
