<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32); // created|accepted|rejected|cancelled|executed|expired
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ownership_transfer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_transfer_logs');
    }
};
