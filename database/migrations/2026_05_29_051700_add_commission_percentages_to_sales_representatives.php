<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_representatives', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_representatives', 'project_commission_percentage')) {
                $table->decimal('project_commission_percentage', 5, 2)->nullable()->after('status');
            }
            if (! Schema::hasColumn('sales_representatives', 'subscription_commission_percentage')) {
                $table->decimal('subscription_commission_percentage', 5, 2)->nullable()->after('project_commission_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_representatives', function (Blueprint $table) {
            if (Schema::hasColumn('sales_representatives', 'project_commission_percentage')) {
                $table->dropColumn('project_commission_percentage');
            }
            if (Schema::hasColumn('sales_representatives', 'subscription_commission_percentage')) {
                $table->dropColumn('subscription_commission_percentage');
            }
        });
    }
};
