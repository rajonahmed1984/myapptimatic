<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('address')->nullable()->after('phone');
            $table->string('nid_path')->nullable()->after('address');
            $table->string('photo_path')->nullable()->after('nid_path');
            $table->string('cv_path')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['address', 'nid_path', 'photo_path', 'cv_path']);
        });
    }
};
