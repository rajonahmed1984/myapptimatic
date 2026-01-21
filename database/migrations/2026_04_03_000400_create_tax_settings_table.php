<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tax_mode_default', 20)->default('exclusive');
            $table->foreignId('default_tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('invoice_tax_label')->default('VAT/Tax');
            $table->text('invoice_tax_note_template')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
