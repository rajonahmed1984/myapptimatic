<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'project_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            });
        }

        $hasProject = Schema::hasColumn('users', 'project_id');
        $hasCustomer = Schema::hasColumn('users', 'customer_id');
        $driver = DB::getDriverName();
        $indexExists = false;
        if ($driver === 'mysql') {
            $indexExists = ! empty(DB::select("SHOW INDEX FROM `users` WHERE Key_name = 'users_customer_id_project_id_index'"));
        }

        if ($hasProject && $hasCustomer && ! $indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['customer_id', 'project_id']);
            });
        }
    }

    public function down(): void
    {
        $hasProject = Schema::hasColumn('users', 'project_id');
        $hasCustomer = Schema::hasColumn('users', 'customer_id');
        $driver = DB::getDriverName();
        $indexExists = false;
        if ($driver === 'mysql') {
            $indexExists = ! empty(DB::select("SHOW INDEX FROM `users` WHERE Key_name = 'users_customer_id_project_id_index'"));
        }

        if ($hasProject && $hasCustomer && $indexExists) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['customer_id', 'project_id']);
            });
        }

        if ($hasProject) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }
    }
};
