<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Throwable;

class ImapAuthService
{
    public function verifyCredentials(MailAccount $account, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        if (! function_exists('imap_open')) {
            return false;
        }

        $mailbox = $this->buildMailboxString($account);
        if ($mailbox === '') {
            return false;
        }

        $options = 0;
        $retries = 1;

        try {
            $stream = @imap_open($mailbox, $account->email, $password, $options, $retries);

            if (! $stream) {
                return false;
            }

            @imap_ping($stream);
            @imap_close($stream);

            return true;
        } catch (Throwable) {
            return false;
        }
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
