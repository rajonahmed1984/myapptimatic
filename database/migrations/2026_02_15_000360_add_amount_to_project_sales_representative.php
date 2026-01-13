<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_sales_representative')) {
            return;
        }

        Schema::table('project_sales_representative', function (Blueprint $table) {
            if (! Schema::hasColumn('project_sales_representative', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('sales_representative_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_sales_representative')) {
            return;
        }

        Schema::table('project_sales_representative', function (Blueprint $table) {
            if (Schema::hasColumn('project_sales_representative', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
};
