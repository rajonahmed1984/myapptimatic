<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('reader_type', 32);
            $table->unsignedBigInteger('reader_id');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'reader_type', 'reader_id'], 'project_message_reads_unique');
            $table->index(['reader_type', 'reader_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_message_reads');
    }
};
