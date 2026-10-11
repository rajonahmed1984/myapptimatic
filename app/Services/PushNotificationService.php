<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PushDevice;
use App\Models\WebPushSubscription;
use App\Services\Push\FcmClient;
use App\Services\Push\WebPushClient;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * Push notifications to the mobile app (Firebase) and to browsers and the
 * installed web app (Web Push). Sending never slows down or breaks the action
 * that triggered it: on a web request it runs after the response has been
 * sent, and a failure is only logged.
 */
class PushNotificationService
{
    public function __construct(
        private readonly FcmClient $fcm,
        private readonly WebPushClient $webPush
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->fcm->isConfigured() || $this->webPush->isConfigured();
    }

    /** Every app install a user of this customer account is signed in on. */
    public function toCustomer(?Customer $customer, string $title, string $body, ?string $url = null): void
    {
        if (! $customer) {
            return;
        }

        $customerId = $customer->id;

        $this->dispatch(fn () => $this->deliver(
            fn (Builder $recipients) => $recipients->whereHas('user', fn (Builder $query) => $query->where('customer_id', $customerId)),
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
            fn (Builder $recipients) => $recipients->whereIn('user_id', $userIds),
            $title,
            $body,
            $url
        ));
    }

    /**
     * Send straight away and report the outcome (used by push:test).
     *
     * @return array{sent: int, invalid: int, failed: int}
     */
    public function sendNowToUser(int $userId, string $title, string $body, ?string $url = null): array
    {
        return $this->deliver(fn (Builder $recipients) => $recipients->where('user_id', $userId), $title, $body, $url);
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
     * @param  Closure(Builder): Builder  $recipients  Narrows a device or subscription query to the people to notify.
     * @return array{sent: int, invalid: int, failed: int}
     */
    private function deliver(Closure $recipients, string $title, string $body, ?string $url): array
    {
        $result = ['sent' => 0, 'invalid' => 0, 'failed' => 0];
        $title = $this->clip($title, 80);
        $body = $this->clip($body, 180);
        $url = $this->relativeUrl($url);

        if ($this->fcm->isConfigured()) {
            $this->deliverToApps($recipients(PushDevice::query()), $title, $body, $url, $result);
        }

        if ($this->webPush->isConfigured()) {
            $this->deliverToBrowsers($recipients(WebPushSubscription::query()), $title, $body, $url, $result);
        }

        return $result;
    }

    /**
     * @param  Builder<PushDevice>  $devices
     * @param  array{sent: int, invalid: int, failed: int}  $result
     */
    private function deliverToApps(Builder $devices, string $title, string $body, ?string $url, array &$result): void
    {
        $data = ['url' => $url];

        foreach ($devices->get(['id', 'token']) as $device) {
            $status = $this->fcm->send($device->token, $title, $body, $data);

            if ($status === FcmClient::SENT) {
                $result['sent']++;
            } elseif ($status === FcmClient::INVALID_TOKEN) {
                $result['invalid']++;
                $device->delete();
            } else {
                $result['failed']++;
            }
        }
    }

    /**
     * @param  Builder<WebPushSubscription>  $subscriptions
     * @param  array{sent: int, invalid: int, failed: int}  $result
     */
    private function deliverToBrowsers(Builder $subscriptions, string $title, string $body, ?string $url, array &$result): void
    {
        $payload = ['title' => $title, 'body' => $body, 'url' => $url];

        foreach ($subscriptions->get(['id', 'endpoint', 'public_key', 'auth_token']) as $subscription) {
            try {
                $status = $this->webPush->send($subscription->endpoint, $subscription->public_key, $subscription->auth_token, $payload);
            } catch (\Throwable $exception) {
                // One bad subscription must not stop the rest.
                report($exception);
                $status = WebPushClient::FAILED;
            }

            if ($status === WebPushClient::SENT) {
                $result['sent']++;
            } elseif ($status === WebPushClient::INVALID_SUBSCRIPTION) {
                $result['invalid']++;
                $subscription->delete();
            } else {
                $result['failed']++;
            }
        }
    }

    /**
     * The app's WebView and the service worker are already on the portal, so a
     * path is all they need (and it keeps working when APP_URL differs from the
     * app's server URL).
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
