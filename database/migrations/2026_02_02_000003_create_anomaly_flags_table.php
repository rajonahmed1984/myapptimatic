<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anomaly_flags', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('flag_type', 48); // abuse|sync_anomaly|payment
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->string('summary');
            $table->string('state', 24)->default('open'); // open|resolved|suppressed|error
            $table->timestamp('detected_at')->useCurrent();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['flag_type', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_flags');
    }
};
