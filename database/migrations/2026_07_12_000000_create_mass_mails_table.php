<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_mails', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('subject');
            $blueprint->text('body');
            $blueprint->string('target_status')->default('all');
            $blueprint->integer('total_recipients')->default(0);
            $blueprint->integer('sent_count')->default(0);
            $blueprint->string('status')->default('pending');
            $blueprint->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_mails');
    }
};
