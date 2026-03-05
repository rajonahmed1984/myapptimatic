<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_accounts')) {
            return;
        }

        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('display_name')->nullable();
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_encryption', 10)->nullable();
            $table->boolean('imap_validate_cert')->default(true);
            $table->string('status', 32)->default('active');
            $table->timestamp('last_auth_failed_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
