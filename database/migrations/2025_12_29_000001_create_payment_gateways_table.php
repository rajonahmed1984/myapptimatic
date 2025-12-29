<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver');
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        DB::table('payment_gateways')->insert([
            [
                'name' => 'Manual / Bank Transfer',
                'slug' => 'manual',
                'driver' => 'manual',
                'is_active' => true,
                'sort_order' => 1,
                'settings' => json_encode([
                    'instructions' => '',
                    'account_name' => '',
                    'account_number' => '',
                    'bank_name' => '',
                    'branch' => '',
                    'routing_number' => '',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'bKash',
                'slug' => 'bkash',
                'driver' => 'bkash',
                'is_active' => false,
                'sort_order' => 2,
                'settings' => json_encode([
                    'merchant_number' => '',
                    'username' => '',
                    'password' => '',
                    'app_key' => '',
                    'app_secret' => '',
                    'sandbox' => true,
                    'instructions' => '',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SSLCommerz',
                'slug' => 'sslcommerz',
                'driver' => 'sslcommerz',
                'is_active' => false,
                'sort_order' => 3,
                'settings' => json_encode([
                    'store_id' => '',
                    'store_password' => '',
                    'sandbox' => true,
                    'instructions' => '',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PayPal',
                'slug' => 'paypal',
                'driver' => 'paypal',
                'is_active' => false,
                'sort_order' => 4,
                'settings' => json_encode([
                    'client_id' => '',
                    'client_secret' => '',
                    'sandbox' => true,
                    'instructions' => '',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
