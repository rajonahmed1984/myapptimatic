<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_representative_id');
            $table->string('source_type', 50); // project|maintenance|plan
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('currency', 10)->default('BDT');
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->enum('status', ['pending', 'earned', 'payable', 'paid', 'reversed'])->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('commission_payout_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sales_representative_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index('invoice_id');
            $table->index('subscription_id');
            $table->index('project_id');
            $table->index('customer_id');
            $table->index('earned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_earnings');
    }
};
