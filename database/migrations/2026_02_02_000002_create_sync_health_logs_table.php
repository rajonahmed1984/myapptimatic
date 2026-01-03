<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24); // success|stale|error
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('retries')->default(0);
            $table->string('source', 24)->nullable(); // api|manual|cron
            $table->string('message')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_health_logs');
    }
};
