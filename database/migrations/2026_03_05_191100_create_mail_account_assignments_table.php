<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_account_assignments')) {
            return;
        }

        Schema::create('mail_account_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->string('assignee_type', 20);
            $table->unsignedBigInteger('assignee_id');
            $table->boolean('can_read')->default(true);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();

            $table->unique(['mail_account_id', 'assignee_type', 'assignee_id'], 'mail_assignment_unique');
            $table->index(['assignee_type', 'assignee_id'], 'mail_assignment_assignee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_account_assignments');
    }
};
