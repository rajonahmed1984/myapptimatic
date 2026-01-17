<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'software_overhead')) {
                $table->dropColumn('software_overhead');
            }
            if (Schema::hasColumn('projects', 'website_overhead')) {
                $table->dropColumn('website_overhead');
            }
        });

        Schema::create('project_overheads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('short_details', 255);
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'software_overhead')) {
                $table->decimal('software_overhead', 12, 2)->nullable()->after('actual_hours');
            }
            if (! Schema::hasColumn('projects', 'website_overhead')) {
                $table->decimal('website_overhead', 12, 2)->nullable()->after('software_overhead');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_overheads');

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'software_overhead')) {
                $table->dropColumn('software_overhead');
            }
            if (Schema::hasColumn('projects', 'website_overhead')) {
                $table->dropColumn('website_overhead');
            }
        });
    }
};
