<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('budget_amount', 12, 2)->nullable()->after('notes');
            $table->decimal('planned_hours', 10, 2)->nullable()->after('budget_amount');
            $table->decimal('hourly_cost', 12, 2)->nullable()->after('planned_hours');
            $table->decimal('actual_hours', 10, 2)->nullable()->after('hourly_cost');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget_amount', 'planned_hours', 'hourly_cost', 'actual_hours']);
        });
    }
};
