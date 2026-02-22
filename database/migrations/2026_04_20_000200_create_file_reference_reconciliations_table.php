<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_reference_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('column_name', 100);
            $table->string('metadata_key', 100)->default('');
            $table->text('original_path');
            $table->char('path_hash', 40);
            $table->string('status', 30)->default('missing');
            $table->string('action', 30)->default('flagged');
            $table->json('context')->nullable();
            $table->timestamp('reconciled_at');
            $table->timestamps();

            $table->index(['model_type', 'model_id'], 'idx_frr_model');
            $table->index('status', 'idx_frr_status');
            $table->unique(
                ['model_type', 'model_id', 'column_name', 'metadata_key', 'path_hash'],
                'uq_frr_reference'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_reference_reconciliations');
    }
};
