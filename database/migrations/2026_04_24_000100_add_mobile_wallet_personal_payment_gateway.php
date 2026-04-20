<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        $exists = DB::table('payment_gateways')
            ->where('slug', 'mobile_wallet_personal')
            ->exists();

        if ($exists) {
            return;
        }

        $nextSortOrder = (int) DB::table('payment_gateways')->max('sort_order') + 1;

        DB::table('payment_gateways')->insert([
            'name' => 'Mobile Wallet (Personal)',
            'slug' => 'mobile_wallet_personal',
            'driver' => 'manual',
            'is_active' => true,
            'sort_order' => max(1, $nextSortOrder),
            'settings' => json_encode([
                'instructions' => '',
                'payment_url' => '',
                'account_name' => '',
                'account_number' => '',
                'bank_name' => '',
                'branch' => '',
                'routing_number' => '',
                'button_label' => '',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateways')) {
            return;
        }

        DB::table('payment_gateways')
            ->where('slug', 'mobile_wallet_personal')
            ->delete();
    }
};
