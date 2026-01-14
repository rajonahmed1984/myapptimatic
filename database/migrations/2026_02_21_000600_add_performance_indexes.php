<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('invoices', 'idx_invoices_status_due_date', ['status', 'due_date']);
        $this->addIndex('invoices', 'idx_invoices_status_paid_at', ['status', 'paid_at']);
        $this->addIndex('invoices', 'idx_invoices_subscription_id', ['subscription_id']);
        $this->addIndex('invoices', 'idx_invoices_project_id', ['project_id']);

        $this->addIndex('subscriptions', 'idx_subscriptions_status_next_invoice_at', ['status', 'next_invoice_at']);

        $this->addIndex('project_tasks', 'idx_project_tasks_project_status', ['project_id', 'status']);
        $this->addIndex('project_tasks', 'idx_project_tasks_project_customer_visible', ['project_id', 'customer_visible']);

        $this->addIndex('project_task_activities', 'idx_pta_task_created_at', ['project_task_id', 'created_at']);
        $this->addIndex('project_task_messages', 'idx_ptm_task_created_at', ['project_task_id', 'created_at']);

        $this->addIndex('accounting_entries', 'idx_accounting_type_entry_date', ['type', 'entry_date']);

        $this->addIndex('licenses', 'idx_licenses_status_expires_at', ['status', 'expires_at']);
        $this->addIndex('license_domains', 'idx_license_domains_license_status', ['license_id', 'status']);
    }

    public function down(): void
    {
        $this->dropIndex('invoices', 'idx_invoices_status_due_date');
        $this->dropIndex('invoices', 'idx_invoices_status_paid_at');
        $this->dropIndex('invoices', 'idx_invoices_subscription_id');
        $this->dropIndex('invoices', 'idx_invoices_project_id');

        $this->dropIndex('subscriptions', 'idx_subscriptions_status_next_invoice_at');

        $this->dropIndex('project_tasks', 'idx_project_tasks_project_status');
        $this->dropIndex('project_tasks', 'idx_project_tasks_project_customer_visible');

        $this->dropIndex('project_task_activities', 'idx_pta_task_created_at');
        $this->dropIndex('project_task_messages', 'idx_ptm_task_created_at');

        $this->dropIndex('accounting_entries', 'idx_accounting_type_entry_date');

        $this->dropIndex('licenses', 'idx_licenses_status_expires_at');
        $this->dropIndex('license_domains', 'idx_license_domains_license_status');
    }

    private function addIndex(string $table, string $indexName, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, function (Blueprint $schema) use ($columns, $indexName) {
                $schema->index($columns, $indexName);
            });
        } catch (\Throwable) {
        }
    }

    private function dropIndex(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $schema) use ($indexName) {
                $schema->dropIndex($indexName);
            });
        } catch (\Throwable) {
        }
    }
};
