<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PushDevice;
use App\Services\Push\FcmClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Push notifications to the mobile app. Sending never slows down or breaks the
 * action that triggered it: on a web request it runs after the response has
 * been sent, and a failure is only logged.
 */
class PushNotificationService
{
    public function __construct(
        private readonly FcmClient $fcm
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->fcm->isConfigured();
    }

    /** Every app install a user of this customer account is signed in on. */
    public function toCustomer(?Customer $customer, string $title, string $body, ?string $url = null): void
    {
        if (! $customer) {
            return;
        }

        $customerId = $customer->id;

        $this->dispatch(fn () => $this->deliver(
            PushDevice::query()->whereHas('user', fn (Builder $query) => $query->where('customer_id', $customerId)),
            $title,
            $body,
            $url
        ));
    }

    /**
     * @param  int|array<int, int>  $userIds
     */
    public function toUsers(int|array $userIds, string $title, string $body, ?string $url = null): void
    {
        $userIds = array_values(array_filter((array) $userIds));
        if ($userIds === []) {
            return;
        }

        $this->dispatch(fn () => $this->deliver(
            PushDevice::query()->whereIn('user_id', $userIds),
            $title,
            $body,
            $url
        ));
    }

    /**
     * Send straight away and report the outcome (used by push:test).
     *
     * @param  Builder<PushDevice>  $devices
     * @return array{sent: int, invalid: int, failed: int}
     */
    public function sendNow(Builder $devices, string $title, string $body, ?string $url = null): array
    {
        return $this->deliver($devices, $title, $body, $url);
    }

    private function dispatch(callable $send): void
    {
        $guarded = function () use ($send) {
            try {
                $send();
            } catch (\Throwable $exception) {
                Log::warning('Push notification failed: '.$exception->getMessage());
            }
        };

        if (app()->runningInConsole()) {
            $guarded();

            return;
        }

        defer($guarded, always: true);
    }

    /**
     * @param  Builder<PushDevice>  $devices
     * @return array{sent: int, invalid: int, failed: int}
     */
    private function deliver(Builder $devices, string $title, string $body, ?string $url): array
    {
        $result = ['sent' => 0, 'invalid' => 0, 'failed' => 0];

        if (! $this->fcm->isConfigured()) {
            return $result;
        }

        $data = ['url' => $this->relativeUrl($url)];

        foreach ($devices->get(['id', 'token']) as $device) {
            $status = $this->fcm->send($device->token, $this->clip($title, 80), $this->clip($body, 180), $data);

            if ($status === FcmClient::SENT) {
                $result['sent']++;
            } elseif ($status === FcmClient::INVALID_TOKEN) {
                $result['invalid']++;
                $device->delete();
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * The app's WebView is already on the portal, so a path is all it needs
     * (and it keeps working when APP_URL differs from the app's server URL).
     */
    private function relativeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    private function clip(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit - 3).'...' : $text;
    }
}
