<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\FileReferenceReconciliation;
use App\Models\Income;
use App\Models\PayrollAuditLog;
use App\Models\PaymentProof;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectTaskActivity;
use App\Models\ProjectTaskMessage;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ReconcileMissingFileReferences extends Command
{
    protected $signature = 'diagnostics:reconcile-missing-files
        {--limit=0 : Maximum number of missing records to reconcile per check (0 = no limit)}
        {--nullify : Also clear broken file references after flagging them}';

    protected $description = 'Flag missing file references safely for integrity diagnostics and optional cleanup.';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Missing-file reconciliation is disabled in production.');
            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));
        $nullify = (bool) $this->option('nullify');
        $disk = Storage::disk('public');
        $rows = [];

        $rows[] = $this->reconcileColumn(
            'Task activities attachments',
            ProjectTaskActivity::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Project chat attachments',
            ProjectMessage::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Task chat attachments',
            ProjectTaskMessage::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Task subtask attachments',
            ProjectTaskSubtask::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Support ticket reply attachments',
            SupportTicketReply::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Income attachments',
            Income::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Expense attachments',
            Expense::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Payment proof attachments',
            PaymentProof::query()->whereNotNull('attachment_path')->where('attachment_path', '!=', ''),
            'attachment_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileMetadata(
            'Employee payout payment proofs',
            EmployeePayout::query()->whereNotNull('metadata'),
            'metadata',
            'payment_proof_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileMetadata(
            'Payroll log payment proofs',
            PayrollAuditLog::query()->whereNotNull('meta'),
            'meta',
            'proof_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Project contract files',
            Project::query()->whereNotNull('contract_file_path')->where('contract_file_path', '!=', ''),
            'contract_file_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Project proposal files',
            Project::query()->whereNotNull('proposal_file_path')->where('proposal_file_path', '!=', ''),
            'proposal_file_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'User avatar files',
            User::query()->whereNotNull('avatar_path')->where('avatar_path', '!=', ''),
            'avatar_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'User NID files',
            User::query()->whereNotNull('nid_path')->where('nid_path', '!=', ''),
            'nid_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'User CV files',
            User::query()->whereNotNull('cv_path')->where('cv_path', '!=', ''),
            'cv_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Employee photo files',
            Employee::query()->whereNotNull('photo_path')->where('photo_path', '!=', ''),
            'photo_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Employee NID files',
            Employee::query()->whereNotNull('nid_path')->where('nid_path', '!=', ''),
            'nid_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Employee CV files',
            Employee::query()->whereNotNull('cv_path')->where('cv_path', '!=', ''),
            'cv_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Customer avatar files',
            Customer::query()->whereNotNull('avatar_path')->where('avatar_path', '!=', ''),
            'avatar_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Customer NID files',
            Customer::query()->whereNotNull('nid_path')->where('nid_path', '!=', ''),
            'nid_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Customer CV files',
            Customer::query()->whereNotNull('cv_path')->where('cv_path', '!=', ''),
            'cv_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Sales rep avatar files',
            SalesRepresentative::query()->whereNotNull('avatar_path')->where('avatar_path', '!=', ''),
            'avatar_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Sales rep NID files',
            SalesRepresentative::query()->whereNotNull('nid_path')->where('nid_path', '!=', ''),
            'nid_path',
            $limit,
            $nullify,
            $disk
        );
        $rows[] = $this->reconcileColumn(
            'Sales rep CV files',
            SalesRepresentative::query()->whereNotNull('cv_path')->where('cv_path', '!=', ''),
            'cv_path',
            $limit,
            $nullify,
            $disk
        );

        $this->table(['Check', 'Flagged', 'Nullified'], $rows);

        $flaggedTotal = array_sum(array_map(static fn (array $row): int => $row[1], $rows));
        $nullifiedTotal = array_sum(array_map(static fn (array $row): int => $row[2], $rows));

        $this->info("Missing-file reconciliation complete. Flagged {$flaggedTotal} reference(s), nullified {$nullifiedTotal} reference(s).");

        return self::SUCCESS;
    }

    private function reconcileColumn(
        string $label,
        Builder $query,
        string $column,
        int $limit,
        bool $nullify,
        $disk
    ): array {
        $flagged = 0;
        $nullified = 0;
        $scannedMissing = 0;

        (clone $query)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (
                &$flagged,
                &$nullified,
                &$scannedMissing,
                $limit,
                $nullify,
                $column,
                $disk
            ) {
                foreach ($rows as $row) {
                    $path = trim((string) ($row->{$column} ?? ''));
                    if ($path === '' || $this->pathExists($disk, $path)) {
                        continue;
                    }

                    if ($limit > 0 && $scannedMissing >= $limit) {
                        return false;
                    }

                    $scannedMissing++;

                    if ($this->recordMissingReference($row, $column, '', $path, $nullify ? 'nullified' : 'flagged')) {
                        $flagged++;
                    }

                    if ($nullify && $row->{$column} !== null) {
                        $row->{$column} = null;
                        $row->save();
                        $nullified++;
                    }
                }

                return null;
            });

        return [$label, $flagged, $nullified];
    }

    private function reconcileMetadata(
        string $label,
        Builder $query,
        string $column,
        string $pathKey,
        int $limit,
        bool $nullify,
        $disk
    ): array {
        $flagged = 0;
        $nullified = 0;
        $scannedMissing = 0;

        (clone $query)
            ->select(['id', $column])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (
                &$flagged,
                &$nullified,
                &$scannedMissing,
                $limit,
                $nullify,
                $column,
                $pathKey,
                $disk
            ) {
                foreach ($rows as $row) {
                    $payload = $row->{$column};
                    if (! is_array($payload)) {
                        continue;
                    }

                    $path = trim((string) ($payload[$pathKey] ?? ''));
                    if ($path === '' || $this->pathExists($disk, $path)) {
                        continue;
                    }

                    if ($limit > 0 && $scannedMissing >= $limit) {
                        return false;
                    }

                    $scannedMissing++;

                    if ($this->recordMissingReference($row, $column, $pathKey, $path, $nullify ? 'nullified' : 'flagged')) {
                        $flagged++;
                    }

                    if ($nullify) {
                        $payload[$pathKey] = null;
                        $row->{$column} = $payload;
                        $row->save();
                        $nullified++;
                    }
                }

                return null;
            });

        return [$label, $flagged, $nullified];
    }

    private function recordMissingReference(
        Model $model,
        string $column,
        string $metadataKey,
        string $path,
        string $action
    ): bool {
        $normalizedPath = ltrim(trim($path), '/');
        $pathHash = sha1($normalizedPath);

        $record = FileReferenceReconciliation::query()->firstOrNew([
            'model_type' => $model::class,
            'model_id' => (int) $model->getKey(),
            'column_name' => $column,
            'metadata_key' => $metadataKey,
            'path_hash' => $pathHash,
        ]);

        $isNew = ! $record->exists;

        $record->original_path = $normalizedPath;
        $record->status = 'missing';
        $record->action = $action;
        $record->context = ['disk' => 'public'];
        $record->reconciled_at = now();
        $record->save();

        return $isNew;
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
}
