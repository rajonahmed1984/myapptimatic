<?php

namespace App\Services\Mail;

use App\Models\Employee;
use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\SalesRepresentative;
use App\Models\User;

class MailBootstrapService
{
    public function bootstrap(array $mailboxes, array $assignments, bool $dryRun = false): array
    {
        $stats = [
            'mailboxes_created' => 0,
            'mailboxes_updated' => 0,
            'assignments_created' => 0,
            'assignments_updated' => 0,
            'warnings' => [],
        ];

        $knownMailboxIds = [];

        foreach ($mailboxes as $index => $definition) {
            if (! is_array($definition)) {
                $stats['warnings'][] = "Mailbox definition at index {$index} is invalid.";
                continue;
            }

            $email = strtolower(trim((string) ($definition['email'] ?? '')));
            if ($email === '') {
                $stats['warnings'][] = "Mailbox definition at index {$index} is missing an email.";
                continue;
            }

            [$mailbox, $created] = $this->upsertMailbox($definition, $email, $dryRun);

            if ($mailbox) {
                $knownMailboxIds[$mailbox->email] = (int) $mailbox->id;
            }

            if ($created) {
                $stats['mailboxes_created']++;
            } else {
                $stats['mailboxes_updated']++;
            }
        }

        foreach ($assignments as $index => $definition) {
            if (! is_array($definition)) {
                $stats['warnings'][] = "Assignment definition at index {$index} is invalid.";
                continue;
            }

            $mailboxEmail = strtolower(trim((string) ($definition['mailbox_email'] ?? $definition['mailbox'] ?? '')));
            if ($mailboxEmail === '') {
                $stats['warnings'][] = "Assignment at index {$index} is missing mailbox_email.";
                continue;
            }

            $mailboxId = $knownMailboxIds[$mailboxEmail] ?? (int) MailAccount::query()
                ->whereRaw('LOWER(email) = ?', [$mailboxEmail])
                ->value('id');

            if ($mailboxId <= 0) {
                $stats['warnings'][] = "Assignment at index {$index} references unknown mailbox {$mailboxEmail}.";
                continue;
            }

            $actor = $this->resolveActor($definition);
            if (! $actor) {
                $stats['warnings'][] = "Assignment at index {$index} has no resolvable actor.";
                continue;
            }

            $canRead = (bool) ($definition['can_read'] ?? true);
            $canManage = (bool) ($definition['can_manage'] ?? false);

            [$assignment, $created] = $this->upsertAssignment($mailboxId, $actor, $canRead, $canManage, $dryRun);
            if (! $assignment) {
                $stats['warnings'][] = "Assignment at index {$index} could not be created.";
                continue;
            }

            if ($created) {
                $stats['assignments_created']++;
            } else {
                $stats['assignments_updated']++;
            }
        }

        return $stats;
    }

    private function upsertMailbox(array $definition, string $email, bool $dryRun): array
    {
        $attributes = [
            'display_name' => $this->nullableString($definition['display_name'] ?? null),
            'imap_host' => $this->nullableString($definition['imap_host'] ?? config('apptimatic_email.imap.host')),
            'imap_port' => $definition['imap_port'] ?? config('apptimatic_email.imap.port', 993),
            'imap_encryption' => $this->nullableString($definition['imap_encryption'] ?? config('apptimatic_email.imap.encryption', 'ssl')),
            'imap_validate_cert' => (bool) ($definition['imap_validate_cert'] ?? config('apptimatic_email.imap.validate_cert', true)),
            'status' => $this->nullableString($definition['status'] ?? 'active') ?: 'active',
        ];

        $existing = MailAccount::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($dryRun) {
            if ($existing) {
                $existing->fill($attributes);
                return [$existing, false];
            }

            $model = new MailAccount(array_merge(['email' => $email], $attributes));
            $model->id = 0;

            return [$model, true];
        }

        $mailbox = MailAccount::query()->updateOrCreate(
            ['email' => $email],
            $attributes
        );

        return [$mailbox, ! $existing];
    }

    private function upsertAssignment(int $mailboxId, array $actor, bool $canRead, bool $canManage, bool $dryRun): array
    {
        $lookup = [
            'mail_account_id' => $mailboxId,
            'assignee_type' => $actor['type'],
            'assignee_id' => $actor['id'],
        ];

        $values = [
            'can_read' => $canRead,
            'can_manage' => $canManage,
        ];

        $existing = MailAccountAssignment::query()->where($lookup)->first();

        if ($dryRun) {
            if ($existing) {
                $existing->fill($values);
                return [$existing, false];
            }

            return [new MailAccountAssignment(array_merge($lookup, $values)), true];
        }

        $assignment = MailAccountAssignment::query()->updateOrCreate($lookup, $values);

        return [$assignment, ! $existing];
    }

    private function resolveActor(array $definition): ?array
    {
        $actor = $definition['actor'] ?? [];
        if (! is_array($actor)) {
            return null;
        }

        $type = strtolower(trim((string) ($actor['type'] ?? '')));
        $id = isset($actor['id']) ? (int) $actor['id'] : 0;

        if ($type === '' || ! in_array($type, ['user', 'support', 'employee', 'sales_rep'], true)) {
            return null;
        }

        if ($id > 0) {
            return ['type' => $type, 'id' => $id];
        }

        $email = strtolower(trim((string) ($actor['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return null;
        }

        if ($type === 'user') {
            return ['type' => 'user', 'id' => (int) $user->id];
        }

        if ($type === 'support') {
            return ['type' => 'support', 'id' => (int) $user->id];
        }

        if ($type === 'employee') {
            $employee = Employee::query()->where('user_id', $user->id)->first();
            return $employee ? ['type' => 'employee', 'id' => (int) $employee->id] : null;
        }

        $salesRep = SalesRepresentative::query()->where('user_id', $user->id)->first();

        return $salesRep ? ['type' => 'sales_rep', 'id' => (int) $salesRep->id] : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
