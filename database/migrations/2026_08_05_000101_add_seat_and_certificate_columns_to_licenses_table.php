<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('seat_limit')->nullable()->after('max_domains');
            $table->unsignedInteger('last_seats_reported')->nullable()->after('seat_limit');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['seat_limit', 'last_seats_reported']);
        });
    }
};
