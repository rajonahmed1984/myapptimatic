<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic user reference
            $table->string('user_type');  // App\Models\Employee, App\Models\User, App\Models\Customer, App\Models\SalesRepresentative
            $table->unsignedBigInteger('user_id');
            
            // Guard and session info
            $table->string('guard');  // 'employee', 'web', 'client', 'rep'
            $table->string('session_id')->index();
            
            // Session timestamps
            $table->dateTime('login_at')->index();
            $table->dateTime('logout_at')->nullable()->index();
            $table->dateTime('last_seen_at')->index();
            
            // Activity tracking
            $table->bigInteger('active_seconds')->default(0);
            
            // Request metadata
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamps();
            
            // Composite indexes
            $table->index(['user_type', 'user_id', 'login_at']);
            $table->index(['guard', 'login_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
