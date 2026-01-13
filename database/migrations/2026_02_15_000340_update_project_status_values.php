<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        DB::table('projects')->where('status', 'active')->update(['status' => 'ongoing']);
        DB::table('projects')->where('status', 'on_hold')->update(['status' => 'hold']);
        DB::table('projects')->where('status', 'completed')->update(['status' => 'complete']);
        DB::table('projects')->where('status', 'cancelled')->update(['status' => 'cancel']);
        DB::table('projects')->where('status', 'draft')->update(['status' => 'ongoing']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        DB::table('projects')->where('status', 'ongoing')->update(['status' => 'active']);
        DB::table('projects')->where('status', 'hold')->update(['status' => 'on_hold']);
        DB::table('projects')->where('status', 'complete')->update(['status' => 'completed']);
        DB::table('projects')->where('status', 'cancel')->update(['status' => 'cancelled']);
    }
};
