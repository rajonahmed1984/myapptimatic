<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('status');
            $table->date('expected_end_date')->nullable()->after('start_date');
            $table->decimal('total_budget', 12, 2)->nullable()->after('notes');
            $table->decimal('initial_payment_amount', 12, 2)->nullable()->after('total_budget');
            $table->string('currency', 10)->nullable()->after('initial_payment_amount');
            $table->json('sales_rep_ids')->nullable()->after('currency');
            $table->index('start_date');
            $table->index('expected_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'start_date',
                'expected_end_date',
                'total_budget',
                'initial_payment_amount',
                'currency',
                'sales_rep_ids',
            ]);
        });
    }
};
