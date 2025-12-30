<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'reminder_sent_at')) {
                $table->dateTime('reminder_sent_at')->nullable()->after('overdue_at');
            }
            if (! Schema::hasColumn('invoices', 'first_overdue_reminder_sent_at')) {
                $table->dateTime('first_overdue_reminder_sent_at')->nullable()->after('reminder_sent_at');
            }
            if (! Schema::hasColumn('invoices', 'second_overdue_reminder_sent_at')) {
                $table->dateTime('second_overdue_reminder_sent_at')->nullable()->after('first_overdue_reminder_sent_at');
            }
            if (! Schema::hasColumn('invoices', 'third_overdue_reminder_sent_at')) {
                $table->dateTime('third_overdue_reminder_sent_at')->nullable()->after('second_overdue_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'third_overdue_reminder_sent_at')) {
                $table->dropColumn('third_overdue_reminder_sent_at');
            }
            if (Schema::hasColumn('invoices', 'second_overdue_reminder_sent_at')) {
                $table->dropColumn('second_overdue_reminder_sent_at');
            }
            if (Schema::hasColumn('invoices', 'first_overdue_reminder_sent_at')) {
                $table->dropColumn('first_overdue_reminder_sent_at');
            }
            if (Schema::hasColumn('invoices', 'reminder_sent_at')) {
                $table->dropColumn('reminder_sent_at');
            }
        });
    }
};
