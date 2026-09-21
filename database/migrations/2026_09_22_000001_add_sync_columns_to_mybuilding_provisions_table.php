<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The building keeps changing after it is handed over (floors switched
     * off, flats merged or removed), and the installation reports each change
     * back. These hold the latest report next to what was ordered.
     */
    public function up(): void
    {
        Schema::table('mybuilding_provisions', function (Blueprint $table) {
            $table->unsignedInteger('total_flats')->nullable()->after('contracted_flats');
            $table->unsignedInteger('active_flats')->nullable()->after('total_flats');
            $table->json('inactive_floors')->nullable()->after('active_flats');
            $table->timestamp('last_synced_at')->nullable()->after('provisioned_at');
            $table->string('last_sync_action', 100)->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('mybuilding_provisions', function (Blueprint $table) {
            $table->dropColumn(['total_flats', 'active_flats', 'inactive_floors', 'last_synced_at', 'last_sync_action']);
        });
    }
};
