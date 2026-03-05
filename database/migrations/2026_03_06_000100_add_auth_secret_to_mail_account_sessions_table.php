<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_account_sessions')) {
            return;
        }

        if (Schema::hasColumn('mail_account_sessions', 'auth_secret')) {
            return;
        }

        Schema::table('mail_account_sessions', function (Blueprint $table) {
            $table->text('auth_secret')->nullable()->after('session_token_hash');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mail_account_sessions')) {
            return;
        }

        if (! Schema::hasColumn('mail_account_sessions', 'auth_secret')) {
            return;
        }

        Schema::table('mail_account_sessions', function (Blueprint $table) {
            $table->dropColumn('auth_secret');
        });
    }
};

