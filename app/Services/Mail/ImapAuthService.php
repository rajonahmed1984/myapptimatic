<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Throwable;

class ImapAuthService
{
    private const FAILURE_NONE = 'none';
    private const FAILURE_INVALID_CREDENTIALS = 'invalid_credentials';
    private const FAILURE_SERVER_UNAVAILABLE = 'server_unavailable';

    private string $lastFailureType = self::FAILURE_NONE;
    private ?string $lastFailureDetail = null;

    public function verifyCredentials(MailAccount $account, string $password): bool
    {
        $this->lastFailureType = self::FAILURE_NONE;
        $this->lastFailureDetail = null;

        if ($password === '') {
            return false;
        }

        if (! function_exists('imap_open')) {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = 'imap extension is not installed';
            return false;
        }

        $mailbox = $this->buildMailboxString($account);
        if ($mailbox === '') {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = 'imap mailbox host/port is missing';
            return false;
        }

        $options = 0;
        $retries = 1;

        @imap_errors();
        @imap_alerts();

        try {
            $stream = @imap_open($mailbox, $account->email, $password, $options, $retries);

            if (! $stream) {
                $this->captureImapFailure();
                return false;
            }

            @imap_ping($stream);
            @imap_close($stream);

            return true;
        } catch (Throwable $exception) {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = $exception::class . ': ' . $exception->getMessage();

            return false;
        }
    }

    public function lastFailureType(): string
    {
        return $this->lastFailureType;
    }

    public function lastFailureDetail(): ?string
    {
        return $this->lastFailureDetail;
    }

    private function buildMailboxString(MailAccount $account): string
    {
        $host = trim((string) ($account->imap_host ?: config('apptimatic_email.imap.host', '')));
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

    private function captureImapFailure(): void
    {
        $lastError = (string) @imap_last_error();
        $errors = @imap_errors();
        $errorText = trim(implode(' | ', array_filter(array_merge(
            [$lastError],
            is_array($errors) ? $errors : []
        ))));

        if ($errorText === '') {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = 'imap_open failed without error details';
            return;
        }

        $normalized = strtolower($errorText);
        $credentialHints = [
            'authentication failed',
            'authenticationfailed',
            'invalid credentials',
            'login failed',
            'auth failed',
            'authentification failed',
            'username and password not accepted',
            'invalid login',
            'invalid user',
            'invalid password',
        ];

        foreach ($credentialHints as $hint) {
            if (str_contains($normalized, $hint)) {
                $this->lastFailureType = self::FAILURE_INVALID_CREDENTIALS;
                $this->lastFailureDetail = $errorText;
                return;
            }
        }

        if (str_contains($normalized, 'auth') && str_contains($normalized, 'fail')) {
            $this->lastFailureType = self::FAILURE_INVALID_CREDENTIALS;
            $this->lastFailureDetail = $errorText;
            return;
        }

        $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
        $this->lastFailureDetail = $errorText;
    }
}
