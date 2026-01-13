<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, ensure projects table uses InnoDB engine
        DB::statement('ALTER TABLE projects ENGINE = InnoDB');

        if (!Schema::hasColumn('invoices', 'project_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('invoices', 'type')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('type', 50)->nullable()->after('project_id');
                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'project_id')) {
                // Check if foreign key exists before dropping
                $connection = DB::getDoctrineSchemaManager();
                $indexes = $connection->listTableForeignKeys('invoices');
                foreach ($indexes as $fk) {
                    if ($fk->getLocalColumns() === ['project_id']) {
                        $table->dropForeign(['project_id']);
                        break;
                    }
                }
                $table->dropColumn('project_id');
            }
            if (Schema::hasColumn('invoices', 'type')) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            }
        });
    }
};
