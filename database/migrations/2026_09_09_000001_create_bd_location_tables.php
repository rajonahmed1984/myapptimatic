<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bangladesh districts and their upazilas/thanas, owned by this app so the
     * ordering screen never depends on a customer's MyBuilding installation
     * being reachable. Slugs are the key carried across to that installation.
     */
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('bn_name', 150)->nullable();
            $table->string('division', 100)->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->string('bn_name', 150)->nullable();
            $table->timestamps();

            $table->unique(['district_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('districts');
    }
};
