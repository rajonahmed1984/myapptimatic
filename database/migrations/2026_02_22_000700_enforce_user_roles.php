<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        DB::table('users')
            ->whereNull('role')
            ->update(['role' => Role::CLIENT]);

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(255) NOT NULL DEFAULT '".Role::CLIENT."'");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT '".Role::CLIENT."'");
            DB::statement("ALTER TABLE users ALTER COLUMN role SET NOT NULL");
        }

        if ($driver === 'mysql') {
            $indexExists = ! empty(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_role_index'"));
            if (! $indexExists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index(['role']);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(255) NULL");
            $indexExists = ! empty(DB::select("SHOW INDEX FROM users WHERE Key_name = 'users_role_index'"));
            if ($indexExists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex(['role']);
                });
            }
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users ALTER COLUMN role DROP NOT NULL");
        }
    }
};
