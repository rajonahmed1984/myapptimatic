<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supports the subscription-anchored transfer flow (product/subscription/license
        // move without a linked project), alongside the existing project-anchored flow.
        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->change();
        });

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable(false)->change();
        });

        Schema::table('ownership_transfers', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
