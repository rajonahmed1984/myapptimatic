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
            if (! Schema::hasColumn('licenses', 'auto_suspend_override_until')) {
                $table->date('auto_suspend_override_until')
                    ->nullable()
                    ->after('expires_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'auto_suspend_override_until')) {
                $table->dropColumn('auto_suspend_override_until');
            }
        });
    }
};

