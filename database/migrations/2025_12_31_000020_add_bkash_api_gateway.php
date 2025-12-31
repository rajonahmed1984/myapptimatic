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
            ->where('slug', 'bkash_api')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('payment_gateways')->insert([
            'name' => 'bKash API',
            'slug' => 'bkash_api',
            'driver' => 'bkash_api',
            'is_active' => false,
            'sort_order' => 5,
            'settings' => json_encode([
                'api_key' => '',
                'merchant_short_code' => '',
                'service_id' => '',
                'sandbox' => true,
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
            ->where('slug', 'bkash_api')
            ->delete();
    }
};
