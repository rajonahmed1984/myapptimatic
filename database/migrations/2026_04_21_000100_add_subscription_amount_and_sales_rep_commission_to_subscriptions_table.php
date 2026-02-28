<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'subscription_amount')) {
                $table->decimal('subscription_amount', 12, 2)->nullable()->after('sales_rep_id');
            }

            if (! Schema::hasColumn('subscriptions', 'sales_rep_commission_amount')) {
                $table->decimal('sales_rep_commission_amount', 12, 2)->nullable()->after('subscription_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'sales_rep_commission_amount')) {
                $table->dropColumn('sales_rep_commission_amount');
            }

            if (Schema::hasColumn('subscriptions', 'subscription_amount')) {
                $table->dropColumn('subscription_amount');
            }
        });
    }
};
