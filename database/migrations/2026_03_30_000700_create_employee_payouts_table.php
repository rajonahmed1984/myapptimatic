<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('BDT');
            $table->string('payout_method', 20)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payouts');
    }
};
