<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'auto_closed_at')) {
                $table->timestamp('auto_closed_at')->nullable()->after('closed_at');
            }
            if (! Schema::hasColumn('support_tickets', 'admin_reminder_sent_at')) {
                $table->timestamp('admin_reminder_sent_at')->nullable()->after('auto_closed_at');
            }
            if (! Schema::hasColumn('support_tickets', 'feedback_sent_at')) {
                $table->timestamp('feedback_sent_at')->nullable()->after('admin_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'feedback_sent_at')) {
                $table->dropColumn('feedback_sent_at');
            }
            if (Schema::hasColumn('support_tickets', 'admin_reminder_sent_at')) {
                $table->dropColumn('admin_reminder_sent_at');
            }
            if (Schema::hasColumn('support_tickets', 'auto_closed_at')) {
                $table->dropColumn('auto_closed_at');
            }
        });
    }
};
