<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamps();
        });

        $maxNumber = (int) DB::table('invoices')
            ->selectRaw('MAX(CAST(number AS UNSIGNED)) as max_number')
            ->value('max_number');

        DB::table('invoice_sequences')->insert([
            'id' => 1,
            'current_number' => $maxNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
