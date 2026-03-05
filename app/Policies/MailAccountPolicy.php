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

        return MailAccountAssignment::query()
            ->where('mail_account_id', $mailAccount->id)
            ->where('assignee_type', $assigneeType)
            ->where('assignee_id', $assigneeId)
            ->where('can_read', true)
            ->exists();
    }
}
