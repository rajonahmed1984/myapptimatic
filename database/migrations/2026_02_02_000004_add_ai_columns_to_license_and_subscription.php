<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->decimal('last_risk_score', 5, 2)->nullable()->after('last_check_ip');
            $table->string('last_risk_reason')->nullable()->after('last_risk_score');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('churn_risk_score', 5, 2)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn(['last_risk_score', 'last_risk_reason']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['churn_risk_score']);
        });
    }
};
