<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends one notification to one browser push subscription (Web Push: RFC 8030,
 * payload encryption RFC 8291 "aes128gcm", VAPID RFC 8292). This is how the
 * installed web app on iPhone gets notifications, and it also covers desktop
 * and Android browsers.
 *
 * Built on ext-openssl alone, like FcmClient. Keys are assembled from raw bytes
 * rather than with openssl_pkey_new(), which fails on hosts (XAMPP on Windows)
 * that have no openssl.cnf.
 */
class WebPushClient
{
    public const SENT = 'sent';

    /** The browser dropped the subscription (permission revoked, site data cleared). */
    public const INVALID_SUBSCRIPTION = 'invalid_subscription';

    public const FAILED = 'failed';

    /** Push services the server is allowed to post to; the endpoint comes from the browser. */
    public const ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        'push.apple.com',
        'push.services.mozilla.com',
        'notify.windows.com',
    ];

    private const EC_PRIVATE_KEY_PREFIX = '30310201010420';

    private const EC_PRIVATE_KEY_SUFFIX = 'a00a06082a8648ce3d030107';

    private const EC_PUBLIC_KEY_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    private const RECORD_SIZE = 4096;

    public function isConfigured(): bool
    {
        return strlen(self::base64UrlDecode($this->publicKey() ?? '')) === 65
            && strlen(self::base64UrlDecode((string) config('services.webpush.private_key'))) === 32;
    }

    /** The VAPID public key browsers subscribe with (base64url, uncompressed P-256 point). */
    public function publicKey(): ?string
    {
        $key = trim((string) config('services.webpush.public_key'));

        return $key === '' ? null : $key;
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }

        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload  Read by the service worker (public/sw.js).
     */
    public function send(string $endpoint, string $userPublicKey, string $authSecret, array $payload, int $ttl = 86400): string
    {
        if (! self::isAllowedEndpoint($endpoint)) {
            return self::INVALID_SUBSCRIPTION;
        }

        $body = $this->encrypt(
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            self::base64UrlDecode($userPublicKey),
            self::base64UrlDecode($authSecret)
        );

        $response = Http::withHeaders([
            'Authorization' => 'vapid t='.$this->vapidToken($endpoint).', k='.$this->publicKey(),
            'Content-Encoding' => 'aes128gcm',
            'TTL' => (string) $ttl,
            'Urgency' => 'high',
        ])
            ->withBody($body, 'application/octet-stream')
            ->timeout(10)
            ->post($endpoint);

        if ($response->successful()) {
            return self::SENT;
        }

        if (in_array($response->status(), [404, 410], true)) {
            return self::INVALID_SUBSCRIPTION;
        }

        report(new RuntimeException('Web push failed ('.$response->status().'): '.$response->body()));

        return self::FAILED;
    }

    /**
     * Encrypts a payload for one subscription as a single aes128gcm record.
     */
    public function encrypt(string $plaintext, string $userPublicKey, string $authSecret, ?string $salt = null): string
    {
        if (strlen($userPublicKey) !== 65 || strlen($authSecret) !== 16) {
            throw new RuntimeException('The push subscription keys are malformed.');
        }

        [$serverPrivateKey, $serverPublicKey] = self::newKeyPair();
        $salt ??= random_bytes(16);

        $sharedSecret = openssl_pkey_derive(self::publicKeyResource($userPublicKey), $serverPrivateKey, 32);
        if ($sharedSecret === false) {
            throw new RuntimeException('Could not derive the web push shared secret.');
        }

        $keyInfo = "WebPush: info\0".$userPublicKey.$serverPublicKey;
        $ikm = hash_hkdf('sha256', $sharedSecret, 32, $keyInfo, $authSecret);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        // 0x02 marks the last (and only) record.
        $ciphertext = openssl_encrypt($plaintext."\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Could not encrypt the web push payload.');
        }

        return $salt.pack('N', self::RECORD_SIZE).chr(65).$serverPublicKey.$ciphertext.$tag;
    }

    /**
     * A fresh VAPID key pair, base64url encoded as browsers and other Web Push
     * libraries expect.
     *
     * @return array{public_key: string, private_key: string}
     */
    public static function generateVapidKeys(): array
    {
        [, $publicKey, $secret] = self::newKeyPair();

        return [
            'public_key' => self::base64UrlEncode($publicKey),
            'private_key' => self::base64UrlEncode($secret),
        ];
    }

    private function vapidToken(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $claims = [
            'aud' => $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
            'exp' => time() + 12 * 3600,
            'sub' => $this->subject(),
        ];

        $unsigned = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            .'.'.self::base64UrlEncode((string) json_encode($claims, JSON_UNESCAPED_SLASHES));

        $privateKey = self::privateKeyResource(self::base64UrlDecode((string) config('services.webpush.private_key')));
        if ($privateKey === false || ! openssl_sign($unsigned, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The VAPID private key could not be used to sign.');
        }

        return $unsigned.'.'.self::base64UrlEncode(self::derToRawSignature($derSignature));
    }

    /** Apple rejects a VAPID subject that is not a mailto: or a public https URL. */
    private function subject(): string
    {
        $subject = trim((string) config('services.webpush.subject'));
        if ($subject !== '') {
            return $subject;
        }

        $from = trim((string) config('mail.from.address'));
        if ($from !== '' && ! str_ends_with($from, '@example.com')) {
            return 'mailto:'.$from;
        }

        return (string) config('app.url');
    }

    /**
     * @return array{0: \OpenSSLAsymmetricKey, 1: string, 2: string}  key, uncompressed public point, raw secret
     */
    private static function newKeyPair(): array
    {
        // A random 32-byte scalar is a valid P-256 key except in the ~2^-32 case
        // where it exceeds the curve order; OpenSSL refuses those, so try again.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $secret = random_bytes(32);
            $key = self::privateKeyResource($secret);
            $details = $key === false ? false : openssl_pkey_get_details($key);

            if ($details !== false && isset($details['ec']['x'], $details['ec']['y'])) {
                $publicKey = "\x04".str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT).str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

                return [$key, $publicKey, $secret];
            }
        }

        throw new RuntimeException('Could not create a P-256 key.');
    }

    /** OpenSSL derives the public point itself when the SEC1 key leaves it out. */
    private static function privateKeyResource(string $secret): \OpenSSLAsymmetricKey|false
    {
        if (strlen($secret) !== 32) {
            return false;
        }

        $der = hex2bin(self::EC_PRIVATE_KEY_PREFIX).$secret.hex2bin(self::EC_PRIVATE_KEY_SUFFIX);

        return openssl_pkey_get_private(self::pem('EC PRIVATE KEY', $der));
    }

    private static function publicKeyResource(string $publicKey): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_public(self::pem('PUBLIC KEY', hex2bin(self::EC_PUBLIC_KEY_PREFIX).$publicKey));
        if ($key === false) {
            throw new RuntimeException('The push subscription public key is not a valid P-256 point.');
        }

        return $key;
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END {$label}-----\n";
    }

    /** openssl_sign() returns ASN.1 DER; JWS ES256 wants r and s as 32 bytes each. */
    private static function derToRawSignature(string $der): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7f;
        }

        $raw = '';
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\0");
            $raw .= str_pad($integer, 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $raw;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr(trim($value), '-_', '+/'), true);
    }
}
