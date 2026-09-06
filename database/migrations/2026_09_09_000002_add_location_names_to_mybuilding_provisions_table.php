<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The district/city ids picked while ordering belong to this app, not to
     * the customer's installation, so the slugs and names travel with them and
     * the installation resolves its own ids from those. Area is free text.
     */
    public function up(): void
    {
        Schema::table('mybuilding_provisions', function (Blueprint $table) {
            $table->string('district_slug')->nullable()->after('district_id');
            $table->string('district_name')->nullable()->after('district_slug');
            $table->string('city_slug')->nullable()->after('city_id');
            $table->string('city_name')->nullable()->after('city_slug');
            $table->string('area_name')->nullable()->after('area_id');
        });
    }

    public function down(): void
    {
        Schema::table('mybuilding_provisions', function (Blueprint $table) {
            $table->dropColumn(['district_slug', 'district_name', 'city_slug', 'city_name', 'area_name']);
        });
    }
};
