<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales rep asks for the commission available to them; an admin pays it
 * (recording the method) or declines it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_representative_id')->constrained('sales_representatives')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('BDT');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('commission_payout_id')->nullable()->constrained('commission_payouts')->nullOnDelete();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->string('payout_method', 60)->nullable();
            $table->string('reference')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['sales_representative_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payout_requests');
    }
};
