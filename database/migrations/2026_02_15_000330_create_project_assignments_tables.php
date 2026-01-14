<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure tables use InnoDB engine (required for foreign keys, MySQL only).
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE employees ENGINE = InnoDB');
            DB::statement('ALTER TABLE sales_representatives ENGINE = InnoDB');
        }

        if (!Schema::hasTable('employee_project')) {
            Schema::create('employee_project', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['project_id', 'employee_id']);
            });
        }

        if (!Schema::hasTable('project_sales_representative')) {
            Schema::create('project_sales_representative', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sales_representative_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['project_id', 'sales_representative_id'], 'project_sales_rep_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sales_representative');
        Schema::dropIfExists('employee_project');
    }
};
