<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_maintenances', function (Blueprint $table) {
            if (! Schema::hasColumn('project_maintenances', 'sales_rep_visible')) {
                $table->boolean('sales_rep_visible')->default(false)->after('auto_invoice');
                $table->index('sales_rep_visible');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_maintenances', function (Blueprint $table) {
            if (Schema::hasColumn('project_maintenances', 'sales_rep_visible')) {
                $table->dropIndex(['sales_rep_visible']);
                $table->dropColumn('sales_rep_visible');
            }
        });
    }
};
