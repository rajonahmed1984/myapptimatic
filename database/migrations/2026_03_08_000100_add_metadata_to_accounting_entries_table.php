<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_entries') || Schema::hasColumn('accounting_entries', 'metadata')) {
            return;
        }

        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_entries') || ! Schema::hasColumn('accounting_entries', 'metadata')) {
            return;
        }

        Schema::table('accounting_entries', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
