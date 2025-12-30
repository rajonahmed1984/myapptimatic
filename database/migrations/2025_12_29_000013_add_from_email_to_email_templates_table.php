<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates') || Schema::hasColumn('email_templates', 'from_email')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('from_email')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_templates') || ! Schema::hasColumn('email_templates', 'from_email')) {
            return;
        }

        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropColumn('from_email');
        });
    }
};
