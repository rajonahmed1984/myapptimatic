<?php

namespace App\Services;

class ApptimaticEmailStubRepository
{
    public function inbox(): array
    {
        return $this->folder('inbox');
    }

    public function folder(string $folder = 'inbox'): array
    {
        $folder = $this->normalizeFolder($folder);
        $messages = $this->seed();
        $messages = array_values(array_filter($messages, fn (array $message): bool => (string) ($message['folder'] ?? 'inbox') === $folder));

        usort($messages, function (array $a, array $b): int {
            return $b['received_at']->getTimestamp() <=> $a['received_at']->getTimestamp();
        });

        return $messages;
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->folder('inbox'), fn (array $message) => (bool) ($message['unread'] ?? false)));
    }

    public function find(string $id): ?array
    {
        foreach ($this->seed() as $message) {
            if ((string) ($message['id'] ?? '') === $id) {
                return $message;
            }
        }

        return null;
    }

    public function threadFor(string $messageId): array
    {
        $selectedMessage = $this->find($messageId);
        if (! $selectedMessage) {
            return [];
        }

        $threadId = (string) ($selectedMessage['thread_id'] ?? '');
        $folder = (string) ($selectedMessage['folder'] ?? 'inbox');
        $thread = array_values(array_filter($this->seed(), function (array $message) use ($threadId): bool {
            return (string) ($message['thread_id'] ?? '') === $threadId;
        }));
        $thread = array_values(array_filter($thread, fn (array $message): bool => (string) ($message['folder'] ?? 'inbox') === $folder));

        usort($thread, function (array $a, array $b): int {
            return $a['received_at']->getTimestamp() <=> $b['received_at']->getTimestamp();
        });

        return $thread;
    }

    private function seed(): array
    {
        $now = now();

        return [
            [
                'id' => 'm-1001',
                'thread_id' => 't-9001',
                'folder' => 'inbox',
                'sender_name' => 'Maya Patel',
                'sender_email' => 'maya.patel@apptimatic.com',
                'to' => 'support@apptimatic.com',
                'subject' => 'Payment follow-up for INV-10089',
                'snippet' => 'Client confirmed transfer details and asked for expected confirmation time.',
                'body' => "Hi Support,\n\nThe client confirmed bank transfer details for INV-10089. Please review the proof and confirm once it is reflected.\n\nThanks,\nMaya",
                'received_at' => $now->copy()->subMinutes(18),
                'unread' => true,
            ],
            [
                'id' => 'm-1002',
                'thread_id' => 't-9001',
                'folder' => 'inbox',
                'sender_name' => 'Support Team',
                'sender_email' => 'support@apptimatic.com',
                'to' => 'maya.patel@apptimatic.com',
                'subject' => 'Re: Payment follow-up for INV-10089',
                'snippet' => 'We validated transaction metadata and are waiting for ledger sync to complete.',
                'body' => "Hi Maya,\n\nThanks for the update. We validated the metadata and are waiting for finance ledger sync to complete. We will notify once done.\n\nRegards,\nSupport",
                'received_at' => $now->copy()->subMinutes(11),
                'unread' => false,
            ],
            [
                'id' => 'm-1003',
                'thread_id' => 't-9002',
                'folder' => 'inbox',
                'sender_name' => 'Nabila Rahman',
                'sender_email' => 'nabila.rahman@apptimatic.com',
                'to' => 'support@apptimatic.com',
                'subject' => 'DNS propagation check for apptimatic.com',
                'snippet' => 'Requesting a verification pass before client handoff.',
                'body' => "Hello Team,\n\nCan you run one more DNS propagation verification before final client handoff?\n\nRegards,\nNabila",
                'received_at' => $now->copy()->subHours(2)->subMinutes(5),
                'unread' => true,
            ],
            [
                'id' => 'm-1004',
                'thread_id' => 't-9003',
                'folder' => 'inbox',
                'sender_name' => 'Billing Bot',
                'sender_email' => 'billing@apptimatic.com',
                'to' => 'support@apptimatic.com',
                'subject' => 'Daily reconciliation digest',
                'snippet' => '19 invoices matched, 2 need manual verification.',
                'body' => "Daily summary:\n- Matched invoices: 19\n- Pending manual verification: 2\n\nPlease review the unmatched rows in admin finance.",
                'received_at' => $now->copy()->subHours(5)->subMinutes(40),
                'unread' => false,
            ],
            [
                'id' => 'm-1005',
                'thread_id' => 't-9004',
                'folder' => 'inbox',
                'sender_name' => 'Shafiq Hasan',
                'sender_email' => 'shafiq.hasan@apptimatic.com',
                'to' => 'support@apptimatic.com',
                'subject' => 'Customer escalation summary',
                'snippet' => 'Escalation call is done. Notes attached for internal follow-up.',
                'body' => "Team,\n\nEscalation call completed. Customer is aligned with the remediation timeline. Please keep responses under 2 hours during business time.\n\nThanks,\nShafiq",
                'received_at' => $now->copy()->subDay()->subMinutes(25),
                'unread' => false,
            ],
            [
                'id' => 'm-2001',
                'thread_id' => 't-9101',
                'folder' => 'sent',
                'sender_name' => 'Master Admin',
                'sender_email' => 'admin@apptimatic.com',
                'to' => 'client@apptimatic.com',
                'subject' => 'Proposal follow-up',
                'snippet' => 'Sharing the updated commercial terms for your review.',
                'body' => "Hello,\n\nPlease find the updated terms attached. Let me know your feedback.\n\nRegards,\nAdmin",
                'received_at' => $now->copy()->subHours(6),
                'unread' => false,
            ],
            [
                'id' => 'm-3001',
                'thread_id' => 't-9201',
                'folder' => 'drafts',
                'sender_name' => 'Master Admin',
                'sender_email' => 'admin@apptimatic.com',
                'to' => 'ops@apptimatic.com',
                'subject' => 'Monthly operations note (draft)',
                'snippet' => 'Draft saved with pending details...',
                'body' => "Draft:\n- Metrics\n- Risks\n- Action points",
                'received_at' => $now->copy()->subHours(3),
                'unread' => false,
            ],
            [
                'id' => 'm-4001',
                'thread_id' => 't-9301',
                'folder' => 'spam',
                'sender_name' => 'Random Sender',
                'sender_email' => 'offer@unknown.biz',
                'to' => 'admin@apptimatic.com',
                'subject' => 'Congrats! You won',
                'snippet' => 'Click now to claim reward.',
                'body' => "Suspicious message body",
                'received_at' => $now->copy()->subHours(10),
                'unread' => true,
            ],
            [
                'id' => 'm-5001',
                'thread_id' => 't-9401',
                'folder' => 'trash',
                'sender_name' => 'Old Thread',
                'sender_email' => 'old@apptimatic.com',
                'to' => 'admin@apptimatic.com',
                'subject' => 'Deprecated note',
                'snippet' => 'Moved to trash.',
                'body' => "This message is in trash.",
                'received_at' => $now->copy()->subDays(2),
                'unread' => false,
            ],
        ];
    }

    private function normalizeFolder(string $folder): string
    {
        $value = strtolower(trim($folder));
        $allowed = ['inbox', 'sent', 'drafts', 'spam', 'trash'];

        return in_array($value, $allowed, true) ? $value : 'inbox';
    }
}
