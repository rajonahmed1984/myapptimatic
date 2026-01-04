<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 50); // sales_rep|product|plan|project_type
            $table->string('scope_id', 100)->nullable();
            $table->string('source_type', 50); // project|maintenance|plan
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 12, 2);
            $table->boolean('recurring')->default(false);
            $table->boolean('first_payment_only')->default(false);
            $table->decimal('cap_amount', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->index('source_type');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
