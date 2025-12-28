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
            $table->string('role')->default('client')->after('password');
            $table->foreignId('customer_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->index(['role', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'customer_id']);
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn('role');
        });
    }
};
