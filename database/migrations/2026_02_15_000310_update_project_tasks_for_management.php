<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('status');
            $table->text('description')->nullable()->after('title');
            $table->string('assigned_type', 30)->nullable()->after('status'); // employee, sales_rep, customer
            $table->unsignedBigInteger('assigned_id')->nullable()->after('assigned_type');
            $table->boolean('customer_visible')->default(false)->after('assigned_id');
            $table->string('status', 50)->default('pending')->change();
            $table->unsignedTinyInteger('progress')->default(0)->after('customer_visible');
            $table->foreignId('created_by')->nullable()->after('progress')->constrained('users')->nullOnDelete();

            $table->index(['assigned_type', 'assigned_id']);
            $table->index('customer_visible');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'start_date',
                'description',
                'assigned_type',
                'assigned_id',
                'customer_visible',
                'progress',
                'created_by',
            ]);
            $table->string('status', 50)->default('todo')->change();
        });
    }
};
