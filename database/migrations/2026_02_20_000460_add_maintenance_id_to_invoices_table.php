<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'maintenance_id')) {
                $table->foreignId('maintenance_id')->nullable()->after('project_id')
                    ->constrained('project_maintenances')
                    ->nullOnDelete();
                $table->index('maintenance_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'maintenance_id')) {
                $table->dropConstrainedForeignId('maintenance_id');
            }
        });
    }
};
