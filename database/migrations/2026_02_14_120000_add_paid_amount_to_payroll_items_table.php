<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('net_pay');
        });

        DB::table('payroll_items')
            ->where('status', 'paid')
            ->update([
                'paid_amount' => DB::raw('net_pay'),
            ]);
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('paid_amount');
        });
    }
};
