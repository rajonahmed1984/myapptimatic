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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'otp_enabled')) {
                $table->boolean('otp_enabled')->default(false)->after('role');
            }
            if (! Schema::hasColumn('users', 'login_otp_code')) {
                $table->string('login_otp_code', 64)->nullable()->after('otp_enabled');
            }
            if (! Schema::hasColumn('users', 'login_otp_expires_at')) {
                $table->timestamp('login_otp_expires_at')->nullable()->after('login_otp_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'login_otp_expires_at')) {
                $columns[] = 'login_otp_expires_at';
            }
            if (Schema::hasColumn('users', 'login_otp_code')) {
                $columns[] = 'login_otp_code';
            }
            if (Schema::hasColumn('users', 'otp_enabled')) {
                $columns[] = 'otp_enabled';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
