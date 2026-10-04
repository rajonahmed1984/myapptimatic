<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firebase Cloud Messaging tokens of the mobile app installs a user is signed
 * in on. A token belongs to one install, so it moves to whoever signs in there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
