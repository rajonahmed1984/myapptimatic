<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'tax_rate_percent')) {
                $table->decimal('tax_rate_percent', 6, 2)->nullable()->after('subtotal');
            }
            if (! Schema::hasColumn('invoices', 'tax_mode')) {
                $table->string('tax_mode', 20)->nullable()->after('tax_rate_percent');
            }
            if (! Schema::hasColumn('invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
            if (Schema::hasColumn('invoices', 'tax_mode')) {
                $table->dropColumn('tax_mode');
            }
            if (Schema::hasColumn('invoices', 'tax_rate_percent')) {
                $table->dropColumn('tax_rate_percent');
            }
        });
    }
};
