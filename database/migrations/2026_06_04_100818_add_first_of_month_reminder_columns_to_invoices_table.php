<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dateTime('reminder_created_day_sent_at')->nullable()->after('third_overdue_reminder_sent_at');
            $table->dateTime('reminder_5d_before_sent_at')->nullable()->after('reminder_created_day_sent_at');
            $table->dateTime('reminder_3d_before_sent_at')->nullable()->after('reminder_5d_before_sent_at');
            $table->dateTime('reminder_1d_before_sent_at')->nullable()->after('reminder_3d_before_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_created_day_sent_at',
                'reminder_5d_before_sent_at',
                'reminder_3d_before_sent_at',
                'reminder_1d_before_sent_at',
            ]);
        });
    }
};
