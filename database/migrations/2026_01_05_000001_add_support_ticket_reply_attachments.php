<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_ticket_replies')) {
            return;
        }

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('support_ticket_replies', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_ticket_replies')) {
            return;
        }

        Schema::table('support_ticket_replies', function (Blueprint $table) {
            if (Schema::hasColumn('support_ticket_replies', 'attachment_path')) {
                $table->dropColumn('attachment_path');
            }
        });
    }
};
