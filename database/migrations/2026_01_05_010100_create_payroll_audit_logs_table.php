<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['payroll_item_id']);
            $table->index(['event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_audit_logs');
    }
};
