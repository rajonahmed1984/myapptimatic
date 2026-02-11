<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('sales_representative_id');
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
