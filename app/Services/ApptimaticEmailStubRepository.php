<?php

namespace App\Services;

class ApptimaticEmailStubRepository
{
    public function inbox(): array
    {
        $messages = $this->seed();

        usort($messages, function (array $a, array $b): int {
            return $b['received_at']->getTimestamp() <=> $a['received_at']->getTimestamp();
        });

        return $messages;
    }

    public function unreadCount(): int
    {
        return count(array_filter($this->seed(), fn (array $message) => (bool) ($message['unread'] ?? false)));
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
        $thread = array_values(array_filter($this->seed(), function (array $message) use ($threadId): bool {
            return (string) ($message['thread_id'] ?? '') === $threadId;
        }));

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
                'sender_name' => 'Shafiq Hasan',
                'sender_email' => 'shafiq.hasan@apptimatic.com',
                'to' => 'support@apptimatic.com',
                'subject' => 'Customer escalation summary',
                'snippet' => 'Escalation call is done. Notes attached for internal follow-up.',
                'body' => "Team,\n\nEscalation call completed. Customer is aligned with the remediation timeline. Please keep responses under 2 hours during business time.\n\nThanks,\nShafiq",
                'received_at' => $now->copy()->subDay()->subMinutes(25),
                'unread' => false,
            ],
        ];
    }
}
