<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('licenses', 'last_verified_at')) {
                $table->timestamp('last_verified_at')->nullable()->after('last_check_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'last_verified_at')) {
                $table->dropColumn('last_verified_at');
            }
        });
    }
};
