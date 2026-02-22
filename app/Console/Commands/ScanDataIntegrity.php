<?php

namespace App\Console\Commands;

use App\Models\AccountingEntry;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\EmployeePayout;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\FileReferenceReconciliation;
use App\Models\Income;
use App\Models\PayrollItem;
use App\Models\PayrollAuditLog;
use App\Models\PaymentProof;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectTask;
use App\Models\ProjectTaskActivity;
use App\Models\ProjectTaskMessage;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\SupportTicketReply;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ScanDataIntegrity extends Command
{
    protected $signature = 'diagnostics:integrity {--limit=10}';
    protected $description = 'Scan for common relational data integrity issues.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Integrity diagnostics are disabled in production.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));

        $rows = [];

        $rows[] = $this->summarize('Tasks without project', ProjectTask::query()->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Subtasks without task', ProjectTaskSubtask::query()->whereDoesntHave('task'), $limit);
        $rows[] = $this->summarize('Project messages without project', ProjectMessage::query()->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Task messages without task', ProjectTaskMessage::query()->whereDoesntHave('task'), $limit);

        $rows[] = $this->summarize('Invoices without customer', Invoice::query()->whereNull('customer_id'), $limit);
        $rows[] = $this->summarize('Invoices with missing project', Invoice::query()->whereNotNull('project_id')->whereDoesntHave('project'), $limit);
        $rows[] = $this->summarize('Invoices with missing customer record', Invoice::query()->whereNotNull('customer_id')->whereDoesntHave('customer'), $limit);

        $rows[] = $this->summarize('Payroll items without employee', PayrollItem::query()->whereDoesntHave('employee'), $limit);
        $rows[] = $this->summarize('Payroll items without period', PayrollItem::query()->whereDoesntHave('period'), $limit);

        $rows[] = $this->summarize('Commission payouts without sales rep', CommissionPayout::query()->whereDoesntHave('salesRep'), $limit);
        $rows[] = $this->summarize('Commission payouts without earnings', CommissionPayout::query()->whereDoesntHave('earnings'), $limit);

        $rows[] = $this->summarize('Employee payouts without employee', EmployeePayout::query()->whereDoesntHave('employee'), $limit);

        $rows[] = $this->summarize('Accounting entries missing references', AccountingEntry::query()
            ->whereNull('reference')
            ->whereNull('customer_id')
            ->whereNull('invoice_id')
            ->whereNull('payment_gateway_id'), $limit);

        $rows[] = $this->summarize('Expense invoices with invalid source type', ExpenseInvoice::query()
            ->whereNotIn('source_type', ['expense', 'payroll_item', 'employee_payout', 'commission_payout']), $limit);

        $rows[] = $this->summarize('Expense invoices missing expense source', ExpenseInvoice::query()
            ->where('source_type', 'expense')
            ->whereNotIn('source_id', Expense::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing payroll item', ExpenseInvoice::query()
            ->where('source_type', 'payroll_item')
            ->whereNotIn('source_id', PayrollItem::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing employee payout', ExpenseInvoice::query()
            ->where('source_type', 'employee_payout')
            ->whereNotIn('source_id', EmployeePayout::query()->select('id')), $limit);

        $rows[] = $this->summarize('Expense invoices missing commission payout', ExpenseInvoice::query()
            ->where('source_type', 'commission_payout')
            ->whereNotIn('source_id', CommissionPayout::query()->select('id')), $limit);

        $rows[] = $this->summarizeMissingFiles('Task activities with missing attachments', ProjectTaskActivity::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Project chat messages with missing attachments', ProjectMessage::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Task chat messages with missing attachments', ProjectTaskMessage::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Task subtasks with missing attachments', ProjectTaskSubtask::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Support ticket replies with missing attachments', SupportTicketReply::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Income entries with missing attachments', Income::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Expenses with missing attachments', Expense::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Payment proofs with missing attachments', PaymentProof::query()
            ->whereNotNull('attachment_path')
            ->where('attachment_path', '!=', ''), 'attachment_path', $limit);
        $rows[] = $this->summarizeMissingMetadataFiles('Employee payouts with missing payment proofs', EmployeePayout::query()
            ->whereNotNull('metadata'), 'metadata', 'payment_proof_path', $limit);
        $rows[] = $this->summarizeMissingMetadataFiles('Payroll logs with missing payment proofs', PayrollAuditLog::query()
            ->whereNotNull('meta'), 'meta', 'proof_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Projects with missing contract files', Project::query()
            ->whereNotNull('contract_file_path')
            ->where('contract_file_path', '!=', ''), 'contract_file_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Projects with missing proposal files', Project::query()
            ->whereNotNull('proposal_file_path')
            ->where('proposal_file_path', '!=', ''), 'proposal_file_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Users with missing avatar files', User::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', ''), 'avatar_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Users with missing NID files', User::query()
            ->whereNotNull('nid_path')
            ->where('nid_path', '!=', ''), 'nid_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Users with missing CV files', User::query()
            ->whereNotNull('cv_path')
            ->where('cv_path', '!=', ''), 'cv_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Employees with missing photo files', Employee::query()
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', ''), 'photo_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Employees with missing NID files', Employee::query()
            ->whereNotNull('nid_path')
            ->where('nid_path', '!=', ''), 'nid_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Employees with missing CV files', Employee::query()
            ->whereNotNull('cv_path')
            ->where('cv_path', '!=', ''), 'cv_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Customers with missing avatar files', Customer::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', ''), 'avatar_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Customers with missing NID files', Customer::query()
            ->whereNotNull('nid_path')
            ->where('nid_path', '!=', ''), 'nid_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Customers with missing CV files', Customer::query()
            ->whereNotNull('cv_path')
            ->where('cv_path', '!=', ''), 'cv_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Sales reps with missing avatar files', SalesRepresentative::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', ''), 'avatar_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Sales reps with missing NID files', SalesRepresentative::query()
            ->whereNotNull('nid_path')
            ->where('nid_path', '!=', ''), 'nid_path', $limit);
        $rows[] = $this->summarizeMissingFiles('Sales reps with missing CV files', SalesRepresentative::query()
            ->whereNotNull('cv_path')
            ->where('cv_path', '!=', ''), 'cv_path', $limit);

        $this->table(['Check', 'Count', 'Sample IDs'], $rows);

        return self::SUCCESS;
    }

    private function summarize(string $label, Builder $query, int $limit): array
    {
        $count = (clone $query)->count();
        $sample = (clone $query)->limit($limit)->pluck('id')->map(fn ($id) => (string) $id)->implode(', ');

        return [$label, $count, $sample ?: '--'];
    }

    private function summarizeMissingFiles(string $label, Builder $query, string $column, int $limit): array
    {
        $disk = Storage::disk('public');
        $missingCount = 0;
        $sampleIds = [];

        (clone $query)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$missingCount, &$sampleIds, $limit, $column, $disk): void {
                foreach ($rows as $row) {
                    $path = trim((string) ($row->{$column} ?? ''));
                    if ($path === '') {
                        continue;
                    }

                    if (! $this->pathExists($disk, $path)) {
                        if ($this->isReconciled($row, $column, '', $path)) {
                            continue;
                        }

                        $missingCount++;
                        if (count($sampleIds) < $limit) {
                            $sampleIds[] = (string) $row->id;
                        }
                    }
                }
            });

        return [$label, $missingCount, empty($sampleIds) ? '--' : implode(', ', $sampleIds)];
    }

    private function pathExists($disk, string $path): bool
    {
        $normalized = ltrim($path, '/');
        if ($disk->exists($normalized)) {
            return true;
        }

        if (str_starts_with($normalized, 'storage/')) {
            $trimmed = substr($normalized, 8);
            if ($trimmed !== false && $disk->exists($trimmed)) {
                return true;
            }
        }

        if (str_starts_with($normalized, 'public/')) {
            $trimmed = substr($normalized, 7);
            if ($trimmed !== false && $disk->exists($trimmed)) {
                return true;
            }
        }

        return false;
    }

    private function summarizeMissingMetadataFiles(string $label, Builder $query, string $column, string $pathKey, int $limit): array
    {
        $disk = Storage::disk('public');
        $missingCount = 0;
        $sampleIds = [];

        (clone $query)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$missingCount, &$sampleIds, $limit, $column, $pathKey, $disk): void {
                foreach ($rows as $row) {
                    $payload = $row->{$column};
                    if (! is_array($payload)) {
                        continue;
                    }

                    $path = trim((string) ($payload[$pathKey] ?? ''));
                    if ($path === '') {
                        continue;
                    }

                    if (! $this->pathExists($disk, $path)) {
                        if ($this->isReconciled($row, $column, $pathKey, $path)) {
                            continue;
                        }

                        $missingCount++;
                        if (count($sampleIds) < $limit) {
                            $sampleIds[] = (string) $row->id;
                        }
                    }
                }
            });

        return [$label, $missingCount, empty($sampleIds) ? '--' : implode(', ', $sampleIds)];
    }

    private function isReconciled(Model $model, string $column, string $metadataKey, string $path): bool
    {
        $normalizedPath = ltrim(trim($path), '/');
        if ($normalizedPath === '') {
            return false;
        }

        return FileReferenceReconciliation::query()
            ->where('model_type', $model::class)
            ->where('model_id', (int) $model->getKey())
            ->where('column_name', $column)
            ->where('metadata_key', $metadataKey)
            ->where('path_hash', sha1($normalizedPath))
            ->where('status', 'missing')
            ->exists();
    }
}
