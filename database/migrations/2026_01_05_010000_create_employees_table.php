<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('designation')->nullable();
            $table->string('department', 100)->nullable();
            $table->string('employment_type', 30)->default('full_time'); // full_time, part_time, contract
            $table->string('work_mode', 20)->default('remote'); // remote, onsite, hybrid
            $table->date('join_date')->nullable();
            $table->string('status', 30)->default('active'); // active, on_leave, terminated
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'employment_type']);
            $table->index(['department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
