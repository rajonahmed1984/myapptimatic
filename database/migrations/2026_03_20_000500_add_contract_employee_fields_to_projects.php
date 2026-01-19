<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'contract_amount')) {
                $table->decimal('contract_amount', 12, 2)->nullable()->after('sales_rep_ids');
            }
            if (! Schema::hasColumn('projects', 'contract_employee_total_earned')) {
                $table->decimal('contract_employee_total_earned', 12, 2)->nullable()->after('contract_amount');
            }
            if (! Schema::hasColumn('projects', 'contract_employee_payable')) {
                $table->decimal('contract_employee_payable', 12, 2)->nullable()->after('contract_employee_total_earned');
            }
            if (! Schema::hasColumn('projects', 'contract_employee_payout_status')) {
                $table->string('contract_employee_payout_status', 20)->nullable()->after('contract_employee_payable');
            }
            if (! Schema::hasColumn('projects', 'contract_employee_payout_reference')) {
                $table->string('contract_employee_payout_reference', 100)->nullable()->after('contract_employee_payout_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'contract_employee_payout_reference')) {
                $table->dropColumn('contract_employee_payout_reference');
            }
            if (Schema::hasColumn('projects', 'contract_employee_payout_status')) {
                $table->dropColumn('contract_employee_payout_status');
            }
            if (Schema::hasColumn('projects', 'contract_employee_payable')) {
                $table->dropColumn('contract_employee_payable');
            }
            if (Schema::hasColumn('projects', 'contract_employee_total_earned')) {
                $table->dropColumn('contract_employee_total_earned');
            }
            if (Schema::hasColumn('projects', 'contract_amount')) {
                $table->dropColumn('contract_amount');
            }
        });
    }
};
