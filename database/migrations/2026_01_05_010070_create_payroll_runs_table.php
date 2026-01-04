<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued'); // queued, completed, failed
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('ran_at')->nullable();
            $table->timestamps();

            $table->index(['payroll_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
