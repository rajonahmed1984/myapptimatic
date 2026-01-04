<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'sales_rep_id')) {
                $table->unsignedBigInteger('sales_rep_id')->nullable()->after('invoice_id');
                $table->index('sales_rep_id');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'sales_rep_id')) {
                $table->unsignedBigInteger('sales_rep_id')->nullable()->after('plan_id');
                $table->index('sales_rep_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'default_sales_rep_id')) {
                $table->unsignedBigInteger('default_sales_rep_id')->nullable()->after('status');
                $table->index('default_sales_rep_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['sales_rep_id']);
            $table->dropColumn('sales_rep_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['sales_rep_id']);
            $table->dropColumn('sales_rep_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['default_sales_rep_id']);
            $table->dropColumn('default_sales_rep_id');
        });
    }
};
