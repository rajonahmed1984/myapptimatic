<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('contract_file_path')->nullable()->after('actual_hours');
            $table->string('contract_original_name')->nullable()->after('contract_file_path');
            $table->string('proposal_file_path')->nullable()->after('contract_original_name');
            $table->string('proposal_original_name')->nullable()->after('proposal_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'contract_file_path',
                'contract_original_name',
                'proposal_file_path',
                'proposal_original_name',
            ]);
        });
    }
};
