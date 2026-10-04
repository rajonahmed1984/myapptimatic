<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A sales rep paying money back is recorded as a "recovery" payout with a
 * negative amount, so every sum over paid payouts (rep balance, sales payout
 * expense) nets it off without special cases. The enum only allowed
 * regular/advance, so the column becomes a short string.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->string('type', 20)->default('regular')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('commission_payouts')->where('type', 'recovery')->exists()) {
            throw new RuntimeException('Recovery payouts exist; remove them before rolling back.');
        }

        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->enum('type', ['regular', 'advance'])->default('regular')->change();
        });
    }
};
