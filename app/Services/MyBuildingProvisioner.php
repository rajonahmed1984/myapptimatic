<?php

namespace App\Services;

use App\Models\MyBuildingProvision;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Hands an accepted MyBuilding order over to the customer's installation.
 *
 * The call is signed with the shared secret the installation verifies, and
 * every attempt is recorded so a failure can be retried from the admin page
 * rather than leaving a paying customer with nothing.
 */
class MyBuildingProvisioner
{
    public function secret(): string
    {
        return (string) config('mybuilding.provision_secret');
    }

    public function configured(): bool
    {
        return $this->secret() !== '';
    }

    /**
     * Create the building inside the customer's installation.
     * Returns true when the remote side confirmed it.
     */
    public function provision(MyBuildingProvision $provision): bool
    {
        if ($provision->isProvisioned()) {
            return true;
        }

        if (!$this->configured()) {
            $this->fail($provision, 'MYBUILDING_PROVISION_SECRET is not configured on this server.');

            return false;
        }

        $payload = [
            'account_name' => $provision->customer?->company_name
                ?: ($provision->customer?->name ?: $provision->building_name),
            'owner_name' => $provision->owner_name,
            'email' => $provision->owner_email,
            'phone' => $provision->owner_phone,
            // A one-time password the owner is told to change; never stored here.
            'password' => Str::password(14, true, true, false),
            'building_name' => $provision->building_name,
            'building_address' => $provision->building_address,
            'district_id' => $provision->district_id,
            'city_id' => $provision->city_id,
            'area_id' => $provision->area_id,
            'license_key' => $provision->license?->license_key,
            'total_floors' => $provision->total_floors,
            'flats_per_floor' => $provision->flats_per_floor,
            'floor_plan' => $provision->floor_plan ?: null,
            'external_order_id' => $provision->order_id ? (string) $provision->order_id : null,
        ];

        $body = json_encode(array_filter($payload, fn ($v) => $v !== null), JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->secret());

        $url = rtrim($provision->install_url, '/') . '/api/v1/external/register-building';

        $provision->increment('attempts');

        try {
            $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Apptimatic-Timestamp' => $timestamp,
                    'X-Apptimatic-Signature' => $signature,
                ])
                ->timeout((int) config('mybuilding.timeout', 20))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (Throwable $exception) {
            $this->fail($provision, 'Could not reach the installation: ' . $exception->getMessage());

            return false;
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? ('HTTP ' . $response->status());

            // Validation errors carry the useful detail.
            $errors = $response->json('errors');
            if (is_array($errors)) {
                $message .= ' - ' . collect($errors)->flatten()->implode(' ');
            }

            $this->fail($provision, $message);

            return false;
        }

        $data = $response->json('data') ?? [];

        $provision->forceFill([
            'status' => MyBuildingProvision::STATUS_PROVISIONED,
            'provisioned_at' => now(),
            'last_error' => null,
            'remote_building_id' => $data['building_id'] ?? null,
            'remote_client_account_id' => $data['client_account_id'] ?? null,
            'registration_code' => $data['registration_code'] ?? null,
        ])->save();

        Log::info('MyBuilding provisioned', [
            'provision_id' => $provision->id,
            'license_id' => $provision->license_id,
            'building_id' => $data['building_id'] ?? null,
            'flats_created' => $data['flats_created'] ?? null,
        ]);

        return true;
    }

    /**
     * Read the location tree from an installation so the admin can pick a
     * district/city/area instead of typing ids that only exist over there.
     *
     * @return array{ok: bool, districts: array, error: ?string}
     */
    public function locations(string $installUrl): array
    {
        if (!$this->configured()) {
            return ['ok' => false, 'districts' => [], 'error' => 'Provisioning secret is not configured.'];
        }

        $body = json_encode(new \stdClass());
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $this->secret());

        try {
            $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Apptimatic-Timestamp' => $timestamp,
                    'X-Apptimatic-Signature' => $signature,
                ])
                ->timeout((int) config('mybuilding.timeout', 20))
                ->withBody($body, 'application/json')
                ->post(rtrim($installUrl, '/') . '/api/v1/external/locations');
        } catch (Throwable $exception) {
            return ['ok' => false, 'districts' => [], 'error' => 'Could not reach the installation: ' . $exception->getMessage()];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'districts' => [],
                'error' => $response->json('message') ?? ('HTTP ' . $response->status()),
            ];
        }

        return [
            'ok' => true,
            'districts' => $response->json('data.districts') ?? [],
            'error' => null,
        ];
    }

    private function fail(MyBuildingProvision $provision, string $message): void
    {
        $provision->forceFill([
            'status' => MyBuildingProvision::STATUS_FAILED,
            'last_error' => Str::limit($message, 1000),
        ])->save();

        Log::warning('MyBuilding provisioning failed', [
            'provision_id' => $provision->id,
            'license_id' => $provision->license_id,
            'error' => $message,
        ]);
    }
}
