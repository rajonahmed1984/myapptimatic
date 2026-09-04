<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MyBuilding is sold by building size, and an accepted order has to create
     * the building inside the customer's MyBuilding installation. This records
     * what was ordered and tracks the hand-off, so a failed call can be retried
     * instead of silently leaving the customer with nothing.
     */
    public function up(): void
    {
        Schema::create('mybuilding_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            // What the customer bought.
            $table->string('building_name');
            $table->string('building_address')->nullable();
            $table->unsignedSmallInteger('total_floors')->default(1);
            $table->unsignedSmallInteger('flats_per_floor')->default(4);
            $table->json('floor_plan')->nullable();      // per-floor counts, optional
            $table->unsignedInteger('contracted_flats')->default(0);

            // Where the building has to be created, and the location ids that
            // installation expects.
            $table->string('install_url');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('area_id')->nullable();

            // Owner login created inside MyBuilding.
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone', 32);

            // Hand-off state.
            $table->string('status', 20)->default('pending'); // pending|provisioned|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->unsignedBigInteger('remote_building_id')->nullable();
            $table->unsignedBigInteger('remote_client_account_id')->nullable();
            $table->string('registration_code')->nullable();

            $table->timestamps();

            $table->unique('license_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mybuilding_provisions');
    }
};
