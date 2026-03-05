<?php

namespace App\Policies;

use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\User;

class MailAccountPolicy
{
    public function use(User $user, MailAccount $mailAccount, string $assigneeType, int $assigneeId): bool
    {
        if ($user->isAdmin() && (bool) config('apptimatic_email.allow_admin_global_mailboxes', false)) {
            return true;
        }

        $candidateTypes = $this->candidateAssigneeTypes($user, $assigneeType);

        return MailAccountAssignment::query()
            ->where('mail_account_id', $mailAccount->id)
            ->whereIn('assignee_type', $candidateTypes)
            ->where('assignee_id', $assigneeId)
            ->where('can_read', true)
            ->exists();
    }

    /**
     * Support users may be assigned as either "user" (admin portal) or "support" (support portal).
     */
    private function candidateAssigneeTypes(User $user, string $assigneeType): array
    {
        $types = [strtolower(trim($assigneeType))];

        if ($user->isSupport()) {
            $types[] = 'support';
            $types[] = 'user';
        }

        return array_values(array_unique(array_filter($types)));
    }
}
