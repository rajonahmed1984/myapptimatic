<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns have been unconstrained since table creation, so existing rows
        // may already reference deleted parents. Null out dangling references on the
        // nullable columns before adding the constraint, so it doesn't fail on old data.
        $this->nullifyOrphans('commission_earnings', 'invoice_id', 'invoices');
        $this->nullifyOrphans('commission_earnings', 'subscription_id', 'subscriptions');
        $this->nullifyOrphans('commission_earnings', 'project_id', 'projects');
        $this->nullifyOrphans('commission_earnings', 'customer_id', 'customers');
        $this->nullifyOrphans('commission_earnings', 'commission_payout_id', 'commission_payouts');
        $this->nullifyOrphans('commission_payouts', 'project_id', 'projects');

        Schema::table('commission_earnings', function (Blueprint $table) {
            $table->foreign('sales_representative_id')
                ->references('id')->on('sales_representatives')
                ->restrictOnDelete();
            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->nullOnDelete();
            $table->foreign('subscription_id')
                ->references('id')->on('subscriptions')
                ->nullOnDelete();
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->nullOnDelete();
            $table->foreign('commission_payout_id')
                ->references('id')->on('commission_payouts')
                ->nullOnDelete();
        });

        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->foreign('sales_representative_id')
                ->references('id')->on('sales_representatives')
                ->restrictOnDelete();
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->dropForeign(['sales_representative_id']);
            $table->dropForeign(['project_id']);
        });

        Schema::table('commission_earnings', function (Blueprint $table) {
            $table->dropForeign(['sales_representative_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['subscription_id']);
            $table->dropForeign(['project_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['commission_payout_id']);
        });
    }

    private function nullifyOrphans(string $table, string $column, string $referencedTable): void
    {
        DB::table($table)
            ->whereNotNull($column)
            ->whereNotIn($column, function ($query) use ($referencedTable) {
                $query->select('id')->from($referencedTable);
            })
            ->update([$column => null]);
    }
};
