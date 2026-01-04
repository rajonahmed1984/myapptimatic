<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_representative_id');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 10)->default('BDT');
            $table->enum('payout_method', ['bank', 'mobile', 'cash'])->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['draft', 'paid', 'reversed'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['sales_representative_id', 'paid_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
    }
};
