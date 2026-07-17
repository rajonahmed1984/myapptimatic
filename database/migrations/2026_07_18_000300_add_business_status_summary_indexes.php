<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'incomes' => ['idx_incomes_income_date' => ['income_date']],
        'payroll_items' => ['idx_payroll_status_paid_at' => ['status', 'paid_at']],
        'employee_payouts' => ['idx_employee_payouts_paid_at' => ['paid_at']],
        'commission_payouts' => ['idx_commission_status_paid_at' => ['status', 'paid_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $indexName => $columns) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
                        $table->index($columns, $indexName);
                    });
                } catch (Throwable) {
                    // The index may already exist on installations with custom tuning.
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach (array_reverse($indexes, true) as $indexName => $columns) {
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                        $table->dropIndex($indexName);
                    });
                } catch (Throwable) {
                    // Keep rollback safe when the index was not created by this migration.
                }
            }
        }
    }
};
