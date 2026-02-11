<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->enum('type', ['regular', 'advance'])->default('regular')->after('sales_representative_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('commission_payouts', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
