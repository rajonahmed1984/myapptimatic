<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_settings')) {
            return;
        }

        DB::table('tax_settings')->update([
            'invoice_tax_label' => 'VAT',
        ]);

        DB::table('tax_settings')
            ->whereNotNull('invoice_tax_note_template')
            ->update([
                'invoice_tax_note_template' => DB::raw(
                    "REPLACE(REPLACE(invoice_tax_note_template, 'VAT/Tax', 'VAT'), 'Tax', 'VAT')"
                ),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tax_settings')) {
            return;
        }

        DB::table('tax_settings')
            ->where('invoice_tax_label', 'VAT')
            ->update([
                'invoice_tax_label' => 'VAT/Tax',
            ]);

        DB::table('tax_settings')
            ->whereNotNull('invoice_tax_note_template')
            ->update([
                'invoice_tax_note_template' => DB::raw(
                    "REPLACE(invoice_tax_note_template, 'VAT', 'Tax')"
                ),
            ]);
    }
};
