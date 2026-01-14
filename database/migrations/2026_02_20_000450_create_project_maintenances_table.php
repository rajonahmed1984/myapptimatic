<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10);
            $table->enum('billing_cycle', ['monthly', 'yearly']);
            $table->date('start_date');
            $table->date('next_billing_date');
            $table->timestamp('last_billed_at')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->boolean('auto_invoice')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'next_billing_date']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_maintenances');
    }
};
