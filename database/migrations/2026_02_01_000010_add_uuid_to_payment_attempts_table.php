<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unique('uuid');
        });

        // Backfill existing rows with UUIDs
        DB::table('payment_attempts')
            ->whereNull('uuid')
            ->lazyById()
            ->each(function ($attempt) {
                DB::table('payment_attempts')
                    ->where('id', $attempt->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
