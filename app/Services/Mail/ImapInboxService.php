<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Throwable;

class ImapInboxService
{
    private const FOLDER_ALIASES = [
        'inbox' => ['INBOX'],
        'sent' => ['INBOX.Sent', 'Sent', 'Sent Items', 'INBOX.Sent Items', '[Gmail]/Sent Mail'],
        'drafts' => ['INBOX.Drafts', 'Drafts', '[Gmail]/Drafts'],
        'spam' => ['INBOX.Spam', 'INBOX.Junk', 'Spam', 'Junk', 'Junk E-mail', '[Gmail]/Spam'],
        'trash' => ['INBOX.Trash', 'Trash', 'Deleted Items', '[Gmail]/Trash'],
    ];

    /**
     * @var array<string, string>
     */
    private array $folderCache = [];

    public function isAvailable(): bool
    {
        return function_exists('imap_open') || function_exists('stream_socket_client');
    }

    public function inbox(MailAccount $account, string $password, int $limit = 50): array
    {
        return $this->folder($account, $password, 'inbox', $limit);
    }

    public function folder(MailAccount $account, string $password, string $folder = 'inbox', int $limit = 50): array
    {
        $folder = $this->normalizeFolderKey($folder);
        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return $this->fetchViaSocket($account, $password, $folder, $limit);
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
                $content = $this->messageContentByUid($stream, $uid);
                $plainBody = (string) ($content['text'] ?? '');
                $htmlBody = (string) ($content['html'] ?? '');
                $snippet = $this->buildSnippet($plainBody !== '' ? $plainBody : $this->cleanText($htmlBody), $subject);
                $attachments = is_array($content['attachments'] ?? null) ? $content['attachments'] : [];

                $messages[] = [
                    'id' => (string) $uid,
                    'thread_id' => $this->threadIdFromSubject($subject),
                    'folder' => $folder,
                    'sender_name' => $from['name'],
                    'sender_email' => $from['email'],
                    'to' => (string) ($account->email ?? ''),
                    'subject' => $subject,
                    'snippet' => $snippet,
                    'body' => $plainBody,
                    'body_html' => $htmlBody,
                    'attachments' => $attachments,
                    'has_attachments' => count($attachments) > 0,
                    'received_at' => $receivedAt,
                    'unread' => ! ((bool) ($overview->seen ?? false)),
                ];
            }

            usort($messages, fn (array $a, array $b) => $b['received_at']->getTimestamp() <=> $a['received_at']->getTimestamp());

            return $messages;
        } catch (Throwable) {
            return $this->fetchViaSocket($account, $password, $folder, $limit);
        } finally {
            @imap_close($stream);
        }
    }

    public function threadFor(MailAccount $account, string $password, string $messageId, int $limit = 120, string $folder = 'inbox'): array
    {
        $inbox = $this->folder($account, $password, $folder, max($limit, 50));
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

    public function snapshotHash(MailAccount $account, string $password, int $limit = 40, string $folder = 'inbox'): string
    {
        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return $this->snapshotHashViaSocket($account, $password, $limit, $folder);
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
            return $this->snapshotHashViaSocket($account, $password, $limit, $folder);
        } finally {
            @imap_close($stream);
        }
    }

    public function unreadCount(MailAccount $account, string $password, string $folder = 'inbox'): int
    {
        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return $this->unreadCountViaSocket($account, $password, $folder);
        }

        try {
            $unseenUids = @imap_search($stream, 'UNSEEN', SE_UID);
            if (is_array($unseenUids)) {
                return count($unseenUids);
            }

            $uids = $this->latestUids($stream, 200);
            $count = 0;
            foreach ($uids as $uid) {
                $overview = $this->overviewByUid($stream, $uid);
                if ($overview && ! ((bool) ($overview->seen ?? false))) {
                    $count++;
                }
            }

            return $count;
        } catch (Throwable) {
            return $this->unreadCountViaSocket($account, $password, $folder);
        } finally {
            @imap_close($stream);
        }
    }

    public function attachmentDataByUid(MailAccount $account, string $password, string $messageId, string $partNumber, string $folder = 'inbox'): ?array
    {
        $uid = (int) $messageId;
        if ($uid <= 0 || ! preg_match('/^[0-9]+(?:\.[0-9]+)*$/', $partNumber)) {
            return null;
        }

        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return null;
        }

        try {
            $structure = @imap_fetchstructure($stream, (string) $uid, FT_UID);
            if (! is_object($structure)) {
                return null;
            }

            $part = $this->partByNumber($structure, $partNumber);
            if (! $part) {
                return null;
            }

            $rawBody = $this->fetchPartBody($stream, $uid, $partNumber);
            if ($rawBody === '') {
                return null;
            }

            $binary = $this->decodePartBody($rawBody, (int) ($part->encoding ?? 0));
            $mime = $this->partMimeType($part);
            $cid = trim((string) $this->partParameter($part, 'content-id'));

            return [
                'filename' => $this->resolveAttachmentFilename($part, $partNumber, $mime, $cid),
                'mime' => $mime !== '' ? $mime : 'application/octet-stream',
                'content' => $binary,
                'is_inline' => strtolower((string) ($part->disposition ?? '')) === 'inline',
            ];
        } catch (Throwable) {
            return null;
        } finally {
            @imap_close($stream);
        }
    }

    public function moveMessage(MailAccount $account, string $password, string $messageId, string $fromFolder, string $toFolder): bool
    {
        $uid = (int) $messageId;
        if ($uid <= 0) {
            return false;
        }

        $sourceFolder = $this->normalizeFolderKey($fromFolder);
        $targetFolder = $this->normalizeFolderKey($toFolder);

        $stream = $this->openStream($account, $password, $sourceFolder);
        if (! $stream) {
            return false;
        }

        try {
            $destinationName = $this->resolveFolderName($account, $password, $targetFolder);
            $moved = @imap_mail_move($stream, (string) $uid, $destinationName, CP_UID);
            if (! $moved) {
                return false;
            }

            @imap_expunge($stream);

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            @imap_close($stream);
        }
    }

    public function moveToTrash(MailAccount $account, string $password, string $messageId, string $fromFolder = 'inbox'): bool
    {
        return $this->moveMessage($account, $password, $messageId, $fromFolder, 'trash');
    }

    public function restoreFromTrash(MailAccount $account, string $password, string $messageId): bool
    {
        return $this->moveMessage($account, $password, $messageId, 'trash', 'inbox');
    }

    public function deleteForever(MailAccount $account, string $password, string $messageId, string $folder = 'trash'): bool
    {
        $uid = (int) $messageId;
        if ($uid <= 0) {
            return false;
        }

        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return false;
        }

        try {
            $deleted = @imap_delete($stream, (string) $uid, FT_UID);
            if (! $deleted) {
                return false;
            }

            @imap_expunge($stream);

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            @imap_close($stream);
        }
    }

    public function setSeenStatus(MailAccount $account, string $password, string $messageId, string $folder, bool $seen): bool
    {
        $uid = (int) $messageId;
        if ($uid <= 0) {
            return false;
        }

        $stream = $this->openStream($account, $password, $folder);
        if (! $stream) {
            return false;
        }

        try {
            $options = defined('ST_UID') ? ST_UID : 0;
            if ($seen) {
                return (bool) @imap_setflag_full($stream, (string) $uid, '\\Seen', $options);
            }

            return (bool) @imap_clearflag_full($stream, (string) $uid, '\\Seen', $options);
        } catch (Throwable) {
            return false;
        } finally {
            @imap_close($stream);
        }
    }

    public function saveDraft(
        MailAccount $account,
        string $password,
        string $fromEmail,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body
    ): bool {
        return $this->appendMessageToFolder(
            $account,
            $password,
            'drafts',
            $this->buildRawRfc822Message($fromEmail, $fromName, $to, $cc, $bcc, $subject, $body),
            '\\Draft'
        );
    }

    public function saveSentCopy(
        MailAccount $account,
        string $password,
        string $fromEmail,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body
    ): bool {
        return $this->appendMessageToFolder(
            $account,
            $password,
            'sent',
            $this->buildRawRfc822Message($fromEmail, $fromName, $to, $cc, $bcc, $subject, $body),
            '\\Seen'
        );
    }

    public function appendMessageToFolder(MailAccount $account, string $password, string $folder, string $rawMessage, string $flags = ''): bool
    {
        $stream = $this->openStream($account, $password, 'inbox');
        if (! $stream) {
            return false;
        }

        try {
            $prefix = $this->buildMailboxPrefix($account);
            if ($prefix === '') {
                return false;
            }

            $targetName = $this->resolveFolderName($account, $password, $folder);
            $targetMailbox = $prefix . $targetName;

            $appended = (bool) @imap_append($stream, $targetMailbox, $rawMessage, trim($flags));
            if ($appended) {
                return true;
            }

            $created = (bool) @imap_createmailbox($stream, function_exists('imap_utf7_encode') ? (string) @imap_utf7_encode($targetMailbox) : $targetMailbox);
            if (! $created) {
                return false;
            }

            return (bool) @imap_append($stream, $targetMailbox, $rawMessage, trim($flags));
        } catch (Throwable) {
            return false;
        } finally {
            @imap_close($stream);
        }
    }

    private function openStream(MailAccount $account, string $password, string $folder = 'inbox')
    {
        if (! function_exists('imap_open') || $password === '') {
            return false;
        }

        $prefix = $this->buildMailboxPrefix($account);
        if ($prefix === '') {
            return false;
        }

        $folderKey = $this->normalizeFolderKey($folder);
        $folderName = $this->resolveFolderName($account, $password, $folderKey);
        $mailbox = $prefix . $folderName;

        try {
            $stream = @imap_open($mailbox, $account->email, $password, 0, 1);
            if ($stream) {
                return $stream;
            }

            // Retry with novalidate-cert if not already present
            if (! str_contains($prefix, 'novalidate-cert')) {
                $noCertPrefix = str_replace('}', '/novalidate-cert}', $prefix);
                $stream = @imap_open($noCertPrefix . $folderName, $account->email, $password, 0, 1);
                if ($stream) {
                    return $stream;
                }
            }

            // Retry with folder aliases
            $aliases = self::FOLDER_ALIASES[$folderKey] ?? [];
            foreach ($aliases as $alias) {
                if ($alias === $folderName) {
                    continue;
                }
                $stream = @imap_open($prefix . $alias, $account->email, $password, 0, 1);
                if ($stream) {
                    $this->folderCache[(int) ($account->id ?? 0) . ':' . $folderKey] = $alias;
                    return $stream;
                }
                if (! str_contains($prefix, 'novalidate-cert')) {
                    $noCertPrefix = str_replace('}', '/novalidate-cert}', $prefix);
                    $stream = @imap_open($noCertPrefix . $alias, $account->email, $password, 0, 1);
                    if ($stream) {
                        $this->folderCache[(int) ($account->id ?? 0) . ':' . $folderKey] = $alias;
                        return $stream;
                    }
                }
            }

            return false;
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

    private function messageContentByUid($stream, int $uid): array
    {
        $content = [
            'text' => '',
            'html' => '',
            'attachments' => [],
        ];

        $structure = @imap_fetchstructure($stream, (string) $uid, FT_UID);
        if (! is_object($structure)) {
            $fallbackText = $this->bodyByUidFallback($stream, $uid);

            return [
                'text' => $this->cleanText($fallbackText),
                'html' => '',
                'attachments' => [],
            ];
        }

        $this->collectMessageParts($stream, $uid, $structure, '', $content);

        $plainText = $this->cleanText((string) ($content['text'] ?? ''));
        $html = trim((string) ($content['html'] ?? ''));

        if ($plainText === '' && $html !== '') {
            $plainText = $this->cleanText($html);
        }

        return [
            'text' => $plainText,
            'html' => $html,
            'attachments' => is_array($content['attachments'] ?? null) ? $content['attachments'] : [],
        ];
    }

    private function collectMessageParts($stream, int $uid, object $part, string $partNumber, array &$content): void
    {
        $children = $part->parts ?? null;
        if (is_array($children) && $children !== []) {
            foreach ($children as $index => $childPart) {
                if (! is_object($childPart)) {
                    continue;
                }

                $childPartNumber = $partNumber === ''
                    ? (string) ($index + 1)
                    : $partNumber . '.' . ($index + 1);

                $this->collectMessageParts($stream, $uid, $childPart, $childPartNumber, $content);
            }

            return;
        }

        $currentPartNumber = $partNumber !== '' ? $partNumber : '1';
        $rawBody = $this->fetchPartBody($stream, $uid, $currentPartNumber);
        if ($rawBody === '') {
            return;
        }

        $decodedBody = $this->decodePartBody($rawBody, (int) ($part->encoding ?? 0));
        if ($decodedBody === '') {
            return;
        }

        $mime = $this->partMimeType($part);
        $disposition = strtolower((string) ($part->disposition ?? ''));
        $filename = $this->partFilename($part);
        $cid = trim((string) $this->partParameter($part, 'content-id'));
        $isAttachment = $filename !== '' || $disposition === 'attachment';
        $isInlineAttachment = $disposition === 'inline' && ($filename !== '' || str_starts_with($mime, 'image/'));

        if ($isAttachment || $isInlineAttachment) {
            $content['attachments'][] = [
                'part' => $currentPartNumber,
                'filename' => $this->resolveAttachmentFilename($part, $currentPartNumber, $mime, $cid),
                'mime' => $mime,
                'size' => (int) (($part->bytes ?? strlen($decodedBody)) ?: strlen($decodedBody)),
                'is_inline' => $disposition === 'inline',
                'cid' => trim($cid, '<>'),
            ];

            return;
        }

        $charset = $this->partCharset($part);
        $text = $this->toUtf8($decodedBody, $charset);

        if ($mime === 'text/plain') {
            $content['text'] = trim((string) ($content['text'] ?? '') . "\n\n" . $text);
            return;
        }

        if ($mime === 'text/html') {
            $content['html'] = trim((string) ($content['html'] ?? '') . "\n\n" . $text);
        }
    }

    private function bodyByUidFallback($stream, int $uid): string
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

        return $body;
    }

    private function fetchPartBody($stream, int $uid, string $partNumber): string
    {
        $body = @imap_fetchbody($stream, (string) $uid, $partNumber, FT_UID | FT_PEEK);
        if (! is_string($body) || $body === '') {
            if ($partNumber === '1') {
                $body = @imap_body($stream, (string) $uid, FT_UID | FT_PEEK);
            }
        }

        return is_string($body) ? $body : '';
    }

    private function decodePartBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => (string) (base64_decode($body, true) ?: ''),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function partMimeType(object $part): string
    {
        $primary = match ((int) ($part->type ?? 0)) {
            0 => 'text',
            1 => 'multipart',
            2 => 'message',
            3 => 'application',
            4 => 'audio',
            5 => 'image',
            6 => 'video',
            default => 'application',
        };

        $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));
        return $primary . '/' . $subtype;
    }

    private function partParameter(object $part, string $name): string
    {
        $target = strtolower($name);

        foreach (['parameters', 'dparameters'] as $field) {
            $parameters = $part->{$field} ?? null;
            if (! is_array($parameters)) {
                continue;
            }

            foreach ($parameters as $parameter) {
                if (! is_object($parameter)) {
                    continue;
                }

                $attribute = strtolower((string) ($parameter->attribute ?? ''));
                if ($attribute !== $target) {
                    continue;
                }

                return trim((string) ($parameter->value ?? ''));
            }
        }

        return '';
    }

    private function partFilename(object $part): string
    {
        $filename = $this->partParameter($part, 'filename');
        if ($filename !== '') {
            return $this->decodeMimeHeader($filename);
        }

        $name = $this->partParameter($part, 'name');
        if ($name !== '') {
            return $this->decodeMimeHeader($name);
        }

        return '';
    }

    private function partCharset(object $part): string
    {
        return strtolower($this->partParameter($part, 'charset'));
    }

    private function toUtf8(string $value, string $charset): string
    {
        $input = trim($value);
        if ($input === '') {
            return '';
        }

        if ($charset === '' || $charset === 'utf-8') {
            return $input;
        }

        $converted = @iconv($charset, 'UTF-8//IGNORE', $input);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }

        return $input;
    }

    private function resolveAttachmentFilename(object $part, string $partNumber, string $mime, string $cid): string
    {
        $filename = trim($this->partFilename($part));
        if ($filename !== '') {
            return $filename;
        }

        $extension = 'bin';
        if (str_contains($mime, '/')) {
            $segments = explode('/', $mime, 2);
            $extension = preg_replace('/[^a-z0-9]+/i', '', strtolower($segments[1] ?? 'bin')) ?: 'bin';
        }

        $normalizedCid = trim($cid, '<>');
        if ($normalizedCid !== '') {
            $safeCid = preg_replace('/[^a-z0-9._-]+/i', '-', $normalizedCid) ?: 'inline';
            return $safeCid . '.' . $extension;
        }

        $safePart = str_replace('.', '_', $partNumber);
        return 'attachment-' . $safePart . '.' . $extension;
    }

    private function partByNumber(object $structure, string $partNumber): ?object
    {
        $parts = $structure->parts ?? null;
        if ((! is_array($parts) || $parts === []) && $partNumber === '1') {
            return $structure;
        }

        $segments = explode('.', $partNumber);
        $current = $structure;

        foreach ($segments as $segment) {
            $index = (int) $segment;
            if ($index <= 0) {
                return null;
            }

            $children = $current->parts ?? null;
            if (! is_array($children) || ! isset($children[$index - 1]) || ! is_object($children[$index - 1])) {
                return null;
            }

            $current = $children[$index - 1];
        }

        return $current;
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
        if (function_exists('imap_mime_header_decode')) {
            $decoded = @imap_mime_header_decode($value);
            if (is_array($decoded) && $decoded !== []) {
                $parts = [];
                foreach ($decoded as $part) {
                    if (is_object($part) && isset($part->text)) {
                        $parts[] = (string) $part->text;
                    }
                }

                $output = trim(implode('', $parts));
                if ($output !== '') {
                    return $output;
                }
            }
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && trim($decoded) !== '') {
                return trim($decoded);
            }
        }

        if (function_exists('mb_decode_mimeheader')) {
            $decoded = @mb_decode_mimeheader($value);
            if (is_string($decoded) && trim($decoded) !== '') {
                return trim($decoded);
            }
        }

        return trim($value) !== '' ? trim($value) : '(No subject)';
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
        $prefix = $this->buildMailboxPrefix($account);
        if ($prefix === '') {
            return '';
        }

        return $prefix . 'INBOX';
    }

    private function buildMailboxPrefix(MailAccount $account): string
    {
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = (string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', '')));
        $port = (int) ($account->imap_port ?: (Setting::getValue('mail_server_imap_port') ?: config('apptimatic_email.imap.port', 993)));
        $encryption = strtolower((string) ($account->imap_encryption ?: (Setting::getValue('mail_server_imap_encryption') ?: config('apptimatic_email.imap.encryption', 'ssl'))));
        $certSetting = Setting::getValue('mail_server_validate_cert');
        $validateCert = (bool) ($account->imap_validate_cert ?? ($certSetting !== null ? (bool) $certSetting : config('apptimatic_email.imap.validate_cert', true)));

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

        return '{' . $host . ':' . $port . '/' . implode('/', $flags) . '}';
    }

    private function resolveFolderName(MailAccount $account, string $password, string $folder): string
    {
        $folderKey = $this->normalizeFolderKey($folder);
        $cacheKey = (int) ($account->id ?? 0) . ':' . $folderKey;
        if (isset($this->folderCache[$cacheKey])) {
            return $this->folderCache[$cacheKey];
        }

        $aliases = self::FOLDER_ALIASES[$folderKey] ?? self::FOLDER_ALIASES['inbox'];
        $default = $aliases[0] ?? 'INBOX';
        if ($folderKey === 'inbox') {
            $this->folderCache[$cacheKey] = 'INBOX';
            return 'INBOX';
        }

        $prefix = $this->buildMailboxPrefix($account);
        if ($prefix === '') {
            $this->folderCache[$cacheKey] = $default;
            return $default;
        }

        $inboxStream = $this->openStreamByMailbox($account, $password, $prefix . 'INBOX');
        if (! $inboxStream) {
            $this->folderCache[$cacheKey] = $default;
            return $default;
        }

        try {
            $mailboxes = @imap_getmailboxes($inboxStream, $prefix, '*');
            if (! is_array($mailboxes) || $mailboxes === []) {
                $this->folderCache[$cacheKey] = $default;
                return $default;
            }

            $available = [];
            foreach ($mailboxes as $mailbox) {
                if (! is_object($mailbox) || ! isset($mailbox->name)) {
                    continue;
                }

                $name = (string) $mailbox->name;
                $decodedName = function_exists('imap_utf7_decode') ? (string) @imap_utf7_decode($name) : $name;
                if (str_starts_with($decodedName, $prefix)) {
                    $decodedName = substr($decodedName, strlen($prefix));
                }

                $decodedName = trim($decodedName);
                if ($decodedName !== '') {
                    $available[] = $decodedName;
                }
            }

            $resolved = $this->matchBestFolderName($aliases, $available);
            $this->folderCache[$cacheKey] = $resolved;

            return $resolved;
        } finally {
            @imap_close($inboxStream);
        }
    }

    private function matchBestFolderName(array $aliases, array $available): string
    {
        if ($available === []) {
            return $aliases[0] ?? 'INBOX';
        }

        $normalizedAvailable = [];
        foreach ($available as $name) {
            $normalizedAvailable[$this->normalizeFolderName($name)] = $name;
        }

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeFolderName((string) $alias);
            if (isset($normalizedAvailable[$normalizedAlias])) {
                return $normalizedAvailable[$normalizedAlias];
            }
        }

        foreach ($aliases as $alias) {
            $normalizedAlias = $this->normalizeFolderName((string) $alias);
            foreach ($available as $name) {
                $normalizedName = $this->normalizeFolderName($name);
                if ($normalizedName === $normalizedAlias) {
                    return $name;
                }

                if (str_ends_with($normalizedName, '.' . $normalizedAlias) || str_ends_with($normalizedName, '/' . $normalizedAlias)) {
                    return $name;
                }
            }
        }

        return $aliases[0] ?? 'INBOX';
    }

    private function normalizeFolderName(string $value): string
    {
        $name = strtolower(trim($value));
        $name = str_replace('\\', '/', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?: $name;

        return $name;
    }

    private function normalizeFolderKey(string $folder): string
    {
        $key = strtolower(trim($folder));
        if (! array_key_exists($key, self::FOLDER_ALIASES)) {
            return 'inbox';
        }

        return $key;
    }

    private function openStreamByMailbox(MailAccount $account, string $password, string $mailbox)
    {
        try {
            return @imap_open($mailbox, (string) $account->email, $password, 0, 1);
        } catch (Throwable) {
            return false;
        }
    }

    private function buildRawRfc822Message(
        string $fromEmail,
        string $fromName,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $body
    ): string {
        $subjectHeader = function_exists('mb_encode_mimeheader')
            ? (string) @mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n")
            : $subject;

        $fromHeader = trim($fromName) !== ''
            ? sprintf('"%s" <%s>', addcslashes($fromName, "\"\\"), $fromEmail)
            : $fromEmail;

        $headers = [
            'From: ' . $fromHeader,
        ];

        if ($to !== []) {
            $headers[] = 'To: ' . implode(', ', $to);
        }
        if ($cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }
        if ($bcc !== []) {
            $headers[] = 'Bcc: ' . implode(', ', $bcc);
        }

        $headers[] = 'Subject: ' . $subjectHeader;
        $headers[] = 'Date: ' . now()->toRfc2822String();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'X-Mailer: Apptimatic';

        $normalizedBody = preg_replace("/\r\n|\r/", "\n", $body) ?? $body;
        $normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

        return implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
    }

    private function openImapSocket(string $host, int $port, string $encryption, string $email, string $password)
    {
        $protocol = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("{$protocol}{$host}:{$port}", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context);
        if (! $socket) {
            return false;
        }

        stream_set_timeout($socket, 8);

        $greeting = fgets($socket, 1024);
        if ($greeting === false || ! str_contains(strtoupper((string) $greeting), 'OK')) {
            @fclose($socket);
            return false;
        }

        if ($encryption === 'tls' && $port !== 993) {
            fwrite($socket, "TAG0 STARTTLS\r\n");
            $tlsResponse = fgets($socket, 1024);
            if ($tlsResponse !== false && str_starts_with(trim($tlsResponse), 'TAG0 OK')) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        }

        $escapedEmail = addcslashes($email, "\\\"");
        $escapedPassword = addcslashes($password, "\\\"");
        fwrite($socket, "TAG_LOGIN LOGIN \"{$escapedEmail}\" \"{$escapedPassword}\"\r\n");

        $authenticated = false;
        while (! feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $trimmed = trim($line);
            if (str_starts_with($trimmed, 'TAG_LOGIN OK')) {
                $authenticated = true;
                break;
            }
            if (str_starts_with($trimmed, 'TAG_LOGIN NO') || str_starts_with($trimmed, 'TAG_LOGIN BAD')) {
                break;
            }
        }

        if (! $authenticated) {
            @fclose($socket);
            return false;
        }

        return $socket;
    }

    private function sendSocketCommand($socket, string $command, string $tag = 'TAG'): array
    {
        fwrite($socket, "{$tag} {$command}\r\n");
        $lines = [];
        $status = '';

        while (! feof($socket)) {
            $line = fgets($socket, 2048);
            if ($line === false) {
                break;
            }
            $trimmed = trim($line);
            $lines[] = $trimmed;
            if (str_starts_with($trimmed, "{$tag} OK") || str_starts_with($trimmed, "{$tag} NO") || str_starts_with($trimmed, "{$tag} BAD")) {
                $status = $trimmed;
                break;
            }
        }

        return [
            'status' => $status,
            'lines' => $lines,
        ];
    }

    private function fetchViaSocket(MailAccount $account, string $password, string $folder = 'inbox', int $limit = 50): array
    {
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = (string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', '')));
        $port = (int) ($account->imap_port ?: (Setting::getValue('mail_server_imap_port') ?: config('apptimatic_email.imap.port', 993)));
        $encryption = strtolower((string) ($account->imap_encryption ?: (Setting::getValue('mail_server_imap_encryption') ?: config('apptimatic_email.imap.encryption', 'ssl'))));

        if ($host === '' || $port <= 0 || $password === '') {
            return [];
        }

        $socket = $this->openImapSocket($host, $port, $encryption, (string) $account->email, $password);
        if (! $socket) {
            return [];
        }

        try {
            $folderKey = $this->normalizeFolderKey($folder);
            $aliases = self::FOLDER_ALIASES[$folderKey] ?? ['INBOX'];
            $selectedFolder = null;

            foreach ($aliases as $candidate) {
                $res = $this->sendSocketCommand($socket, "SELECT \"{$candidate}\"", 'TAG_SEL');
                if (str_contains($res['status'], 'OK')) {
                    $selectedFolder = $candidate;
                    break;
                }
            }

            if (! $selectedFolder) {
                return [];
            }

            $searchRes = $this->sendSocketCommand($socket, 'UID SEARCH ALL', 'TAG_SRCH');
            $uids = [];
            foreach ($searchRes['lines'] as $line) {
                if (str_starts_with(strtoupper($line), '* SEARCH')) {
                    $parts = preg_split('/\s+/', trim(substr($line, 8)));
                    foreach ($parts as $p) {
                        $val = (int) $p;
                        if ($val > 0) {
                            $uids[] = $val;
                        }
                    }
                }
            }

            if ($uids === []) {
                return [];
            }

            rsort($uids);
            $limit = max(1, min($limit, 100));
            $uids = array_slice($uids, 0, $limit);

            $messages = [];
            foreach ($uids as $uid) {
                $msgData = $this->fetchMessageViaSocket($socket, $uid, $folderKey, (string) ($account->email ?? ''));
                if ($msgData) {
                    $messages[] = $msgData;
                }
            }

            usort($messages, fn (array $a, array $b) => $b['received_at']->getTimestamp() <=> $a['received_at']->getTimestamp());

            return $messages;
        } catch (Throwable) {
            return [];
        } finally {
            $this->sendSocketCommand($socket, 'LOGOUT', 'TAG_BYE');
            @fclose($socket);
        }
    }

    private function fetchMessageViaSocket($socket, int $uid, string $folder, string $accountEmail): ?array
    {
        $tag = 'FTCH_' . $uid;
        fwrite($socket, "{$tag} UID FETCH {$uid} (FLAGS BODY.PEEK[])\r\n");

        $seen = false;
        $rawMessage = '';
        $literalRemaining = 0;

        while (! feof($socket)) {
            if ($literalRemaining > 0) {
                $readSize = min($literalRemaining, 8192);
                $chunk = fread($socket, $readSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $rawMessage .= $chunk;
                $literalRemaining -= strlen($chunk);
                continue;
            }

            $line = fgets($socket, 2048);
            if ($line === false) {
                break;
            }

            $trimmed = trim($line);
            if (str_starts_with($trimmed, "{$tag} OK") || str_starts_with($trimmed, "{$tag} NO") || str_starts_with($trimmed, "{$tag} BAD")) {
                break;
            }

            if (str_contains(strtoupper($line), '\\SEEN')) {
                $seen = true;
            }

            if (preg_match('/\{(\d+)\}\s*$/', $line, $matches)) {
                $literalRemaining = (int) $matches[1];
            }
        }

        if ($rawMessage === '') {
            return null;
        }

        return $this->parseRawMimeMessage($rawMessage, $uid, $folder, $accountEmail, $seen);
    }

    private function parseRawMimeMessage(string $rawMessage, int $uid, string $folder, string $accountEmail, bool $seen): array
    {
        $parts = preg_split("/\r\n\r\n|\n\n/", $rawMessage, 2);
        $headerText = $parts[0] ?? '';
        $bodyText = $parts[1] ?? '';

        $unfoldedHeaders = preg_replace("/\r\n[ \t]+|\n[ \t]+/", ' ', $headerText) ?? $headerText;
        $headerLines = explode("\n", str_replace("\r", '', $unfoldedHeaders));

        $headers = [];
        foreach ($headerLines as $line) {
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $name = strtolower(trim(substr($line, 0, $colonPos)));
                $value = trim(substr($line, $colonPos + 1));
                $headers[$name] = $value;
            }
        }

        $rawSubject = (string) ($headers['subject'] ?? '(No subject)');
        $subject = $this->decodeMimeHeader($rawSubject);
        $from = $this->parseFrom((string) ($headers['from'] ?? 'Unknown sender'));
        $receivedAt = $this->parseDate((string) ($headers['date'] ?? ''));
        $to = (string) ($headers['to'] ?? $accountEmail);

        $contentType = (string) ($headers['content-type'] ?? 'text/plain');
        $contentTransferEncoding = strtolower((string) ($headers['content-transfer-encoding'] ?? ''));

        $plainBody = '';
        $htmlBody = '';
        $attachments = [];

        if (preg_match('/boundary=["\']?([^"\'\s;]+)["\']?/i', $contentType, $boundaryMatch)) {
            $boundary = $boundaryMatch[1];
            $sections = explode('--' . $boundary, $bodyText);
            foreach ($sections as $section) {
                $section = trim($section);
                if ($section === '' || $section === '--') {
                    continue;
                }

                $subParts = preg_split("/\r\n\r\n|\n\n/", $section, 2);
                $subHeadersText = $subParts[0] ?? '';
                $subBody = $subParts[1] ?? '';

                $subHeaders = [];
                $subLines = explode("\n", str_replace("\r", '', preg_replace("/\r\n[ \t]+|\n[ \t]+/", ' ', $subHeadersText) ?? $subHeadersText));
                foreach ($subLines as $sl) {
                    $cp = strpos($sl, ':');
                    if ($cp !== false) {
                        $subHeaders[strtolower(trim(substr($sl, 0, $cp)))] = trim(substr($sl, $cp + 1));
                    }
                }

                $subType = (string) ($subHeaders['content-type'] ?? 'text/plain');
                $subEncoding = strtolower((string) ($subHeaders['content-transfer-encoding'] ?? ''));
                $subDisposition = strtolower((string) ($subHeaders['content-disposition'] ?? ''));

                $decodedSub = $this->decodeBodyByEncoding($subBody, $subEncoding);

                if (str_contains($subDisposition, 'attachment') || preg_match('/filename=["\']?([^"\'\s;]+)["\']?/i', $subDisposition . ' ' . $subType, $fnMatch)) {
                    $fn = isset($fnMatch[1]) ? $this->decodeMimeHeader($fnMatch[1]) : 'attachment.bin';
                    $mime = trim(explode(';', $subType)[0]);
                    $attachments[] = [
                        'part' => (string) (count($attachments) + 1),
                        'filename' => $fn,
                        'mime' => $mime !== '' ? $mime : 'application/octet-stream',
                        'size' => strlen($decodedSub),
                        'is_inline' => str_contains($subDisposition, 'inline'),
                        'cid' => '',
                    ];
                } elseif (stripos($subType, 'text/html') !== false) {
                    $htmlBody .= ($htmlBody !== '' ? "\n" : '') . $decodedSub;
                } elseif (stripos($subType, 'text/plain') !== false) {
                    $plainBody .= ($plainBody !== '' ? "\n" : '') . $decodedSub;
                }
            }
        } else {
            $decoded = $this->decodeBodyByEncoding($bodyText, $contentTransferEncoding);
            if (stripos($contentType, 'text/html') !== false) {
                $htmlBody = $decoded;
                $plainBody = $this->cleanText($decoded);
            } else {
                $plainBody = $decoded;
            }
        }

        $plainBody = $this->cleanText($plainBody);
        $snippet = $this->buildSnippet($plainBody !== '' ? $plainBody : $this->cleanText($htmlBody), $subject);

        return [
            'id' => (string) $uid,
            'thread_id' => $this->threadIdFromSubject($subject),
            'folder' => $folder,
            'sender_name' => $from['name'],
            'sender_email' => $from['email'],
            'to' => $to,
            'subject' => $subject,
            'snippet' => $snippet,
            'body' => $plainBody,
            'body_html' => $htmlBody,
            'attachments' => $attachments,
            'has_attachments' => count($attachments) > 0,
            'received_at' => $receivedAt,
            'unread' => ! $seen,
        ];
    }

    private function decodeBodyByEncoding(string $body, string $encoding): string
    {
        $enc = strtolower(trim($encoding));
        if ($enc === 'base64') {
            return (string) (base64_decode(trim($body), true) ?: $body);
        }
        if ($enc === 'quoted-printable') {
            return quoted_printable_decode($body);
        }

        return $body;
    }

    private function unreadCountViaSocket(MailAccount $account, string $password, string $folder = 'inbox'): int
    {
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = (string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', '')));
        $port = (int) ($account->imap_port ?: (Setting::getValue('mail_server_imap_port') ?: config('apptimatic_email.imap.port', 993)));
        $encryption = strtolower((string) ($account->imap_encryption ?: (Setting::getValue('mail_server_imap_encryption') ?: config('apptimatic_email.imap.encryption', 'ssl'))));

        if ($host === '' || $port <= 0 || $password === '') {
            return 0;
        }

        $socket = $this->openImapSocket($host, $port, $encryption, (string) $account->email, $password);
        if (! $socket) {
            return 0;
        }

        try {
            $folderKey = $this->normalizeFolderKey($folder);
            $aliases = self::FOLDER_ALIASES[$folderKey] ?? ['INBOX'];
            foreach ($aliases as $candidate) {
                $res = $this->sendSocketCommand($socket, "SELECT \"{$candidate}\"", 'TAG_SEL');
                if (str_contains($res['status'], 'OK')) {
                    break;
                }
            }

            $searchRes = $this->sendSocketCommand($socket, 'UID SEARCH UNSEEN', 'TAG_UNSEEN');
            $count = 0;
            foreach ($searchRes['lines'] as $line) {
                if (str_starts_with(strtoupper($line), '* SEARCH')) {
                    $parts = preg_split('/\s+/', trim(substr($line, 8)));
                    foreach ($parts as $p) {
                        if ((int) $p > 0) {
                            $count++;
                        }
                    }
                }
            }

            return $count;
        } catch (Throwable) {
            return 0;
        } finally {
            $this->sendSocketCommand($socket, 'LOGOUT', 'TAG_BYE');
            @fclose($socket);
        }
    }

    private function snapshotHashViaSocket(MailAccount $account, string $password, int $limit = 40, string $folder = 'inbox'): string
    {
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = (string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', '')));
        $port = (int) ($account->imap_port ?: (Setting::getValue('mail_server_imap_port') ?: config('apptimatic_email.imap.port', 993)));
        $encryption = strtolower((string) ($account->imap_encryption ?: (Setting::getValue('mail_server_imap_encryption') ?: config('apptimatic_email.imap.encryption', 'ssl'))));

        if ($host === '' || $port <= 0 || $password === '') {
            return '';
        }

        $socket = $this->openImapSocket($host, $port, $encryption, (string) $account->email, $password);
        if (! $socket) {
            return '';
        }

        try {
            $folderKey = $this->normalizeFolderKey($folder);
            $aliases = self::FOLDER_ALIASES[$folderKey] ?? ['INBOX'];
            foreach ($aliases as $candidate) {
                $res = $this->sendSocketCommand($socket, "SELECT \"{$candidate}\"", 'TAG_SEL');
                if (str_contains($res['status'], 'OK')) {
                    break;
                }
            }

            $searchRes = $this->sendSocketCommand($socket, 'UID SEARCH ALL', 'TAG_SRCH');
            $uids = [];
            foreach ($searchRes['lines'] as $line) {
                if (str_starts_with(strtoupper($line), '* SEARCH')) {
                    $parts = preg_split('/\s+/', trim(substr($line, 8)));
                    foreach ($parts as $p) {
                        $val = (int) $p;
                        if ($val > 0) {
                            $uids[] = $val;
                        }
                    }
                }
            }

            rsort($uids);
            $limit = max(1, min($limit, 40));
            $uids = array_slice($uids, 0, $limit);

            return hash('sha256', implode(',', $uids));
        } catch (Throwable) {
            return '';
        } finally {
            $this->sendSocketCommand($socket, 'LOGOUT', 'TAG_BYE');
            @fclose($socket);
        }
    }
}
