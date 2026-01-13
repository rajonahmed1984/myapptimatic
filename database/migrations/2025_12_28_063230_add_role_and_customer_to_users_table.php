<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('client')->after('password');
            });
        }

        if (!Schema::hasColumn('users', 'customer_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->after('role')->constrained()->nullOnDelete();
            });
        }

        $hasRole = Schema::hasColumn('users', 'role');
        $hasCustomer = Schema::hasColumn('users', 'customer_id');
        $indexExists = !empty(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_role_customer_id_index'"));

        if ($hasRole && $hasCustomer && !$indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['role', 'customer_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasRole = Schema::hasColumn('users', 'role');
        $hasCustomer = Schema::hasColumn('users', 'customer_id');
        $indexExists = !empty(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_role_customer_id_index'"));

        if ($hasRole && $hasCustomer && $indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role', 'customer_id']);
            });
        }

        if ($hasCustomer) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_id');
            });
        }

        if ($hasRole) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
