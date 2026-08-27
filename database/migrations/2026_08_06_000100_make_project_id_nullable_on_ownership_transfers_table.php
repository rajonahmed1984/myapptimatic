<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supports the subscription-anchored transfer flow (product/subscription/license
        // move without a linked project), alongside the existing project-anchored flow.
        $this->dropProjectForeignKeyIfExists();

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        $this->addProjectForeignKeyIfNotExists();
    }

    public function down(): void
    {
        $this->dropProjectForeignKeyIfExists();

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        $this->addProjectForeignKeyIfNotExists();
    }

    private function dropProjectForeignKeyIfExists(): void
    {
        if (! Schema::hasTable('ownership_transfers') || ! Schema::hasColumn('ownership_transfers', 'project_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $foreignKeys = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                ['ownership_transfers', 'project_id']
            );

            foreach ($foreignKeys as $fk) {
                $constraintName = $fk->CONSTRAINT_NAME ?? null;
                if ($constraintName) {
                    DB::statement("ALTER TABLE `ownership_transfers` DROP FOREIGN KEY `{$constraintName}`");
                }
            }
            return;
        }

        try {
            Schema::table('ownership_transfers', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        } catch (\Throwable $e) {
            // Ignored if foreign key doesn't exist
        }
    }

    private function addProjectForeignKeyIfNotExists(): void
    {
        if (! Schema::hasTable('ownership_transfers') || ! Schema::hasColumn('ownership_transfers', 'project_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $foreignKeys = DB::select(
                'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
                ['ownership_transfers', 'project_id']
            );

            if (! empty($foreignKeys)) {
                return;
            }
        }

        try {
            Schema::table('ownership_transfers', function (Blueprint $table) {
                $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            });
        } catch (\Throwable $e) {
            // Ignored if foreign key already exists or cannot be created
        }
    }
};
