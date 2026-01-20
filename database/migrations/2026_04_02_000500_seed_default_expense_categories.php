<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            return;
        }

        $defaults = [
            ['name' => 'Salaries', 'description' => 'Salary payments', 'status' => 'active'],
            ['name' => 'Contractual Payouts', 'description' => 'Contract employee payouts', 'status' => 'active'],
            ['name' => 'Sales Rep Payouts', 'description' => 'Sales representative payouts', 'status' => 'active'],
        ];

        foreach ($defaults as $category) {
            $exists = DB::table('expense_categories')->where('name', $category['name'])->exists();
            if (! $exists) {
                DB::table('expense_categories')->insert(array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        // Defaults are removed when expense_categories table is dropped.
    }
};
