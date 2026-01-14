<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->string('nid_path')->nullable();
            $table->string('cv_path')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->string('nid_path')->nullable();
            $table->string('cv_path')->nullable();
        });

        Schema::table('sales_representatives', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->string('nid_path')->nullable();
            $table->string('cv_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'nid_path', 'cv_path']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'nid_path', 'cv_path']);
        });

        Schema::table('sales_representatives', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'nid_path', 'cv_path']);
        });
    }
};
