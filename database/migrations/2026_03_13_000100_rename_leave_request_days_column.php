<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('leave_requests', 'days')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->renameColumn('days', 'total_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'total_days')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->renameColumn('total_days', 'days');
            });
        }
    }
};
