<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
                $table->index(['user_id', 'customer_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
                $table->dropIndex(['user_id', 'customer_id']);
            }
        });
    }
};
