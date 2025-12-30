<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('licenses')) {
            return;
        }

        Schema::table('licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('licenses', 'expiry_first_notice_sent_at')) {
                $table->timestamp('expiry_first_notice_sent_at')->nullable()->after('last_check_at');
            }
            if (! Schema::hasColumn('licenses', 'expiry_second_notice_sent_at')) {
                $table->timestamp('expiry_second_notice_sent_at')->nullable()->after('expiry_first_notice_sent_at');
            }
            if (! Schema::hasColumn('licenses', 'expiry_expired_notice_sent_at')) {
                $table->timestamp('expiry_expired_notice_sent_at')->nullable()->after('expiry_second_notice_sent_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('licenses')) {
            return;
        }

        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'expiry_expired_notice_sent_at')) {
                $table->dropColumn('expiry_expired_notice_sent_at');
            }
            if (Schema::hasColumn('licenses', 'expiry_second_notice_sent_at')) {
                $table->dropColumn('expiry_second_notice_sent_at');
            }
            if (Schema::hasColumn('licenses', 'expiry_first_notice_sent_at')) {
                $table->dropColumn('expiry_first_notice_sent_at');
            }
        });
    }
};
