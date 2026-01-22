<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('run_at');
            $table->unsignedInteger('total_checked')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('expired_count')->default(0);
            $table->unsignedInteger('suspended_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('domain_updates_count')->default(0);
            $table->unsignedInteger('domain_mismatch_count')->default(0);
            $table->unsignedInteger('api_failures_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_sync_runs');
    }
};
