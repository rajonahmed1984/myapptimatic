<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Illuminate\Support\Carbon;
use Throwable;

class ImapInboxService
{
    public function isAvailable(): bool
    {
        return function_exists('imap_open');
    }

    public function inbox(MailAccount $account, string $password, int $limit = 50): array
    {
        $stream = $this->openStream($account, $password);
        if (! $stream) {
            return [];
        }

        try {
            $uids = $this->latestUids($stream, $limit);
            $messages = [];

            foreach ($uids as $uid) {
                $overview = $this->overviewByUid($stream, $uid);
                if (! $overview) {
                    continue;
                }

                $subject = $this->decodeMimeHeader((string) ($overview->subject ?? '(No subject)'));
                $from = $this->parseFrom((string) ($overview->from ?? 'Unknown sender'));
                $receivedAt = $this->parseDate((string) ($overview->date ?? ''));
                $snippet = $this->buildSnippet($this->bodyByUid($stream, $uid), $subject);

                $messages[] = [
                    'id' => (string) $uid,
                    'thread_id' => $this->threadIdFromSubject($subject),
                    'sender_name' => $from['name'],
                    'sender_email' => $from['email'],
                    'to' => (string) ($account->email ?? ''),
                    'subject' => $subject,
                    'snippet' => $snippet,
                    'body' => $this->bodyByUid($stream, $uid),
                    'received_at' => $receivedAt,
                    'unread' => ! ((bool) ($overview->seen ?? false)),
                ];
            }

            usort($messages, fn (array $a, array $b) => $b['received_at']->getTimestamp() <=> $a['received_at']->getTimestamp());

            return $messages;
        } catch (Throwable) {
            return [];
        } finally {
            @imap_close($stream);
        }
    }

    public function threadFor(MailAccount $account, string $password, string $messageId, int $limit = 120): array
    {
        $inbox = $this->inbox($account, $password, max($limit, 50));
        if ($inbox === []) {
            return [];
        }

        $selected = collect($inbox)->first(fn (array $message) => (string) $message['id'] === (string) $messageId);
        if (! $selected) {
            return [];
        }

        $threadId = (string) ($selected['thread_id'] ?? '');
        if ($threadId === '') {
            return [$selected];
        }

        $thread = array_values(array_filter($inbox, fn (array $message) => (string) ($message['thread_id'] ?? '') === $threadId));
        usort($thread, fn (array $a, array $b) => $a['received_at']->getTimestamp() <=> $b['received_at']->getTimestamp());

        return $thread;
    }

    public function snapshotHash(MailAccount $account, string $password, int $limit = 40): string
    {
        $stream = $this->openStream($account, $password);
        if (! $stream) {
            return '';
        }

        try {
            $uids = $this->latestUids($stream, $limit);
            $rows = [];

            foreach ($uids as $uid) {
                $overview = $this->overviewByUid($stream, $uid);
                if (! $overview) {
                    continue;
                }

                $rows[] = [
                    'uid' => (int) $uid,
                    'subject' => (string) ($overview->subject ?? ''),
                    'date' => (string) ($overview->date ?? ''),
                    'seen' => (bool) ($overview->seen ?? false),
                    'deleted' => (bool) ($overview->deleted ?? false),
                ];
            }

            return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        } catch (Throwable) {
            return '';
        } finally {
            @imap_close($stream);
        }
    }

    private function openStream(MailAccount $account, string $password)
    {
        if (! $this->isAvailable() || $password === '') {
            return false;
        }

        $mailbox = $this->buildMailboxString($account);
        if ($mailbox === '') {
            return false;
        }

        try {
            return @imap_open($mailbox, $account->email, $password, 0, 1);
        } catch (Throwable) {
            return false;
        }
    }

    private function latestUids($stream, int $limit): array
    {
        $uids = @imap_sort($stream, SORTDATE, true, SE_UID, 'ALL');
        if (! is_array($uids)) {
            $messageNumbers = @imap_search($stream, 'ALL') ?: [];
            if (! is_array($messageNumbers)) {
                return [];
            }

            rsort($messageNumbers);
            $uids = [];
            foreach ($messageNumbers as $messageNumber) {
                $uid = @imap_uid($stream, (int) $messageNumber);
                if (is_int($uid) && $uid > 0) {
                    $uids[] = $uid;
                }
            }
        }

        $limit = max(1, min($limit, 200));
        return array_slice(array_map('intval', $uids), 0, $limit);
    }

    private function overviewByUid($stream, int $uid): ?object
    {
        $overview = @imap_fetch_overview($stream, (string) $uid, FT_UID);
        if (! is_array($overview) || ! isset($overview[0]) || ! is_object($overview[0])) {
            return null;
        }

        return $overview[0];
    }

    private function bodyByUid($stream, int $uid): string
    {
        $body = @imap_fetchbody($stream, (string) $uid, '1.1', FT_UID | FT_PEEK);
        if (! is_string($body) || trim($body) === '') {
            $body = @imap_fetchbody($stream, (string) $uid, '1', FT_UID | FT_PEEK);
        }

        if (! is_string($body) || trim($body) === '') {
            $body = @imap_body($stream, (string) $uid, FT_UID | FT_PEEK);
        }

        if (! is_string($body)) {
            return '';
        }

        $body = quoted_printable_decode($body);
        $decoded = @base64_decode($body, true);
        if (is_string($decoded) && $decoded !== '') {
            $body = $decoded;
        }

        return $this->cleanText($body);
    }

    private function cleanText(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }

        if (stripos($text, '<html') !== false || stripos($text, '<body') !== false) {
            $text = strip_tags($text);
        }

        $text = preg_replace("/\r\n|\r/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function buildSnippet(string $body, string $subject): string
    {
        $body = trim($body);
        if ($body === '') {
            return $subject;
        }

        $snippet = preg_replace('/\s+/', ' ', $body) ?? $body;
        return mb_substr($snippet, 0, 140);
    }

    private function decodeMimeHeader(string $value): string
    {
        $decoded = @imap_mime_header_decode($value);
        if (! is_array($decoded) || $decoded === []) {
            return trim($value) !== '' ? trim($value) : '(No subject)';
        }

        $parts = [];
        foreach ($decoded as $part) {
            if (is_object($part) && isset($part->text)) {
                $parts[] = (string) $part->text;
            }
        }

        $output = trim(implode('', $parts));
        return $output !== '' ? $output : '(No subject)';
    }

    private function parseFrom(string $from): array
    {
        $from = trim($from);

        if (preg_match('/^(.*)<(.+)>$/', $from, $matches) === 1) {
            $name = trim(trim($matches[1]), '"');
            $email = trim($matches[2]);

            return [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
            ];
        }

        return [
            'name' => $from !== '' ? $from : 'Unknown sender',
            'email' => '',
        ];
    }

    private function parseDate(string $date): Carbon
    {
        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return now();
        }
    }

    private function threadIdFromSubject(string $subject): string
    {
        $normalized = strtolower(trim($subject));
        $normalized = preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : 'thread-' . md5($subject);
    }

    private function buildMailboxString(MailAccount $account): string
    {
        $host = (string) ($account->imap_host ?: config('apptimatic_email.imap.host', ''));
        $port = (int) ($account->imap_port ?: config('apptimatic_email.imap.port', 993));
        $encryption = strtolower((string) ($account->imap_encryption ?: config('apptimatic_email.imap.encryption', 'ssl')));
        $validateCert = (bool) ($account->imap_validate_cert ?? config('apptimatic_email.imap.validate_cert', true));

        if ($host === '' || $port <= 0) {
            return '';
        }

        $flags = ['imap'];

        if ($encryption === 'ssl') {
            $flags[] = 'ssl';
        } elseif ($encryption === 'tls') {
            $flags[] = 'tls';
        }

        if (! $validateCert) {
            $flags[] = 'novalidate-cert';
        }

        return '{' . $host . ':' . $port . '/' . implode('/', $flags) . '}INBOX';
    }
}
