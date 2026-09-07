<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bangladesh areas/neighbourhoods (e.g. West Agargaon under Dhaka city, Dhaka district).
     */
    public function up(): void
    {
        Schema::dropIfExists('areas');

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('bn_name', 150)->nullable();
            $table->timestamps();

            $table->index(['city_id', 'slug']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
