<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseCertificate;
use Illuminate\Support\Str;
use RuntimeException;

class LicenseCertificateService
{
    /**
     * Issue a new signed offline certificate for a license, revoking any
     * previously active certificate for the same license.
     */
    public function issue(License $license, ?int $userId, ?string $reason = null): LicenseCertificate
    {
        $privateKey = $this->privateKey();

        $license->loadMissing(['domains', 'subscription.plan']);

        $license->certificates()
            ->where('status', 'active')
            ->get()
            ->each(fn (LicenseCertificate $existing) => $this->revoke($existing, $userId, $reason ?? 'reissued'));

        $keyId = (string) config('services.license_cert.key_id', 'v1');
        $payload = [
            'cert_uuid' => (string) Str::uuid(),
            'license_key' => $license->license_key,
            'domain' => $license->domains->firstWhere('status', 'active')?->domain,
            'seat_limit' => $license->seat_limit ?? $license->subscription?->plan?->seat_limit,
            'issued_at' => now()->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'key_id' => $keyId,
        ];

        $signature = $this->sign($payload, $privateKey);

        return LicenseCertificate::create([
            'license_id' => $license->id,
            'cert_uuid' => $payload['cert_uuid'],
            'key_id' => $keyId,
            'payload' => $payload,
            'signature' => $signature,
            'status' => 'active',
            'issued_by' => $userId,
            'issued_at' => now(),
        ]);
    }

    public function revoke(LicenseCertificate $certificate, ?int $userId, ?string $reason = null): LicenseCertificate
    {
        $certificate->update([
            'status' => 'revoked',
            'revoked_by' => $userId,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ]);

        return $certificate;
    }

    /**
     * Verify a certificate's signature against the configured public key.
     * Exposed for tests and for a future admin "verify" action; the
     * licensed product performs this same check offline.
     */
    public function verify(LicenseCertificate $certificate): bool
    {
        $publicKey = $this->publicKey();
        $canonical = $this->canonicalJson($certificate->payload);
        $signature = base64_decode((string) $certificate->signature, true);

        if ($signature === false) {
            return false;
        }

        return openssl_verify($canonical, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private function sign(array $payload, string $privateKey): string
    {
        $canonical = $this->canonicalJson($payload);

        $ok = openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new RuntimeException('Failed to sign license certificate payload.');
        }

        return base64_encode($signature);
    }

    private function canonicalJson(array $payload): string
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function privateKey(): string
    {
        $encoded = (string) config('services.license_cert.private_key', '');

        if ($encoded === '') {
            throw new RuntimeException('LICENSE_CERT_PRIVATE_KEY is not configured.');
        }

        return base64_decode($encoded);
    }

    private function publicKey(): string
    {
        $encoded = (string) config('services.license_cert.public_key', '');

        if ($encoded === '') {
            throw new RuntimeException('LICENSE_CERT_PUBLIC_KEY is not configured.');
        }

        return base64_decode($encoded);
    }
}
