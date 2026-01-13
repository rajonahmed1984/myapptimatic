<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CURRENCY_OPTIONS = ['BDT', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'JPY', 'INR', 'SGD', 'AED'];

    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        DB::table('projects')
            ->whereNotNull('currency')
            ->update(['currency' => DB::raw('UPPER(currency)')]);

        $valid = array_map('strtoupper', self::CURRENCY_OPTIONS);
        DB::table('projects')
            ->whereNull('currency')
            ->orWhereNotIn('currency', $valid)
            ->update(['currency' => 'BDT']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        DB::table('projects')
            ->whereNotNull('currency')
            ->update(['currency' => DB::raw('UPPER(currency)')]);
    }
};
