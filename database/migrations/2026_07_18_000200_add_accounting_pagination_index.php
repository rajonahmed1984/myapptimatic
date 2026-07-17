<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'idx_accounting_entry_date_id';

    public function up(): void
    {
        if (! Schema::hasTable('accounting_entries')) {
            return;
        }

        try {
            Schema::table('accounting_entries', function (Blueprint $table): void {
                $table->index(['entry_date', 'id'], self::INDEX_NAME);
            });
        } catch (Throwable) {
            // The index may already exist on installations with custom tuning.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_entries')) {
            return;
        }

        try {
            Schema::table('accounting_entries', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX_NAME);
            });
        } catch (Throwable) {
            // Keep rollback safe when the index was not created by this migration.
        }
    }
};
