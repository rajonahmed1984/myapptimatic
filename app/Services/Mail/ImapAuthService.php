<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\Setting;
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
            return $this->verifyCredentialsViaSocket($account, $password);
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
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = trim((string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', ''))));
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

    private function imapUnavailableDetail(): string
    {
        $sapi = PHP_SAPI;
        $extensionLoaded = extension_loaded('imap') ? 'yes' : 'no';

        $disabledFunctions = strtolower((string) ini_get('disable_functions'));
        $imapDisabled = str_contains($disabledFunctions, 'imap_open') ? 'yes' : 'no';

        return sprintf(
            'imap_open unavailable (sapi=%s, extension_loaded=%s, imap_open_disabled=%s)',
            $sapi,
            $extensionLoaded,
            $imapDisabled
        );
    }

    private function verifyCredentialsViaSocket(MailAccount $account, string $password): bool
    {
        $globalImapHost = Setting::getValue('mail_server_imap_host') ?: Setting::getValue('mail_server_smtp_host');
        $host = trim((string) ($account->imap_host ?: ($globalImapHost ?: config('apptimatic_email.imap.host', ''))));
        $port = (int) ($account->imap_port ?: (Setting::getValue('mail_server_imap_port') ?: config('apptimatic_email.imap.port', 993)));
        $encryption = strtolower((string) ($account->imap_encryption ?: (Setting::getValue('mail_server_imap_encryption') ?: config('apptimatic_email.imap.encryption', 'ssl'))));

        if ($host === '' || $port <= 0) {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = 'IMAP host or port is not configured.';
            return false;
        }

        $protocol = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("{$protocol}{$host}:{$port}", $errno, $errstr, 6, STREAM_CLIENT_CONNECT, $context);

        if (! $socket) {
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = "Could not connect to {$host}:{$port} ({$errstr})";
            return false;
        }

        stream_set_timeout($socket, 6);

        // Read server greeting
        $greeting = fgets($socket, 1024);
        if ($greeting === false || ! str_contains(strtoupper((string) $greeting), 'OK')) {
            @fclose($socket);
            $this->lastFailureType = self::FAILURE_SERVER_UNAVAILABLE;
            $this->lastFailureDetail = 'Invalid response from mail server greeting.';
            return false;
        }

        if ($encryption === 'tls' && $port !== 993) {
            fwrite($socket, "TAG0 STARTTLS\r\n");
            $tlsResponse = fgets($socket, 1024);
            if ($tlsResponse !== false && str_starts_with(trim($tlsResponse), 'TAG0 OK')) {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        }

        // Send LOGIN command
        $escapedEmail = addcslashes($account->email, "\\\"");
        $escapedPassword = addcslashes($password, "\\\"");
        fwrite($socket, "TAG1 LOGIN \"{$escapedEmail}\" \"{$escapedPassword}\"\r\n");

        $authenticated = false;
        $lastResponse = '';
        while (! feof($socket)) {
            $line = fgets($socket, 1024);
            if ($line === false) {
                break;
            }
            $lastResponse = trim($line);
            if (str_starts_with($lastResponse, 'TAG1 OK')) {
                $authenticated = true;
                break;
            }
            if (str_starts_with($lastResponse, 'TAG1 NO') || str_starts_with($lastResponse, 'TAG1 BAD')) {
                break;
            }
        }

        @fwrite($socket, "TAG2 LOGOUT\r\n");
        @fclose($socket);

        if (! $authenticated) {
            $this->lastFailureType = self::FAILURE_INVALID_CREDENTIALS;
            $this->lastFailureDetail = $lastResponse !== '' ? $lastResponse : 'Invalid email or password.';
            return false;
        }

        return true;
    }
}
