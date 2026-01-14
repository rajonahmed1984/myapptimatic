<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addForeignKeyIfMissing('customers', 'default_sales_rep_id', 'sales_representatives');
        $this->addForeignKeyIfMissing('orders', 'sales_rep_id', 'sales_representatives');
        $this->addForeignKeyIfMissing('subscriptions', 'sales_rep_id', 'sales_representatives');
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('customers', 'default_sales_rep_id');
        $this->dropForeignKeyIfExists('orders', 'sales_rep_id');
        $this->dropForeignKeyIfExists('subscriptions', 'sales_rep_id');
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $referencesTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $referencesTable) {
            $table->foreign($column)
                ->references('id')
                ->on($referencesTable)
                ->nullOnDelete();
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $result = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return ! empty($result);
    }
};
