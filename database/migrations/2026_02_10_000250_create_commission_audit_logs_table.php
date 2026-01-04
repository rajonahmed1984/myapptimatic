<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_representative_id')->nullable();
            $table->unsignedBigInteger('commission_earning_id')->nullable();
            $table->unsignedBigInteger('commission_payout_id')->nullable();
            $table->string('action', 100);
            $table->string('status_from', 50)->nullable();
            $table->string('status_to', 50)->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('sales_representative_id');
            $table->index('commission_earning_id');
            $table->index('commission_payout_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_audit_logs');
    }
};
