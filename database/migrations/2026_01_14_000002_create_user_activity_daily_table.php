<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_dailies', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic user reference
            $table->string('user_type');
            $table->unsignedBigInteger('user_id');
            
            // Guard
            $table->string('guard');
            
            // Date
            $table->date('date')->index();
            
            // Daily aggregates
            $table->integer('sessions_count')->default(0);
            $table->bigInteger('active_seconds')->default(0);
            
            // First and last activity
            $table->dateTime('first_login_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['user_type', 'user_id', 'guard', 'date']);
            
            // Additional indexes
            $table->index(['guard', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_dailies');
    }
};
