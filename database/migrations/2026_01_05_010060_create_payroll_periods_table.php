<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_key')->unique(); // e.g. 2026-01
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft'); // draft, finalized, paid
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
