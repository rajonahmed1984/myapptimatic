<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE project_maintenances ENGINE = InnoDB');
            DB::statement('ALTER TABLE sales_representatives ENGINE = InnoDB');
        }

        if (! Schema::hasTable('project_maintenance_sales_representative')) {
            Schema::create('project_maintenance_sales_representative', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_maintenance_id')
                    ->constrained('project_maintenances', indexName: 'pm_sr_maintenance_fk')
                    ->cascadeOnDelete();
                $table->foreignId('sales_representative_id')
                    ->constrained(indexName: 'pm_sr_sales_rep_fk')
                    ->cascadeOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->timestamps();
                $table->unique(['project_maintenance_id', 'sales_representative_id'], 'maintenance_sales_rep_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_maintenance_sales_representative');
    }
};
