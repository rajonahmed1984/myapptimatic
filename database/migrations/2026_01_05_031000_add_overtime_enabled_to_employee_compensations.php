<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->boolean('overtime_enabled')->default(false)->after('overtime_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employee_compensations', function (Blueprint $table) {
            $table->dropColumn('overtime_enabled');
        });
    }
};
