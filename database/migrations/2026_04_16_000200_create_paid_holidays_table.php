<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paid_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('name', 150);
            $table->string('note', 500)->nullable();
            $table->boolean('is_paid')->default(true);
            $table->timestamps();

            $table->unique('holiday_date');
            $table->index(['holiday_date', 'is_paid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paid_holidays');
    }
};
