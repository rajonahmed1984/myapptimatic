<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->timestamp('login_at')->useCurrent();
            $table->timestamp('logout_at')->nullable()->index();
            $table->timestamp('last_seen_at')->useCurrent()->index();
            $table->unsignedBigInteger('active_seconds')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_sessions');
    }
};
