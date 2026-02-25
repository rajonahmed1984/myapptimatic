<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_messages')) {
            return;
        }

        Schema::table('project_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('project_messages', 'reply_to_message_id')) {
                $table->foreignId('reply_to_message_id')
                    ->nullable()
                    ->after('attachment_path')
                    ->constrained('project_messages')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('project_messages', 'reactions')) {
                $table->json('reactions')->nullable()->after('reply_to_message_id');
            }

            if (! Schema::hasColumn('project_messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('reactions');
            }

            if (! Schema::hasColumn('project_messages', 'pinned_by_type')) {
                $table->string('pinned_by_type', 20)->nullable()->after('is_pinned');
            }

            if (! Schema::hasColumn('project_messages', 'pinned_by_id')) {
                $table->unsignedBigInteger('pinned_by_id')->nullable()->after('pinned_by_type');
            }

            if (! Schema::hasColumn('project_messages', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('pinned_by_id');
            }

            if (! Schema::hasIndex('project_messages', 'project_messages_project_id_is_pinned_index')) {
                $table->index(['project_id', 'is_pinned']);
            }

            if (! Schema::hasIndex('project_messages', 'project_messages_pinned_by_type_pinned_by_id_index')) {
                $table->index(['pinned_by_type', 'pinned_by_id']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_messages')) {
            return;
        }

        Schema::table('project_messages', function (Blueprint $table) {
            if (Schema::hasIndex('project_messages', 'project_messages_project_id_is_pinned_index')) {
                $table->dropIndex(['project_id', 'is_pinned']);
            }

            if (Schema::hasIndex('project_messages', 'project_messages_pinned_by_type_pinned_by_id_index')) {
                $table->dropIndex(['pinned_by_type', 'pinned_by_id']);
            }

            if (Schema::hasColumn('project_messages', 'reply_to_message_id')) {
                $table->dropConstrainedForeignId('reply_to_message_id');
            }

            $dropColumns = [];
            foreach (['reactions', 'is_pinned', 'pinned_by_type', 'pinned_by_id', 'pinned_at'] as $column) {
                if (Schema::hasColumn('project_messages', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
