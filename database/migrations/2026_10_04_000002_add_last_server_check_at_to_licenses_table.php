<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side license checks (the nightly run and the admin Sync button) used
 * to stamp last_check_at, the column that records the installation calling in.
 * Every active license then read "Synced" even if its app had never called.
 * Server checks get their own column so last_check_at means client contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->timestamp('last_server_check_at')->nullable()->after('last_check_ip');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('last_server_check_at');
        });
    }
};
