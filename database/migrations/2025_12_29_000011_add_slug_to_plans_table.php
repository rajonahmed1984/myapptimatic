<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        $plans = DB::table('plans')->select('id', 'name')->get();
        $used = [];

        foreach ($plans as $plan) {
            $base = Str::slug((string) $plan->name);
            if ($base === '') {
                $base = 'plan';
            }

            $slug = $base;
            if (isset($used[$slug])) {
                $slug = $base.'-'.$plan->id;
            }

            $used[$slug] = true;

            DB::table('plans')
                ->where('id', $plan->id)
                ->update(['slug' => $slug]);
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
